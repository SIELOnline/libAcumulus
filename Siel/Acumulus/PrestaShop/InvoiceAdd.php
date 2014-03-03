<?php
/**
 * @file Contains class Siel\Acumulus\PrestaShop\InvoiceAdd.
 */
namespace Siel\Acumulus\PrestaShop;

use Configuration;
use Order;
use OrderPayment;
use Customer;
use Address;
use Country;
use Acumulus;
use Siel\Acumulus\ConfigInterface;
use Siel\Acumulus\WebAPI;

/**
 * Class InvoiceAdd defines the logic to add an invoice to Acumulus via their
 * web API.
 */
class InvoiceAdd {

  /** @var Acumulus */
  protected $module;

  /** @var PrestaShopAcumulusConfig */
  protected $acumulusConfig;

  /** @var WebAPI */
  protected $webAPI;

  /** @var bool */
  protected $interactive;

  /**
   * @param PrestaShopAcumulusConfig $config
   * @param Acumulus $module
   * @param WebAPI $webAPI
   */
  public function __construct(PrestaShopAcumulusConfig $config, Acumulus $module, WebAPI $webAPI) {
    $this->module = $module;
    $this->acumulusConfig = $config;
    $this->webAPI = $webAPI;
  }

  /**
   * Send an order to Acumulus.
   *
   * For now we don't check if the order is already sent to Acumulus (in which
   * case we might just update the payment status), we just send it.
   *
   * @param Order $order
   *   The order to send to Acumulus
   *
   * @return array
   *   A keyed array with the following keys:
   *   - errors
   *   - warnings
   *   - status
   *   - invoice (optional)
   *   If the key invoice is present, it indicates success.
   *
   * See https://apidoc.sielsystems.nl/content/warning-error-and-status-response-section-most-api-calls
   * for more information on the contents of the returned array.
   */
  public function send(Order $order) {
    // Create the invoice array.
    $invoice = $this->convertOrderToAcumulusInvoice($order);

    // Send it.
    $result = $this->webAPI->invoiceAdd($invoice, $order->id);

    if ($result['invoice']) {
      // Attach token and invoice number to order: not yet implemented.
    }

    return $result;
  }

  /**
   * @param Order $order
   *
   * @return array
   */
  protected function convertOrderToAcumulusInvoice(Order $order) {
    $invoice = array();
    $invoice['customer'] = $this->addCustomer($order);
    $invoice['customer']['invoice'] = $this->addInvoice($order, $invoice['customer']);
    return $invoice;
  }

  /**
   * Add the customer part to the Acumulus invoice.
   *
   * Fields that do not exist in Prestashop:
   * - salutation: ignore, we don't try to create it based on gender or if it is
   *     a company.
   * - company2: empty
   * - fax: ignore
   * - bankaccountnumber: ignore, it may be available somewhere in payments, but
   *     I could not find it.
   * - mark
   *
   * As we can't provide all fields, the customer data will only be overwritten,
   * if explicitly set via the config. This because overwriting is an all or
   * nothing operation that includes emptying not provided fields.
   *
   * @param Order $order
   *
   * @return array
   */
  protected function addCustomer(Order $order) {
    $customer = new Customer($order->id_customer);
    $invoiceAddress = new Address($order->id_address_invoice);

    $result = array();
    $this->addEmpty($result, 'companyname1', $invoiceAddress->company);
    $result['companyname2'] = '';
    $result['fullname'] = $invoiceAddress->firstname . ' ' . $invoiceAddress->lastname;
    $this->addEmpty($result, 'address1', $invoiceAddress->address1);
    $this->addEmpty($result, 'address2', $invoiceAddress->address2);
    $this->addEmpty($result, 'postalcode', $invoiceAddress->postcode);
    $this->addEmpty($result, 'city', $invoiceAddress->city);
    if ($invoiceAddress->id_country) {
      $result['countrycode'] = Country::getIsoById($invoiceAddress->id_country);
      $result['locationcode'] = $this->webAPI->getLocationCode($result['countrycode']);
    }
    $this->addIfNotEmpty($result, 'vatnumber', $invoiceAddress->vat_number);
    // Add either mobile or phone number to 'telephone'.
    $this->addIfNotEmpty($result, 'telephone', $invoiceAddress->phone);
    $this->addIfNotEmpty($result, 'telephone', $invoiceAddress->phone_mobile);
    $result['email'] = $customer->email;
    $result['overwriteifexists'] = $this->acumulusConfig->get('overwriteIfExists');

    return $result;
  }

  /**
   * Add the invoice part to the Acumulus invoice.
   *
   * @param Order $order
   * @param array $customer
   *
   * @return array
   */
  protected function addInvoice(Order $order, array $customer) {
    $result = array();

    // Set concept to 0: Issue invoice, no concept.
    $result['concept'] = 0;

    $invoiceNrSource = $this->acumulusConfig->get('invoiceNrSource');
    if ($invoiceNrSource != ConfigInterface::InvoiceNrSource_Acumulus) {
      $result['number'] = $order->id;
      // Invoicing has changed in 1.5.0.1, $order->invoice_number will be deprecated in 1.6 (and removed in 1.7?).
      if ($invoiceNrSource == ConfigInterface::InvoiceNrSource_ShopInvoice && !empty($order->invoice_number)) {
        $result['number'] = Configuration::get('PS_INVOICE_PREFIX', (int) $order->id_lang, null, $order->id_shop) . sprintf('%06d', $order->invoice_number);
      }
    }

    $dateToUse = $this->acumulusConfig->get('');
    if ($dateToUse != ConfigInterface::InvoiceDate_Transfer) {
      $result['issuedate'] = date('Y-m-d', strtotime($order->date_add));
      // Invoice_date is filled with "0000-00-00 00:00:00", so use invoice
      // number instead to check for empty.
      // Invoicing has changed in 1.5.0.1, $order->invoice_number will be deprecated in 1.6 (and removed in 1.7?).
      if ($dateToUse == ConfigInterface::InvoiceDate_InvoiceCreate  && !empty($order->invoice_number)) {
        $this->addIfNotEmpty($result, 'issuedate', date('Y-m-d', strtotime($order->invoice_date)));
      }
    }

    if ($order->hasBeenPaid()) {
      $result['paymentstatus'] = WebAPI::PaymentStatus_Paid;
      // Take date of last payment as payment date.
      $paymentDate = null;
      foreach($order->getOrderPaymentCollection() as $payment) {
        /** @var OrderPayment $payment */
        if ($payment->date_add && (!$paymentDate || $payment->date_add > $paymentDate)) {
          $paymentDate = $payment->date_add;
        }
      }
      if ($paymentDate) {
        $result['paymentdate'] = date('Y-m-d', strtotime($paymentDate));
      }
    }
    else {
      $result['paymentstatus'] = WebAPI::PaymentStatus_Due;
    }

    $result['description'] = $this->acumulusConfig->t('order_id') . ' ' . $order->id;

    // Add all order lines.
    $result['line'] = $this->addInvoiceLines($order);

    // Determine vattype.
    $result['vattype'] = $this->webAPI->getVatType($customer, $result);

    return $result;
  }

  /**
   * Add the oder lines to the Acumulus invoice.
   *
   * This includes:
   * - all product lines
   * - discount lines, if any
   * - gift wrapping line, if available
   * - shipping costs, if any
   *
   * @param Order $order
   *
   * @return array
   */
  protected function addInvoiceLines(Order $order) {
    $result = array_merge(
      $this->addOrderLines($order),
      $this->addWrappingLines($order),
      $this->addShippingLines($order),
      $this->addDiscountLines($order)
    );

    return $result;
  }

  /**
   * @param Order $order
   *
   * @return array
   */
  protected function addOrderLines(Order $order) {
    $result = array();
    foreach ($order->getProducts() as $line) {
      $result[] = $this->addOrderLine($line);
    }
    return $result;
  }

  /**
   * @param array $line
   *
   * @return array
   */
  protected function addOrderLine(array $line) {
    $result = array();

    $this->addIfNotEmpty($result, 'itemnumber', $line['product_upc']);
    $this->addIfNotEmpty($result, 'itemnumber', $line['product_ean13']);
    $this->addIfNotEmpty($result, 'itemnumber', $line['product_reference']);
    $result['product'] = $line['product_name'];

    // Prestashop does not support the margin scheme. So in a standard install
    // this method will always return false. But if this method happens to
    // return true anyway (customisation, hook), the costprice will trigger
    // vattype = 5 for Acumulus.
    if ($this->useMarginScheme($line)) {
      // Margin scheme:
      // - Do not put VAT on invoice: send price incl VAT as unitprice.
      // - But still send the VAT rate to Acumulus.
      $result['unitprice'] = number_format($line['product_price_wt'], 4, '.', '');
      // Costprice > 0 is the trigger for Acumulus to use the margin scheme.
      $result['costprice'] = $line['wholesale_price'];
    }
    else {
      // Unit price is without VAT, so use product_price, not product_price_wt.
      $result['unitprice'] = number_format($line['product_price'], 4, '.', '');
      $result['costprice'] = 0;
    }
    $result['vatrate'] = (int) $line['tax_rate'];
    $result['quantity'] = number_format($line['product_quantity'], 2, '.', '');
    return $result;
  }

  /**
   * Adds the wrapping costs to the invoice.
   *
   * In a Prestashop order the wrapping costs are specified in:
   * - gift: boolean indicating if gift wrapping was selected by the customer
   * - total_wrapping_tax_incl
   * - total_wrapping_tax_excl
   * - total_wrapping (ignored)
   *
   * @param Order $order
   *
   * @return array
   */
  protected function addWrappingLines(Order $order) {
    $result = array();
    if ($order->gift && $order->total_wrapping_tax_incl > 0) {
      $result[] = array(
        'product' => $this->acumulusConfig->t('gift_wrapping'),
        'unitprice' => number_format($order->total_wrapping_tax_excl, 4, '.', ''),
        'vatrate' => number_format(($order->total_wrapping_tax_incl / $order->total_wrapping_tax_excl - 1.0) * 100.0),
        'quantity' => 1,
      );
    }
    return $result;
  }

  /**
   * @param Order $order
   *
   * @return array
   */
  protected function addDiscountLines(Order $order) {
    $result = array();
    foreach ($order->getCartRules() as $line) {
      $result[] = $this->addDiscountLine($line);
    }
    return $result;
  }

  /**
   * In a Prestashop order the discount lines are specified in Order cart rules
   * that have the following fields:
   * - value
   * - value_tax_excl
   *
   * @param array $line
   *
   * @return array
   */
  protected function addDiscountLine(array $line) {
    return array(
      'itemnumber' => $line['id_cart_rule'],
      'product' => $this->acumulusConfig->t('discount_code') . ' ' . $line['name'],
      'unitprice' => number_format(-$line['value_tax_excl'], 4, '.', ''),
      'vatrate' => number_format(($line['value'] / $line['value_tax_excl'] - 1.0) * 100.0),
      'quantity' => 1,
    );
  }

  /**
   * Adds the shipping costs to the invoice.
   *
   * In a Pretashop order the shipping costs are specified in:
   * - total_shipping_tax_incl
   * - total_shipping_tax_excl
   * - total_shipping (ignored)
   *
   * NOTE: The method getShipping allows to specify a breakdown of the shipping
   * costs. This could probably/mainly be used to specify the shipping method,
   * instead of the generic "Shipping costs".
   *
   * NOTE: The method Order::updateShippingCost() only updates total_shipping,
   * so I'm a bit unsure about which properties to use.
   *
   * @param Order $order
   *
   * @return array
   */
  protected function addShippingLines(Order $order) {
    $result = array();
    if ($order->total_shipping_tax_incl > 0) {
      $result[] = array(
					'product' => $this->acumulusConfig->t('shipping_costs'),
					'unitprice' => number_format($order->total_shipping_tax_excl, 4, '.', ''),
					'vatrate' => number_format(($order->total_shipping_tax_incl / $order->total_shipping_tax_excl - 1.0) * 100.0),
					'quantity' => 1,
      );
    }
    return $result;
  }

  /**
   * Adds a value only if it is not empty.
   *
   * @param array $array
   * @param string $key
   * @param mixed $value
   *
   * @return bool
   *   whether the value was not empty and thus has been added.
   */
  protected function addIfNotEmpty(array &$array, $key, $value) {
    if (!empty($value)) {
      $array[$key] = $value;
      return true;
    }
    return false;
  }

  /**
   * Adds a value even if it is not set.
   *
   * @param array $array
   * @param string $key
   * @param mixed $value
   * @param mixed $default
   *
   * @return bool
   *   whether the value was empty (true) or if the default was taken (false).
   */
  protected function addEmpty(array &$array, $key, $value, $default = '') {
    if (!empty($value)) {
      $array[$key] = $value;
      return true;
    }
    else {
      $array[$key] = $default;
      return false;
    }
  }

  /**
   * Returns whether the margin scheme should be used for this product.
   *
   * Note: with a standard Prestashop install, the margin scheme is not
   * supported.
   *
   * @param array $product
   *
   * @return bool
   */
  protected function useMarginScheme(array $product) {
    if (isset($product['condition']) && $product['condition'] == 'used' && !empty($product['wholesale_price'])) {
      // Check if the vat calculation was based on the margin, not sales price.
      $realVat = $product['product_price_wt'] - $product['product_price'];
      $marginVat = ($product['product_price'] - $product['wholesale_price']) * $product['tax_rate'] / 100;
      // Real VAT and VAT under margin scheme must be equal (within a cent):
      return abs($realVat - $marginVat) < 0.011;
    }
    return false;
  }
}
