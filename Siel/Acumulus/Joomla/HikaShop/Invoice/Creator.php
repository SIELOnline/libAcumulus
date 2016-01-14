<?php
namespace Siel\Acumulus\Joomla\HikaShop\Invoice;

use hikashopConfigClass;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\ConfigInterface;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use stdClass;

/**
 * Allows to create arrays in the Acumulus invoice structure from a HikaShop
 * order
 */
class Creator extends BaseCreator {

  /**
   * @var object
   */
  protected $order;

  /**
   * {@inheritdoc}
   *
   * This override also initializes VM specific properties related to the
   * source.
   */
  protected function setInvoiceSource($source) {
    parent::setInvoiceSource($source);
    $this->order = $this->invoiceSource->getSource();
  }

  /**
   * {@inheritdoc}
   */
  protected function getCustomer() {
    $result = array();

    $this->addIfNotEmpty($result, 'contactyourid', $this->order->order_user_id);

    $billingAddress = $this->order->billing_address;
    if (!empty($billingAddress)) {
      // @todo: hoe kan een klant dit (en vat#) invullen?
      $this->addIfNotEmpty($result, 'companyname1', $billingAddress->address_company);
      if (!empty($result['companyname1'])) {
        $this->addIfNotEmpty($result, 'vatnumber', $billingAddress->address_vat);
      }
      $result['fullname'] = $billingAddress->address_lastname;
      if (!empty($billingAddress->address_middle_name)) {
        $result['fullname'] = $billingAddress->address_middle_name . ' ' . $result['fullname'];
      }
      if (!empty($billingAddress->address_firstname)) {
        $result['fullname'] = $billingAddress->address_firstname . ' ' . $result['fullname'];
      }
      if (empty($result['fullname'])) {
        $this->addIfNotEmpty($result, 'fullname', $this->order->customer->name);
      }
      $this->addIfNotEmpty($result, 'address1', $billingAddress->address_street);
      $this->addIfNotEmpty($result, 'address2', $billingAddress->address_street2);
      $this->addIfNotEmpty($result, 'postalcode', $billingAddress->address_post_code);
      $this->addIfNotEmpty($result, 'city', $billingAddress->address_city);
      $this->addIfNotEmpty($result, 'countrycode', $billingAddress->address_country_code_2);
      // Preference for 1st phone number.
      $this->addIfNotEmpty($result, 'telephone', $billingAddress->address_telephone2);
      $this->addIfNotEmpty($result, 'telephone', $billingAddress->address_telephone);
      $this->addIfNotEmpty($result, 'fax', $billingAddress->address_fax);
    }
    else {
      $this->addIfNotEmpty($result, 'fullname', $this->order->customer->name);
    }

    // Preference for the user email, not the registration email.
    $this->addIfNotEmpty($result, 'email', $this->order->customer->email);
    $this->addIfNotEmpty($result, 'email', $this->order->customer->user_email);

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function searchProperty($property) {
    $value = @$this->getProperty($property, $this->order);
    if (empty($value)) {
      $value = @$this->getProperty($property, $this->order->billing_address);
      if (empty($value)) {
        $value = @$this->getProperty($property, $this->order->customer);
      }
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  protected function getInvoiceNumber($invoiceNumberSource) {
    $result = $this->invoiceSource->getReference();
    if ($invoiceNumberSource == ConfigInterface::InvoiceNrSource_ShopInvoice && !empty($this->order->order_invoice_number)) {
      $result = $this->order->order_invoice_number;
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getInvoiceDate($dateToUse) {
    $result = date('Y-m-d', $this->order->order_created);
    if ($dateToUse == ConfigInterface::InvoiceDate_InvoiceCreate && !empty($this->order->order_invoice_created)) {
      $result = date('Y-m-d', $this->order->order_invoice_created);
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPaymentState() {
    /** @var hikashopConfigClass $config */
    $config = hikashop_config();
    $unpaidStatuses = explode(',',$config->get('order_unpaid_statuses','created'));
    return in_array($this->order->order_status, $unpaidStatuses)
      ? ConfigInterface::PaymentStatus_Due
      : ConfigInterface::PaymentStatus_Paid;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPaymentDate() {
    // Scan through the history and look for a non empty history_payment_id.
    // The order of this array is by history_created DESC, we take the one that
    // is furthest away in time.
    $date = NULL;
    foreach ($this->order->history as $history) {
      if (!empty($history->history_payment_id)) {
        $date = $history->history_created;
      }
    }
    if (!$date) {
      // Scan through the history and look for a non unpaid order status.
      // We take the one that is furthest away in time.
      /** @var hikashopConfigClass $config */
      $config = hikashop_config();
      $unpaidStatuses = explode(',',$config->get('order_unpaid_statuses','created'));
      foreach ($this->order->history as $history) {
        if (!empty($history->history_new_status) && !in_array($history->history_new_status, $unpaidStatuses)) {
          $date = $history->history_created;
        }
      }
    }
    return $date ? date('Y-m-d', $date) : $date;
  }

  /**
   * {@inheritdoc}
   *
   * This override provides the values meta-invoiceamountinc and
   * meta-invoicevatamount as they may be needed by the Completor.
   */
  protected function getInvoiceTotals() {
    $vatAmount = 0.0;
    // No order_taxinfo => no tax (?) => vatamount = 0.
    if (!empty($this->order->order_taxinfo)) {
      foreach ($this->order->order_taxinfo as $taxInfo) {
        $vatAmount += $taxInfo->tax_amount;
      }
    }
    return array(
      'meta-invoiceamountinc' => $this->order->order_full_price,
      'meta-invoicevatamount' => $vatAmount,
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getItemLines() {
    $result = array_map(array($this, 'getItemLine'), $this->order->products);
    return $result;
  }

  /**
   * Returns 1 item line for 1 product line.
   *
   * @param stdClass $item
   *
   * @return array
   */
  protected function getItemLine(stdClass $item) {
    $productPriceEx = (float) $item->order_product_price;
    $productVat = (float) $item->order_product_tax;

    // Note that this info remains correct when rates are changed as upon order
    // creation this info is stored in the order_product table.
    if (is_array($item->order_product_tax_info) && count($item->order_product_tax_info) === 1) {
      $productVatInfo = reset($item->order_product_tax_info);
      if (!empty($productVatInfo->tax_rate)) {
        $vatRate = $productVatInfo->tax_rate;
      }
    }
    if (isset($vatRate)) {
      $vatInfo = array(
        'vatrate' => 100.0 * $vatRate,
        'meta-vatrate-source' => static::VatRateSource_Exact,
      );
    }
    else {
      $vatInfo = $this->getVatRangeTags($productVat, $productPriceEx, 0.0001, 0.0001);
    }
    // @todo: options/variations: $item->order_product_options?
    $result = array(
      'itemnumber' => $item->order_product_code,
      'product' => $item->order_product_name,
      'unitprice' => $productPriceEx,
      'lineprice' => $item->order_product_total_price_no_vat,
      'linepriceinc' => $item->order_product_total_price,
      'quantity' => $item->order_product_quantity,
      'vatamount' => $productVat,
    ) + $vatInfo;

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getShippingLine() {
    $result = array();
    // Check if there is a shipping id attached to the order.
    if (!empty($this->order->order_shipping_id)) {
      // Check for free shipping on a credit note.
      if (!Number::isZero($this->order->order_shipping_price) || $this->invoiceSource->getType() !== Source::CreditNote) {
        $shippingInc = (float) $this->order->order_shipping_price;
        $shippingVat = (float) $this->order->order_shipping_tax;
        $shippingEx = $shippingInc - $shippingVat;
        $vatInfo = $this->getVatRangeTags($shippingVat, $shippingEx, 0.0001, 0.0002);
        $description = $this->t('shipping_costs');

        $result = array(
            'product' => $description,
            'unitprice' => $shippingEx,
            'unitpriceinc' => $shippingInc,
            'quantity' => 1,
            'vatamount' => $shippingVat,
          ) + $vatInfo;
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscountLines() {
    $result = array();

    if (!Number::isZero($this->order->order_discount_price)) {
      $discountInc = (float) $this->order->order_discount_price;
      $discountVat = (float) $this->order->order_discount_tax;
      $discountEx = $discountInc - $discountVat;
      $vatInfo = $this->getVatRangeTags($discountVat, $discountEx, 0.0001, 0.0002);
      $description = empty($this->order->order_discount_code)
        ? $this->t('discount')
        : $this->t('discount_code') . ' ' . $this->order->order_discount_code;

      $result[] = array(
          'product' => $description,
          'unitprice' => -$discountEx,
          'unitpriceinc' => -$discountInc,
          'quantity' => 1,
          'vatamount' => -$discountVat,
        ) + $vatInfo;
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPaymentFeeLine() {
    // @todo check (return on refund?)
    $result = array();
    if (!Number::isZero($this->order->order_payment_price)) {
      $paymentInc = (float) $this->order->order_payment_price;
      $paymentVat = (float) $this->order->order_payment_tax;
      $paymentEx = $paymentInc - $paymentVat;
      $vatInfo = $this->getVatRangeTags($paymentVat, $paymentEx, 0.0001, 0.0002);
      $description = $this->t('payment_costs');

      $result = array(
          'product' => $description,
          'unitprice' => $paymentEx,
          'unitpriceinc' => $paymentInc,
          'quantity' => 1,
          'vatamount' => $paymentVat,
        ) + $vatInfo;
    }
    return $result;
  }

}
