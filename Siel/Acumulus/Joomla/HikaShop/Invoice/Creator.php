<?php
namespace Siel\Acumulus\Joomla\HikaShop\Invoice;

use hikashopConfigClass;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\ConfigInterface as InvoiceConfigInterface;
use Siel\Acumulus\Shop\ConfigInterface;
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

    $billingAddress = $this->order->billing_address;
    // @todo: complete and check
    $this->addIfNotEmpty($result, 'contactyourid', $this->order->order_user_id);
    $this->addIfNotEmpty($result, 'companyname1', $billingAddress->address_company);
    $result['companyname2'] = '';
    if (!empty($result['companyname1'])) {
      // @todo: check if this is the vat mnumber
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
    $this->addIfNotEmpty($result, 'address1', $billingAddress->address_street1);
    $this->addIfNotEmpty($result, 'address2', $billingAddress->address_street2);
    $this->addIfNotEmpty($result, 'postalcode', $billingAddress->address_post_code);
    $this->addIfNotEmpty($result, 'city', $billingAddress->address_city);
    $this->addIfNotEmpty($result, 'countrycode', $billingAddress->address_country_code_2);
    // Preference for 1st phone number.
    $this->addIfNotEmpty($result, 'telephone', $billingAddress->address_telephone2);
    $this->addIfNotEmpty($result, 'telephone', $billingAddress->address_telephone);
    $this->addIfNotEmpty($result, 'fax', $billingAddress->address_fax);
    // Preference for the user email, not the registration email.
    $this->addIfNotEmpty($result, 'email', $this->order->customer->email);
    $this->addIfNotEmpty($result, 'email', $this->order->customer->user_email);

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function searchProperty($property) {
    // @todo: check
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
    // @todo: check
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
    // @todo: check
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
    // @todo: check
    /** @var hikashopConfigClass $config */
    $config = hikashop_config();
    $unpaidStatuses = explode(',',$config->get('order_unpaid_statuses','created'));
    return in_array($this->order->order_status, $unpaidStatuses)
      ? InvoiceConfigInterface::PaymentStatus_Due
      : InvoiceConfigInterface::PaymentStatus_Paid;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPaymentDate() {
    // @todo: check
    // Scan through the history and look for last non empty history_payment_id.
    // History order is by history_created DESC.
    $date = NULL;
    foreach ($this->order->history as $history) {
      if (!empty($history->history_payment_id)) {
        $date = $history->history_created;
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
    // @todo: check
    $vatAmount = 0.0;
    foreach ($this->order->order_taxinfo as $taxInfo) {
      $vatAmount += $taxInfo->tax_amount;
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
    // @todo check
    //$result = array_map(array($this, 'getItemLine'), $this->order['items']);
    $result = array();
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
    // @todo
    $productPriceEx = (float) $item->product_discountedPriceWithoutTax;
    $productPriceInc = (float) $item->product_final_price;
    $productVat = (float) $item->product_tax;

    $calcRule = NULL;
    if (!empty($calcRule->calc_value)) {
      $vatInfo = array(
        'vatrate' => (float) $calcRule->calc_value,
        'meta-vatrate-source' => static::VatRateSource_Exact,
      );
    }
    else {
      $vatInfo = $this->getVatRangeTags($productVat, $productPriceEx, 0.0001, 0.0001);
    }
    $result = array(
        'itemnumber' => $item->order_item_sku,
        'product' => $item->order_item_name,
        'unitprice' => $productPriceEx,
        'unitpriceinc' => $productPriceInc,
        'quantity' => $item->product_quantity,
        'vatamount' => $productVat,
      ) + $vatInfo;

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getShippingLine() {
    // @todo
    $result = array();
//    // We are checking on empty, assuming that a null value will be used to
//    // indicate no shipping at all (downloadable product) and that free shipping
//    // will be represented as the string '0.00' which is not considered empty.
//    if (!empty($this->order['details']['BT']->order_shipment)) {
//      $shippingEx = (float) $this->order['details']['BT']->order_shipment;
//      $shippingVat = (float) $this->order['details']['BT']->order_shipment_tax;
//
//      $calcRule = $this->getCalcRule('shipment');
//      if (!empty($calcRule->calc_value)) {
//        $vatInfo = array(
//          'vatrate' => (float) $calcRule->calc_value,
//          'meta-vatrate-source' => static::VatRateSource_Exact,
//        );
//      }
//      else {
//        $vatInfo = $this->getVatRangeTags($shippingVat, $shippingEx, 0.0001, 0.01);
//      }
//      $result = array(
//          'product' => $this->t('shipping_costs'),
//          'unitprice' => $shippingEx,
//          'quantity' => 1,
//          'vatamount' => $shippingVat,
//        ) + $vatInfo;
//    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscountLines() {
    // @todo
    $result = array();

    // We do have several discount related fields in the order details:
    // - order_billDiscountAmount
    // - order_discountAmount
    // - coupon_discount
    // - order_discount
    // However, these fields seem to be totals based on applied non-tax
    // calculation rules. So it is better to add a line per calc rule with a
    // negative amount: this gives us descriptions of the discounts as well.
//    $result = array_merge($result, array_map(array($this, 'getCalcRuleDiscountLine'),
//      array_filter($this->order['calc_rules'], array($this, 'isDiscountCalcRule'))));
//
//    // Coupon codes are not stored in a calc rules, so handle them separately.
//    if (!Number::isZero($this->order['details']['BT']->coupon_discount)) {
//      $result[] = $this->getCouponCodeDiscountLine();
//    }

    return $result;
  }

  /*
   * Returns a discount item line for the discount calculation rule.
   *
   * The returned line will only contain a discount amount including tax.
   * The completor will have to divide this amount over vat rates that are used
   * in this invoice.
   *
   * @param \stdClass $calcRule
   *
   * @return array
   *   An item line for the invoice.
   */
//  protected function getCalcRuleDiscountLine(stdClass $calcRule) {
//    $result = array(
//      'product' => $calcRule->calc_rule_name,
//      'unitprice' => NULL,
//      'unitpriceinc' => $calcRule->calc_amount,
//      'vatrate' => NULL,
//      'quantity' => 1,
//      'meta-vatrate-source' => static::VatRateSource_Strategy,
//      'meta-strategy-split' => TRUE,
//    );
//
//    return $result;
//  }

  /**
   *  Returns an item line for the coupon code discount on this order.
   *
   * The returned line will only contain a discount amount including tax.
   * The completor will have to divide this amount over vat rates that are used
   * in this invoice.
   *
   * @return array
   *   An item line array.
   */
  protected function getCouponCodeDiscountLine() {
    // @todo
    $result = array(
      'product' => $this->t('coupon_code') . ' ' . $this->order->discount_code,
      'unitprice' => NULL,
      'unitpriceinc' => $this->order->order_discount_price,
      'vatamount' => $this->order->order_discount_tax,
      'vatrate' => NULL,
      'quantity' => 1,
      'meta-vatrate-source' => static::VatRateSource_Strategy,
      'meta-strategy-split' => TRUE,
    );

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPaymentFeeLine() {
    $result = array();
    if (!Number::isZero($this->order->order_payment_price)) {
      // @todo
//      $paymentEx = (float) $this->order['details']['BT']->order_payment;
//      if (!Number::isZero($paymentEx)) {
//        $paymentVat = (float) $this->order['details']['BT']->order_payment_tax;
//
//        $calcRule = $this->getCalcRule('payment');
//        if (!empty($calcRule->calc_value)) {
//          $vatInfo = array(
//            'vatrate' => (float) $calcRule->calc_value,
//            'meta-vatrate-source' => static::VatRateSource_Exact,
//          );
//        }
//        else {
//          $vatInfo = $this->getVatRangeTags($paymentVat, $paymentEx, 0.0001, 0.01);
//        }
//
//        $result = array(
//            'product' => $this->t('payment_costs'),
//            'unitprice' => $paymentEx,
//            'quantity' => 1,
//            'vatamount' => $paymentVat,
//          ) + $vatInfo;
//      }
    }
    return $result;
  }

}
