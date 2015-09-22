<?php
namespace Siel\Acumulus\VirtueMart\Invoice;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\ConfigInterface as InvoiceConfigInterface;
use Siel\Acumulus\Shop\ConfigInterface;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use stdClass;
use VmModel;

/**
 * Allows to create arrays in the Acumulus invoice structure from a VirtueMart
 * order
 *
 * Note: the VMInvoice extension offers credit notes, but for now we do not
 *   support this.
 */
class Creator extends BaseCreator {

  /** @var \VirtueMartModelOrders */
  protected $orderModel;

  /**
   * @var array
   * Array with keys:
   * [details]
   *   [BT]: stdClass (BillTo details)
   *   [ST]: stdClass (ShipTo details, if available)
   * [history]
   *   [0]: stdClass (virtuemart_order_histories table record)
   *   ...
   * [items]
   *   [0]: stdClass (virtuemart_order_items table record)
   *   ...
   * [calc_rules]
   *   [0]: stdClass (virtuemart_order_calc_rules table record)
   *   ...
   */
  protected $order;

  /** @var array */
  protected $shopInvoice = array();

  /** @var stdClass
   *  Object with properties:
   *  [...]: virtuemart_vmusers table record columns
   *  [shopper_groups]: array of stdClass virtuemart_vmuser_shoppergroups table
   * records
   *  [JUser]: JUser object
   *  [userInfo]: array of stdClass virtuemart_userinfos table records
   */
  protected $user;

  /** @var int */
  protected $userBtUid;

  /**
   * {@inheritdoc}
   *
   * This override also initializes VM specific properties related to the
   * source.
   */
  protected function setInvoiceSource($source) {
    parent::setInvoiceSource($source);
    $this->order = $this->invoiceSource->getSource();
    $this->orderModel = VmModel::getModel('orders');
    /** @var \TableInvoices $invoiceTable */
    if ($invoiceTable = $this->orderModel->getTable('invoices')->load($this->order['details']['BT']->virtuemart_order_id, 'virtuemart_order_id')) {
      $this->shopInvoice = $invoiceTable->getProperties();
    }
    if (!empty($this->order['details']['BT']->virtuemart_user_id)) {
      /** @var \VirtueMartModelUser $userModel */
      $userModel = VmModel::getModel('user');
      $userModel->setId($this->order['details']['BT']->virtuemart_user_id);
      $this->user = $userModel->getUser();
      $this->userBtUid = NULL;

      foreach ($this->user->userInfo as $uid => $userInfo) {
        if ($userInfo->address_type === 'BT') {
          $this->userBtUid = $uid;
          break;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getCustomer() {
    $result = array();

    $this->addIfSetAndNotEmpty($result, 'contactyourid', $this->order['details']['BT'], 'virtuemart_user_id');
    $this->addIfSetAndNotEmpty($result, 'companyname1', $this->order['details']['BT'], 'company');
    $result['companyname2'] = '';
    if (!empty($result['companyname1']) && !empty($this->userBtUid)) {
      // @todo: there's also a (paid) EU VAT checker extension that probably does not use the field 'tax_exemption_number'.
      $this->addIfSetAndNotEmpty($result, 'vatnumber', $this->user->userInfo[$this->userBtUid], 'tax_exemption_number');
    }
    $result['fullname'] = $this->order['details']['BT']->last_name;
    if (!empty($this->order['details']['BT']->middle_name)) {
      $result['fullname'] = $this->order['details']['BT']->middle_name . ' ' . $result['fullname'];
    }
    if (!empty($this->order['details']['BT']->first_name)) {
      $result['fullname'] = $this->order['details']['BT']->first_name . ' ' . $result['fullname'];
    }
    $this->addIfSetAndNotEmpty($result, 'salutation', $this->order['details']['BT'], 'title');
    $this->addIfSetAndNotEmpty($result, 'address1', $this->order['details']['BT'], 'address_1');
    $this->addIfSetAndNotEmpty($result, 'address2', $this->order['details']['BT'], 'address_2');
    $this->addIfSetAndNotEmpty($result, 'postalcode', $this->order['details']['BT'], 'zip');
    $this->addIfSetAndNotEmpty($result, 'city', $this->order['details']['BT'], 'city');
    if (!empty($this->order['details']['BT']->virtuemart_country_id)) {
      /** @var \VirtueMartModelCountry $countryModel */
      $countryModel = VmModel::getModel('country');
      $country = $countryModel->getData($this->order['details']['BT']->virtuemart_country_id);
      $this->addIfSetAndNotEmpty($result, 'countrycode', $country, 'country_2_code');
    }
    $this->addIfSetAndNotEmpty($result, 'telephone', $this->order['details']['BT'], 'phone_2');
    $this->addIfSetAndNotEmpty($result, 'telephone', $this->order['details']['BT'], 'phone_1');
    $this->addIfSetAndNotEmpty($result, 'fax', $this->order['details']['BT'], 'fax');
    $this->addIfSetAndNotEmpty($result, 'email', $this->order['details']['BT'], 'email');

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getProperty($property) {
    $value = @$this->order['details']['BT']->$property;
    if (empty($value)) {
      $value = @$this->user->$property;
    }
    if (empty($value)) {
      $value = @$this->user->userInfo[$this->userBtUid]->$property;
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  protected function getInvoiceNumber($invoiceNumberSource) {
    $result = $this->invoiceSource->getReference();
    if ($invoiceNumberSource == ConfigInterface::InvoiceNrSource_ShopInvoice && !empty($this->shopInvoice['invoice_number'])) {
      $result = $this->shopInvoice['invoice_number'];
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getInvoiceDate($dateToUse) {
    $result = date('Y-m-d', strtotime($this->order['details']['BT']->created_on));
    if ($dateToUse == ConfigInterface::InvoiceDate_InvoiceCreate && !empty($this->shopInvoice['created_on'])) {
      $result = date('Y-m-d', strtotime($this->shopInvoice['created_on']));
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPaymentState() {
    $order = $this->invoiceSource->getSource();
    return in_array($order['details']['BT']->order_status, $this->getPaidStates())
      ? InvoiceConfigInterface::PaymentStatus_Paid
      : InvoiceConfigInterface::PaymentStatus_Due;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPaymentDate() {
    $date = NULL;
    $previousStatus = '';
    foreach ($this->order['history'] as $orderHistory) {
      if (in_array($orderHistory->order_status_code, $this->getPaidStates()) && !in_array($previousStatus, $this->getPaidStates())) {
        $date = $orderHistory->created_on;
      }
      $previousStatus = $orderHistory->order_status_code;
    }
    return $date ? date('Y-m-d', strtotime($date)) : $date;
  }

  /**
   * Returns a list of order states that indicate that the order has been paid.
   *
   * @return array
   */
  protected function getPaidStates() {
    return array('C', 'S', 'R');
  }

  /**
   * {@inheritdoc}
   *
   * This override provides the values meta-invoiceamountinc and
   * meta-invoicevatamount as they may be needed by the Completor.
   */
  protected function getInvoiceTotals() {
    return array(
      'meta-invoiceamountinc' => $this->order['details']['BT']->order_total,
      'meta-invoicevatamount' => $this->order['details']['BT']->order_billTaxAmount,
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getItemLines() {
    $result = array_map(array($this, 'getItemLine'), $this->order['items']);
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
    // @todo: next release: can margin scheme be used in VM?
    $productPriceEx = (float) $item->product_discountedPriceWithoutTax;
    $productPriceInc = (float) $item->product_final_price;
    $productVat = (float) $item->product_tax;

    $calcRule = $this->getCalcRule('VatTax', $item->virtuemart_order_item_id);
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
    $result = array();
    // We are checking on empty, assuming that a null value will be used to
    // indicate no shipping at all (downloadable product) and that free shipping
    // will be represented as the string '0.00' which is not considered empty.
    if (!empty($this->order['details']['BT']->order_shipment)) {
      $shippingEx = (float) $this->order['details']['BT']->order_shipment;
      $shippingVat = (float) $this->order['details']['BT']->order_shipment_tax;

      $calcRule = $this->getCalcRule('shipment');
      if (!empty($calcRule->calc_value)) {
        $vatInfo = array(
          'vatrate' => (float) $calcRule->calc_value,
          'meta-vatrate-source' => static::VatRateSource_Exact,
        );
      }
      else {
        $vatInfo = $this->getVatRangeTags($shippingVat, $shippingEx, 0.0001, 0.01);
      }
      $result = array(
          'product' => $this->t('shipping_costs'),
          'unitprice' => $shippingEx,
          'quantity' => 1,
          'vatamount' => $shippingVat,
        ) + $vatInfo;
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscountLines() {
    $result = array();

    // We do have several discount related fields in the order details:
    // - order_billDiscountAmount
    // - order_discountAmount
    // - coupon_discount
    // - order_discount
    // However, these fields seem to be totals based on applied non-tax
    // calculation rules. So it is better to add a line per calc rule with a
    // negative amount: this gives us descriptions of the discounts as well.
    $result = array_merge($result, array_map(array($this, 'getCalcRuleDiscountLine'),
      array_filter($this->order['calc_rules'], array($this, 'isDiscountCalcRule'))));

    // Coupon codes are not stored in a calc rules, so handle them separately.
    if (!Number::isZero($this->order['details']['BT']->coupon_discount)) {
      $result[] = $this->getCouponCodeDiscountLine();
    }

    return $result;
  }

  /**
   * Returns whether the calculation rule is a discount rule.
   *
   * @param \stdClass $calcRule
   *
   * @return bool
   *   True if the calculation rule is a discount rule.
   */
  protected function isDiscountCalcRule(stdClass $calcRule) {
    return $calcRule->calc_amount < 0.0
    && !in_array($calcRule->calc_kind, array('VatTax', 'shipment', 'payment'));
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
  protected function getCalcRuleDiscountLine(stdClass $calcRule) {
    $result = array(
      'product' => $calcRule->calc_rule_name,
      'unitprice' => NULL,
      'unitpriceinc' => $calcRule->calc_amount,
      'vatrate' => NULL,
      'quantity' => 1,
      'meta-vatrate-source' => static::VatRateSource_Strategy,
      'meta-strategy-split' => TRUE,
    );

    return $result;
  }

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
    $result = array(
      'product' => $this->t('coupon_code') . ' ' . $this->order['details']['BT']->coupon_code,
      'unitprice' => NULL,
      'unitpriceinc' => $this->order['details']['BT']->coupon_discount,
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
    if (!empty($this->order['details']['BT']->order_payment)) {
      $paymentEx = (float) $this->order['details']['BT']->order_payment;
      if (!Number::isZero($paymentEx)) {
        $paymentVat = (float) $this->order['details']['BT']->order_payment_tax;

        $calcRule = $this->getCalcRule('payment');
        if (!empty($calcRule->calc_value)) {
          $vatInfo = array(
            'vatrate' => (float) $calcRule->calc_value,
            'meta-vatrate-source' => static::VatRateSource_Exact,
          );
        }
        else {
          $vatInfo = $this->getVatRangeTags($paymentVat, $paymentEx, 0.0001, 0.01);
        }

        $result = array(
            'product' => $this->t('payment_costs'),
            'unitprice' => $paymentEx,
            'quantity' => 1,
            'vatamount' => $paymentVat,
          ) + $vatInfo;
      }
    }
    return $result;
  }

  /**
   * Returns a calculation rule identified by the given reference
   *
   * @param string $calcKind
   *   The value for the kind of calc rule.
   * @param int $orderItemId
   *   The value for the order item id, or 0 for special lines.
   *
   * @return null|object
   *   The (1st) calculation rule for the given reference, or null if none
   *   found.
   */
  protected function getCalcRule($calcKind, $orderItemId = 0) {
    foreach ($this->order['calc_rules'] as $calcRule) {
      if ($calcRule->calc_kind == $calcKind) {
        if (empty($orderItemId) || $calcRule->virtuemart_order_item_id == $orderItemId) {
          return $calcRule;
        }
      }
    }
    return NULL;
  }

}
