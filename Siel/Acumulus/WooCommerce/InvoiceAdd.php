<?php
/**
 * @file Contains class Siel\Acumulus\WooCommerce\InvoiceAdd.
 */
namespace Siel\Acumulus\WooCommerce;

use Acumulus;
use Siel\Acumulus\ConfigInterface;
use Siel\Acumulus\WebAPI;
use WC_Coupon;
use WC_Order;
use WC_Product;

/**
 * Class InvoiceAdd defines the logic to add an invoice to Acumulus via their
 * web API.
 */
class InvoiceAdd {

  /** @var Acumulus */
  protected $module;

  /** @var WooCommerceAcumulusConfig */
  protected $acumulusConfig;

  /** @var WebAPI */
  protected $webAPI;

  /** @var bool */
  protected $interactive;

  /**
   * @param WooCommerceAcumulusConfig $config
   * @param Acumulus $module
   * @param WebAPI $webAPI
   */
  public function __construct(WooCommerceAcumulusConfig $config, Acumulus $module, WebAPI $webAPI) {
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
   * @param WC_Order $order
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
  public function send(WC_Order $order) {
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
   * @param WC_Order $order
   *
   * @return array
   */
  protected function convertOrderToAcumulusInvoice(WC_Order $order) {
    $invoice = array();
    $invoice['customer'] = $this->addCustomer($order);
    $invoice['customer']['invoice'] = $this->addInvoice($order, $invoice['customer']);
    return $invoice;
  }

  /**
   * Add the customer part to the Acumulus invoice.
   *
   * Fields that do not exist in WooCommerce:
   * - salutation: ignore, we don't try to create it based on gender or if it is
   *     a company.
   * - company2: empty
   * - fax: ignore
   * - vatnumber: ignore, not stored by default, but there is a paid module for
   *     entering and storing VAT numbers: http://www.woothemes.com/products/eu-vat-number/
   * - bankaccountnumber: ignore, I did not find a module that covers this.
   * - mark
   *
   * As we can't provide all fields, the customer data will only be overwritten,
   * if explicitly set via the config. This because overwriting is an all or
   * nothing operation that includes emptying not provided fields.
   *
   * @param WC_Order $order
   *
   * @return array
   */
  protected function addCustomer(WC_Order $order) {
    $result = array();
    $this->addEmpty($result, 'companyname1', $order->billing_company);
    $result['companyname2'] = '';
    $result['fullname'] = $order->billing_first_name . ' ' . $order->billing_last_name;
    $this->addEmpty($result, 'address1', $order->billing_address_1);
    $this->addEmpty($result, 'address2', $order->billing_address_2);
    $this->addEmpty($result, 'postalcode', $order->billing_postcode);
    $this->addEmpty($result, 'city', $order->billing_city);
    if (isset($order->billing_country)) {
      $result['countrycode'] = $order->billing_country;
      $result['locationcode'] = $this->webAPI->getLocationCode($result['countrycode']);
    }
    $this->addIfNotEmpty($result, 'telephone', $order->billing_phone);
    $result['email'] = $order->billing_email;
    $result['overwriteifexists'] = $this->acumulusConfig->get('overwriteIfExists');

    return $result;
  }

  /**
   * Add the invoice part to the Acumulus invoice.
   *
   * @param WC_Order $order
   * @param array $customer
   *
   * @return array
   */
  protected function addInvoice(WC_Order $order, array $customer) {
    $result = array();

    // Set concept to 0: Issue invoice, no concept.
    $result['concept'] = WebAPI::Concept_No;

    $invoiceNrSource = $this->acumulusConfig->get('invoiceNrSource');
    if ($invoiceNrSource != ConfigInterface::InvoiceNrSource_Acumulus) {
      $result['number'] = $order->id;
    }

    $dateToUse = $this->acumulusConfig->get('');
    if ($dateToUse != ConfigInterface::InvoiceDate_Transfer) {
      $result['issuedate'] = date('Y-m-d', strtotime($order->order_date));
    }

    // _paid_date meta property is set in WC_Order::payment_complete().
    if (empty($order->_paid_date) || $order->needs_payment()) {
      $result['paymentstatus'] = WebAPI::PaymentStatus_Due;
    }
    else {
      $result['paymentstatus'] = WebAPI::PaymentStatus_Paid;
      if (!empty($order->_paid_date)) {
        $result['paymentdate'] = date('Y-m-d', strtotime($order->_paid_date));
      }
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
   * - shipping costs, if any. With free shipping, no shipping costs will be
   *   specified at all.
   * - fees
   *
   * Coupon discounts:
   * - Are applied to the item lines themselves.
   * - if they grant free shipping, the shipping costs line will be removed.
   * Because of the above, if $order->get_total_discount() returns a value it
   * should not be added as a separate line to the invoice. However, this does
   * not hold when the option "Apply discount before taxes" was not set, but
   * this option should always be set!, or the tax calculation will fail and you
   * will pay too much VAT.
   *
   * @param WC_Order $order
   *
   * @return array
   */
  protected function addInvoiceLines(WC_Order $order) {
    $result = array_merge(
      $this->addOrderLines($order),
      $this->addShippingLines($order),
      $this->addFeeLines($order),
      $this->addDiscountLines($order)
    );

    return $result;
  }

  /**
   * @param WC_Order $order
   *
   * @return array
   */
  protected function addOrderLines(WC_Order $order) {
    $result = array();
    foreach ($order->get_items() as $line) {
      $result[] = $this->addOrderLine($line, $order);
    }
    return $result;
  }

  /**
   * Because discounts are applied to the order lines themselves, the unit price
   * should not be taken from the product, but from the item line. (Actually,
   * because of possible price changes between the order creation and the
   * sending of the order to Acumulus, this should always be done so!)
   *
   * @param array $line
   * @param \WC_Order $order
   *
   * @return array
   */
  protected function addOrderLine(array $line, WC_Order $order) {
    $result = array();

    $product = get_product($line['variation_id'] ? $line['variation_id'] : $line['product_id'] );
    // get_item_total returns cost per item after discount, ex vat (2nd parameter).
    $priceEx = $order->get_item_total($line, false, false);
    // get_item_tax returns tax per item after discount.
    $tax = $order->get_item_tax($line, false);
    $vatRate = $tax / $priceEx * 100;

    $this->addIfNotEmpty($result, 'itemnumber', $product->get_sku());
    $result['product'] = $line['name'];

    // WooCommerce does not support the margin scheme. So in a standard install
    // this method will always return false. But if this method happens to
    // return true anyway (customisation, hook), the costprice will trigger
    // vattype = 5 for Acumulus.
    if ($this->useMarginScheme($product)) {
      // Margin scheme:
      // - Do not put VAT on invoice: send price incl VAT as unitprice.
      // - But still send the VAT rate to Acumulus.
      $result['unitprice'] = number_format($priceEx + $tax, 4, '.', '');
      // Costprice > 0 is the trigger for Acumulus to use the margin scheme.
      $result['costprice'] = $line['cost_price'];
    }
    else {
      // Unit price is without VAT, so use product_price, not product_price_wt.
      $result['unitprice'] = number_format($priceEx, 4, '.', '');
      $result['costprice'] = 0;
    }
    $result['vatrate'] = number_format($vatRate);
    $result['quantity'] = number_format($line['qty'], 2, '.', '');
    return $result;
  }

  /**
   * Adds the shipping costs to the invoice.
   *
   * @param WC_Order $order
   *
   * @return array
   */
  protected function addShippingLines(WC_Order $order) {
    $result = array();
    $shipping = $order->get_total_shipping();
    if ($shipping != 0) {
      $result[] = array(
        'product' => $this->acumulusConfig->t('shipping_costs'),
        'unitprice' => number_format($shipping, 4, '.', ''),
        'vatrate' => number_format(($order->get_shipping_tax() / $shipping) * 100.0),
        'quantity' => 1,
      );
    }
    return $result;
  }

  /**
   * @param WC_Order $order
   *
   * @return array
   */
  protected function addFeeLines(WC_Order $order) {
    $result = array();
    foreach ($order->get_fees() as $line) {
      $result[] = $this->addFeeLine($line);
    }
    return $result;
  }

  /**
   * @param array $line
   *
   * @return array
   */
  protected function addFeeLine(array $line) {
    return array(
      'itemnumber' => $this->acumulusConfig->t('fee_code'),
      'product' => $this->acumulusConfig->t($line['name']),
      'unitprice' => number_format($line['line_total'], 4, '.', ''),
      'vatrate' => number_format(($line['line_tax'] / $line['line_total']) * 100.0),
      'quantity' => 1,
    );
  }

  /**
   * @param WC_Order $order
   *
   * @return array
   */
  protected function addDiscountLines(WC_Order $order) {
    $result = array();
    foreach ($order->get_used_coupons() as $code) {
      if ($code) {
        $result[] = $this->addDiscountLine(new WC_Coupon($code), $order);
      }
    }
    return $result;
  }

  /**
   * In WooCommerce discounts can be set as "apply before tax" or "aplly after
   * tax":
   * - The discounts before tax are applied to the product lines themselves, so
   *   they don't have to appear in a separate discount line. However, for
   *   reasons of clarity a 0-amount line will be added to the invoice, so one
   *   can easily see which coupons are used for an order.
   * - The coupons that are applied after tax are to be seen as gift vouchers
   *   and should be added as a (partial) payment for the order. This means that
   *   they don't have vat.
   *
   * @param \WC_Coupon $coupon
   * @param \WC_Order $order
   *
   * @return array
   */
  protected function addDiscountLine(WC_Coupon $coupon, $order) {
    $orderTotalBeforeDiscount = $order->get_total();
    $orderTotalBeforeDiscount += $coupon->type === 'fixed_cart' ? $order->get_cart_discount() : $order->get_order_discount();
    $displayAmount = in_array($coupon->type, array('fixed_product', 'fixed_cart')) ? "€{$coupon->amount}" : "{$coupon->amount}%";
    $description = $this->acumulusConfig->t('discount_code') . " {$coupon->code} ($displayAmount)" ;
    $amount = $coupon->apply_before_tax() ? 0 : $coupon->get_discount_amount($orderTotalBeforeDiscount);
    return array(
      'itemnumber' => $coupon->code,
      'product' => $description,
      'unitprice' => number_format(-$amount, 4, '.', ''),
      'vatrate' => number_format(-1),
      'quantity' => 1,
    );
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
   * Note: with a standard WooCommerce install, the margin scheme is not
   * supported.
   *
   * @param \WC_Product $product
   *
   * @return bool
   */
  protected function useMarginScheme(WC_Product $product) {
    // Standard WooCommerce cannot handle cost price, let alone the margin scheme.
    return false;
  }
}
