<?php
/**
 * @file Contains class InvoiceAdd
 */

namespace Siel\Acumulus\OpenCart;

use ModelModuleAcumulus;
use Siel\Acumulus\WebAPI;

/**
 * Class InvoiceAdd defines the logic to add an invoice to Acumulus via their
 * web API.
 */
class InvoiceAdd {

  /** @var \ModelModuleAcumulus */
  protected $module;

  /** @var OpenCartAcumulusConfig */
  protected $acumulusConfig;

  /** @var WebAPI */
  protected $webAPI;

  /** @var array */
  protected $warnings;

  /** @var bool */
  protected $interactive;

  /**
   * @param OpenCartAcumulusConfig $config
   * @param \ModelModuleAcumulus $module
   */
  public function __construct(OpenCartAcumulusConfig $config, ModelModuleAcumulus $module) {
    $this->module = $module;
    $this->acumulusConfig = $config;
    $this->webAPI = new WebAPI($this->acumulusConfig);
  }

  /**
   * Send an order to Acumulus.
   *
   * For now we don't check if the order is already sent to Acumulus (in which
   * case we might just update the payment status), we just send it.
   *
   * @param array $order
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
  public function send(array $order) {
    $this->warnings = array();

    // Create the invoice array.
    $invoice = $this->convertOrderToAcumulusInvoice($order);

    // Send it.
    $result = $this->webAPI->invoiceAdd($invoice, $order['order_id']);

    if ($result['invoice']) {
      // Attach token and invoice number to order: not yet implemented.
    }

    if (!empty($this->warnings)) {
      $result['warnings'] += $this->warnings;
    }
    return $result;
  }

  /**
   * @param array $order
   *
   * @return array
   */
  protected function convertOrderToAcumulusInvoice(array $order) {
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
   * - bankaccountnumber: ignore, it may be available somewhere in payments, but
   *     I could not find it.
   * - mark
   *
   * As we can't provide all fields, the customer data will only be overwritten,
   * if explicitly set via the config. This because overwriting is an all or
   * nothing operation that includes emptying not provided fields.
   *
   * @param array $order
   *
   * @return array
   */
  protected function addCustomer(array $order) {
    $result = array();
    $this->addEmpty($result, 'companyname1', $order['payment_company']);
    $result['companyname2'] = '';
    $result['fullname'] = $order['firstname'] . ' ' . $order['lastname'];
    $this->addEmpty($result, 'address1', $order['payment_address_1']);
    $this->addEmpty($result, 'address2', $order['payment_address_2']);
    $this->addEmpty($result, 'postalcode', $order['payment_postcode']);
    $this->addEmpty($result, 'city', $order['payment_city']);
    if (!empty($order['payment_iso_code_2'])) {
      $result['countrycode'] = $order['payment_iso_code_2'];
      $result['locationcode'] = $this->webAPI->getLocationCode($result['countrycode']);
    }
    $this->addIfNotEmpty($result, 'vatnumber', $order['payment_tax_id']);
    $this->addIfNotEmpty($result, 'telephone', $order['telephone']);
    $this->addIfNotEmpty($result, 'fax', $order['fax']);
    $result['email'] = $order['email'];
    $result['overwriteifexists'] = $this->acumulusConfig->get('overwriteIfExists');

    return $result;
  }

  /**
   * Add the invoice part to the Acumulus invoice.
   *
   * @param array $order
   * @param array $customer
   *
   * @return array
   */
  protected function addInvoice(array $order, array $customer) {
    $result = array();

    // Set concept to 0: Issue invoice, no concept.
    $result['concept'] = 0;

    if (!$this->acumulusConfig->get('useAcumulusInvoiceNr')) {
      // OpenCart has an order_id and an invoice_no, we take the latter if available.
      $result['number'] = empty($order['invoice_no']) ? $order['order_id'] : $order['invoice_prefix'] . $order['invoice_no'];
    }
    if ($this->acumulusConfig->get('useOrderDate')) {
      $result['issuedate'] = substr($order['date_added'], 0, strlen("2014-01-01"));
    }
    // @todo: get payment status (config?)
    if (true) {
      $result['paymentstatus'] = WebAPI::PaymentStatus_Paid;
      $result['paymentdate'] = substr($order['date_modified'], 0, strlen("2014-01-01"));
    }
    else {
      $result['paymentstatus'] = WebAPI::PaymentStatus_Due;
    }
    $result['description'] = 'Ordernummer ' . $order['order_id'];

    // Add all order lines.
    $result['line'] = $this->addInvoiceLines($order);

    // Determine VAT type.
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
   * - voucher lines, if any
   *
   * @param array $order
   *
   * @return array
   */
  protected function addInvoiceLines(array $order) {
    $taxesOnProducts = array();
    $taxLines = array();
    $orderLines = $this->addOrderLines($order, $taxesOnProducts);
    $totalLines = $this->addTotalLines($order, $taxLines);

    $this->repairTaxes($totalLines, $taxesOnProducts, $taxLines);

    $result = array_merge(
      $orderLines,
      $totalLines
    );

    return $result;
  }

  /**
   * Adds the product order lines.
   *
   * @param array $order
   * @param array $taxesOnProducts
   *
   * @return array
   */
  protected function addOrderLines(array $order, array &$taxesOnProducts) {
    $result = array();

    foreach ($this->module->model_sale_order->getOrderProducts($order['order_id']) as $line) {
      $result[] = $this->addOrderLine($line, $taxesOnProducts);
    }
    return $result;
  }

  /**
   * Adds 1 order line.
   *
   * @param array $line
   * @param array $taxes
   *
   * @return array
   */
  protected function addOrderLine(array $line, array &$taxes) {
    $result = array();

    $this->module->load->model('catalog/product');
    $product = $this->module->model_catalog_product->getProduct($line['product_id']);
    $this->addIfNotEmpty($result, 'itemnumber', $product['mpn']);
    $this->addIfNotEmpty($result, 'itemnumber', $product['isbn']);
    $this->addIfNotEmpty($result, 'itemnumber', $product['jan']);
    $this->addIfNotEmpty($result, 'itemnumber', $product['ean']);
    $this->addIfNotEmpty($result, 'itemnumber', $product['upc']);
    $this->addIfNotEmpty($result, 'itemnumber', $product['sku']);

    $result['product'] = $line['name'];
    if (!empty($line['model'])) {
      $result['product'] .= ' (' . $line['model'] . ')';
    }

    // OpenCart does not support the margin scheme. So for now this method will
    // always return false. But if an extension exists that implements this and
    // we start supporting it, setting the cost price will trigger vattype = 5
    // for Acumulus.
    $result['unitprice'] = number_format($line['price'], 4, '.', '');
    if ($this->useMarginScheme($line, $product)) {
      // Change this when OpenCart can support the margin scheme.
      $result['costprice'] = $line['costprice'];
      $result['vatrate'] = (int) round(100.0 * $line['tax'] /  ($result['unitprice'] - $result['costprice']));
    }
    else {
      $result['costprice'] = 0;
      $result['vatrate'] = (int) round(100.0 * $line['tax'] /  $result['unitprice']);
    }
    $result['quantity'] = number_format($line['quantity'], 2, '.', '');

    // Administer taxes per tax rate.
    if (array_key_exists($result['vatrate'], $taxes)) {
      $taxes[$result['vatrate']] += $line['tax'];
    }
    else {
      $taxes[$result['vatrate']] = $line['tax'];
    }

    return $result;
  }

  /**
   * Adds the "order total" lines to the invoice.
   *
   * In an OpenCart order the order total lines specify:
   * - sub total
   * - coupons
   * - shipping
   * - handling or other fees
   * - ... other costs/deductions defined by other extensions
   * - tax
   * - total
   *
   * These lines are added to the order sub total (based on purchased products)
   * to arrive at the order total. Discounts are specified as negative amounts
   * and are ex VAT, so they do not require different processing from our side.
   *
   * The following line types (specified in the field 'code') should not be
   * processed:
   * - sub_total: sub total of all (real) product lines.
   * - tax: total of all taxes (this should be spread over the separate lines).
   * - voucher: used as a partial payment.
   * - total: result of all other lines.
   *
   * @param array $order
   * @param array $taxLines
   *
   * @return array
   */
  protected function addTotalLines(array $order, array &$taxLines) {
    $result = array();
    foreach ($this->module->model_sale_order->getOrderTotals($order['order_id']) as $line) {
      if (!in_array($line['code'], array('sub_total', 'tax', 'total', 'voucher'))) {
        $result[] = $this->addTotalLine($line);
      }
      else if ($line['code'] === 'tax') {
        $taxLines[$line['title']] = $line['value'];
      }
    }
    return $result;
  }

  /**
   * Adds 1 total line.
   *
   * Known codes:
   * - shipping: has a tax rate defined in its definition, but that cannot be
   *   retrieved from the database
   * - coupon:
   * - voucher: To be deducted from the net total amount (thus no vat)
   *
   * @param array $line
   *
   * @return array
   */
  protected function addTotalLine(array $line) {
    $result = array();

    $result['itemnumber'] = $line['code'];
    $result['product'] = $line['title'];
    $result['unitprice'] = number_format($line['value'], 4, '.', '');
    // Vouchers have no VAT, other lines get a null for now and we try to repair
    // that afterwards.
    $result['vatrate'] = $line['code'] === 'voucher' ?  number_format(-1) : null;
    $result['quantity'] = 1;

    return $result;
  }

  /**
   * @param array $totalLines
   * @param array $taxesOnProducts
   * @param array $taxLines
   */
  protected function repairTaxes(&$totalLines, $taxesOnProducts, $taxLines) {
    if (count($taxLines) === 1) {
      // 1 tax rate only: shipping, coupons and handling must also have that tax
      // rate.
      reset($taxesOnProducts);
      $vatRate = key($taxesOnProducts);
      foreach ($totalLines as &$totalLine) {
        if ($totalLine['vatrate'] === null) {
          $totalLine['vatrate'] = $vatRate;
        }
      }
    }
    else {
      // Shipping and handling should get the highest tax rate (doable).
      // Coupon lines should be split if they are for products from different
      // tax rates (to difficult: set those lines to no vat and warn the user.)
      $maxRate = 0;
      foreach ($taxesOnProducts as $vatRate => $dummy) {
        if ($vatRate > $maxRate) {
          $maxRate = $vatRate;
        }
      }
      foreach ($totalLines as &$totalLine) {
        if ($totalLine['vatrate'] === null) {
          $totalLine['vatrate'] = number_format($totalLine['itemnumber'] === 'coupon' ? -1 : $maxRate);
        }
      }
      $this->warnings[] = array(
        'code' => 'Order',
        'codetag' => '',
        'message' => $this->acumulusConfig->t('message_warning_multiplevat'),
      );
    }
    // @todo: Should we do a check on totals?
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
   * Note: with a standard OpenCart install, the margin scheme is not
   * supported.
   *
   * param array $product
   *
   * @return bool
   */
  protected function useMarginScheme(/*array $product*/) {
    return false;
  }
}
