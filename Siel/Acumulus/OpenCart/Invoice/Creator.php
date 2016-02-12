<?php
namespace Siel\Acumulus\OpenCart\Invoice;

use Siel\Acumulus\Invoice\ConfigInterface;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use Siel\Acumulus\OpenCart\Helpers\Registry;

/**
 * Allows to create arrays in the Acumulus invoice structure from an OpenCart
 * order.
 */
class Creator extends BaseCreator {

  // More specifically typed property.
  /** @var array */
  protected $order;

  /** @var array[] List of order total records. */
  protected $orderTotalLines;

  /**
   * {@inheritdoc}
   *
   * This override also initializes WooCommerce specific properties related to
   * the source.
   */
  protected function setInvoiceSource($invoiceSource) {
    parent::setInvoiceSource($invoiceSource);

    // Load some models and properties we are going to use.
    Registry::getInstance()->load->model('catalog/product');
    $this->orderTotalLines = NULL;

    switch ($this->invoiceSource->getType()) {
      case Source::Order:
        $this->order = $this->invoiceSource->getSource();
        break;
      case Source::CreditNote:
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getCustomer() {
    $order = $this->order;
    $result = array();

    $this->addIfSetAndNotEmpty($result, 'contactyourid', $order, 'customer_id');
    $this->addEmpty($result, 'companyname1', $order['payment_company']);
    if (!empty($result['companyname1'])) {
      // This is not a column in any table.
      // @todo: Are there VAT number extensions?
      $this->addIfSetAndNotEmpty($result, 'vatnumber', $order, 'payment_tax_id');
    }
    $result['fullname'] = $order['firstname'] . ' ' . $order['lastname'];
    $this->addEmpty($result, 'address1', $order['payment_address_1']);
    $this->addEmpty($result, 'address2', $order['payment_address_2']);
    $this->addEmpty($result, 'postalcode', $order['payment_postcode']);
    $this->addEmpty($result, 'city', $order['payment_city']);
    if (!empty($order['payment_iso_code_2'])) {
      $result['countrycode'] = $order['payment_iso_code_2'];
    }
    $this->addIfSetAndNotEmpty($result, 'country', $order, 'payment_country');
    $this->addIfSetAndNotEmpty($result, 'telephone', $order, 'telephone');
    $this->addIfSetAndNotEmpty($result, 'fax', $order, 'fax');
    $result['email'] = $order['email'];

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function searchProperty($property) {
    // @todo: $this->order is the only array to search?
    $value = parent::searchProperty($property);
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  protected function getInvoiceNumber($invoiceNumberSource) {
    $result = $this->invoiceSource->getReference();
    if ($invoiceNumberSource == ConfigInterface::InvoiceNrSource_ShopInvoice && !empty($this->order['invoice_no'])) {
      $result = $this->order['invoice_prefix'] . $this->order['invoice_no'];
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getInvoiceDate($dateToUse) {
    $result = substr($this->order['date_added'], 0, strlen('2000-01-01'));
    // There doesn't seem to be an invoice date: stick with order create date,
    // also when $dateToUse === ConfigInterface::InvoiceDate_InvoiceCreate.
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPaymentState() {
    // @todo: Can we get the payment status (introduce setting based on order state?).
    $result = ConfigInterface::PaymentStatus_Paid;
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPaymentDate() {
    // Will default to the issue date.
    return NULL;
  }

  /**
   * {@inheritdoc}
   *
   * This override provides the values meta-invoice-amountinc,
   * meta-invoice-vatamount and a vat breakdown in meta-invoice-vat.
   */
  protected function getInvoiceTotals() {
    $result = array(
      'meta-invoice-amountinc' => $this->order['total'],
      'meta-invoice-vatamount' => 0.0,
      'meta-invoice-vat' => array(),
    );

    $orderTotals = $this->getOrderTotalLines();
    foreach ($orderTotals as $totalLine) {
      if ($totalLine['code'] === 'tax') {
        $result['meta-invoice-vat'][] = $totalLine['title'] . ': '. $totalLine['value'];
        $result['meta-invoice-vatamount'] += $totalLine['value'];
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getInvoiceLines() {
    $itemLines = $this->getItemLines();
    $itemLines = $this->addLineType($itemLines, static::LineType_Order);

    $totalLines = $this->getTotalLines();

    $result = array_merge($itemLines, $totalLines);
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getItemLines() {
    $result = array();

    $orderProducts = $this->getOrderModel()->getOrderProducts($this->invoiceSource->getId());
    foreach ($orderProducts as $line) {
      $result = array_merge($result, $this->getItemLine($line));
    }

    return $result;
  }

  /**
   * Returns the item line(s) for 1 product line.
   *
   * This method may return multiple lines if there are many options.
   * These additional lines will be informative, their price will be 0.
   *
   * @param array $item
   *
   * @return array[]
   */
  protected function getItemLine(array $item) {
    $result = array();

    $product = Registry::getInstance()->model_catalog_product->getProduct($item['product_id']);
    $this->addIfSetAndNotEmpty($result, 'itemnumber', $product, 'mpn');
    $this->addIfSetAndNotEmpty($result, 'itemnumber', $product, 'isbn');
    $this->addIfSetAndNotEmpty($result, 'itemnumber', $product, 'jan');
    $this->addIfSetAndNotEmpty($result, 'itemnumber', $product, 'ean');
    $this->addIfSetAndNotEmpty($result, 'itemnumber', $product, 'upc');
    $this->addIfSetAndNotEmpty($result, 'itemnumber', $product, 'sku');

    // Product name, model, and option(s).
    $result['product'] = $item['name'];
    if (!empty($item['model'])) {
      $result['product'] .= ' (' . $item['model'] . ')';
    }

    $productPriceEx = $item['price'];
    $productVat = $item['tax'];
    $vatInfo = $this->getVatRangeTags($productVat, $productPriceEx);

    // Options (variants).
    $optionsLines = array();
    $optionsTexts = array();
    $options = $this->getOrderModel()->getOrderOptions($item['order_id'], $item['order_product_id']);
    foreach ($options as $option) {
      $optionsTexts[] = "{$option['name']}: {$option['value']}";
    }
    if (count($options) > 0) {
      $optionsText = ' (' . implode(', ', $optionsTexts) . ')';

      $invoiceSettings = $this->config->getInvoiceSettings();
      if (count($options) <= $invoiceSettings['optionsAllOn1Line']
        || (count($options) < $invoiceSettings['optionsAllOnOwnLine'] && strlen($optionsText) <= $invoiceSettings['optionsMaxLength'])
      ) {
        // All options on 1 item.
        $result['product'] .= ' (' . implode(', ', $optionsTexts) . ')';
      }
      else {
        // All options on their own item.
        foreach ($optionsTexts as $optionsText) {
          $optionsLines[] = array(
              'product' => " - $optionsText",
              'unitprice' => 0,
              // @todo: is an option/variant/composant always quantity 1?
              'quantity' => 1,
            ) + $vatInfo;
        }

      }
    }

    $result['unitprice'] = $productPriceEx;
    $result['quantity'] = $item['quantity'];
    $result += $vatInfo;
    $result['vatamount'] = $productVat;

    return array_merge(array($result), $optionsLines);
  }

  /**
   *
   *
   *
   * @return array[]
   *
   */
  protected function getTotalLines() {
    $result = array();

    $totalLines = $this->getOrderTotalLines();
    foreach ($totalLines as $totalLine) {
      switch ($totalLine['code']) {
        case 'sub_total':
          // Sub total of all product lines: ignore.
          $line = NULL;
          break;
        case 'shipping':
          $line = $this->getTotalLine($totalLine);
          $line['meta-line-type'] = static::LineType_Shipping;
          break;
        case 'coupon':
          $line = $this->getTotalLine($totalLine);
          $line['meta-line-type'] = static::LineType_Discount;
          break;
        case 'tax':
          // Tax line: added to invoice level
          $line = NULL;
          break;
        case 'voucher':
          $line = $this->getTotalLine($totalLine);
          $line['meta-line-type'] = static::LineType_Voucher;
          break;
        case 'total':
          // Overall total: ignore.
          $line = NULL;
          break;
        default:
          $line = $this->getTotalLine($totalLine);
          $line['meta-line-type'] = static::LineType_Other;
          break;
      }
      if ($line) {
        $result[] = $line;
      }
    }

    return $result;
  }


  /**
   * Returns a line based on a "order total line".
   *
   * @param array $line
   *
   * @return array
   */
  protected function getTotalLine(array $line) {
    $result = array(
      'product' => $line['title'],
      // Let's hope that this is the value ex vat...
      'unitprice' => $line['value'],
      'quantity' => 1,
    );

    if ($line['code'] === 'voucher') {
      // A voucher is to be seen as a partial payment, thus no tax.
      $result += array(
        'vatrate' => -1,
        'meta-vatrate-source' => Creator::VatRateSource_Exact0,
      );
    }
    else {
      // Other lines do not have a discoverable vatrate, let a strategy try to
      // compute it.
      $result += array(
        'vatrate' => NULL,
        'meta-vatrate-source' => Creator::VatRateSource_Strategy,
        // Coupons may have to be split over various taxes, but shipping and
        // other fees not.
        'meta-strategy-split' => $line['code'] === 'coupun',
      );
    }

    return $result;
  }

  /**
   *
   *
   *
   * @return mixed
   *
   */
  protected function getOrderTotalLines() {
    if (!$this->orderTotalLines) {
      $orderModel = $this->getOrderModel();
      $this->orderTotalLines = $orderModel->getOrderTotals($this->order['order_id']);
    }
    return $this->orderTotalLines;
  }

  /**
   *
   *
   *
   * @return \ModelAccountOrder|\ModelSaleOrder
   *
   */
  protected function getOrderModel() {
    if (strrpos(DIR_APPLICATION, '/catalog/') === strlen(DIR_APPLICATION) - strlen('/catalog/')) {
      // We are in the catalog section, use the account/order model.
      Registry::getInstance()->load->model('account/order');
      $orderModel = Registry::getInstance()->model_account_order;
    }
    else {
      // We are in the admin section, use the sale/order model.
      Registry::getInstance()->load->model('sale/order');
      $orderModel = Registry::getInstance()->model_sale_order;
    }
    return $orderModel;
  }

}
