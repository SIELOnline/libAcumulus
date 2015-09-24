<?php
namespace Siel\Acumulus\PrestaShop\Invoice;

use Address;
use Configuration;
use Country;
use Customer;
use Order;
use OrderPayment;
use OrderSlip;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\ConfigInterface;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use Siel\Acumulus\Shop\ConfigInterface as ShopConfigInterface;

/**
 * Allows to create arrays in the Acumulus invoice structure from a WordPress
 * order or order refund.
 */
class Creator extends BaseCreator {

  // More specifically typed property.
  /** @var Source */
  protected $invoiceSource;

  /** @var Order */
  protected $order;

  /** @var OrderSlip */
  protected $creditSlip;

  /**
   * {@inheritdoc}
   *
   * This override also initializes WooCommerce specific properties related to
   * the source.
   */
  protected function setInvoiceSource($invoiceSource) {
    parent::setInvoiceSource($invoiceSource);
    switch ($this->invoiceSource->getType()) {
      case Source::Order:
        $this->order = $this->invoiceSource->getSource();
        break;
      case Source::CreditNote:
        $this->creditSlip = $this->invoiceSource->getSource();
        $this->order = new Order($this->creditSlip->id_order);
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getCustomer() {
    // @todo: test this for credit slips.
    $customer = new Customer($this->invoiceSource->getSource()->id_customer);
    $invoiceAddress = new Address($this->order->id_address_invoice);

    $result = array();

    $this->addIfNotEmpty($result, 'contactyourid', $customer->id);
    $this->addEmpty($result, 'companyname1', $invoiceAddress->company);
    $result['companyname2'] = '';
    $result['fullname'] = $invoiceAddress->firstname . ' ' . $invoiceAddress->lastname;
    $this->addEmpty($result, 'address1', $invoiceAddress->address1);
    $this->addEmpty($result, 'address2', $invoiceAddress->address2);
    $this->addEmpty($result, 'postalcode', $invoiceAddress->postcode);
    $this->addEmpty($result, 'city', $invoiceAddress->city);
    if ($invoiceAddress->id_country) {
      $result['countrycode'] = Country::getIsoById($invoiceAddress->id_country);
    }
    $this->addIfNotEmpty($result, 'vatnumber', $invoiceAddress->vat_number);
    // Add either mobile or phone number to 'telephone'.
    $this->addIfNotEmpty($result, 'telephone', $invoiceAddress->phone);
    $this->addIfNotEmpty($result, 'telephone', $invoiceAddress->phone_mobile);
    $result['email'] = $customer->email;

    return $result;
  }

  protected function searchProperty($property) {
    $invoiceAddress = new Address($this->order->id_address_invoice);
    $value = $this->getProperty($property, $invoiceAddress);
    if (empty($value)) {
      $customer = new Customer($this->invoiceSource->getSource()->id_customer);
      $value = $this->getProperty($property, $customer);
    }
    if (empty($value)) {
      $value = parent::searchProperty($property);
    }
    return $value;
  }


  /**
   * {@inheritdoc}
   */
  protected function getInvoiceNumber($invoiceNumberSource) {
    // @todo: test this for credit slips
    $result = $this->invoiceSource->getReference();
    if ($invoiceNumberSource === ShopConfigInterface::InvoiceNrSource_ShopInvoice && $this->invoiceSource->getType() === Source::Order && !empty($this->order->invoice_number)) {
      $result = Configuration::get('PS_INVOICE_PREFIX', (int) $this->order->id_lang, NULL, $this->order->id_shop) . sprintf('%06d', $this->order->invoice_number);
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getInvoiceDate($dateToUse) {
    // @todo: test this for credit slips
    $result = substr($this->invoiceSource->getSource()->date_add, 0, strlen('2000-01-01'));
    // Invoice_date is filled with "0000-00-00 00:00:00", so use invoice
    // number instead to check for empty.
    if ($dateToUse == ShopConfigInterface::InvoiceDate_InvoiceCreate && $this->invoiceSource->getType() === Source::Order && !empty($this->order->invoice_number)) {
      $result = substr($this->order->invoice_date, 0, strlen('2000-01-01'));
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPaymentState() {
    // @todo: test this for credit slips
    // Assumption: credit slips are always in a paid state.
    if (($this->invoiceSource->getType() === Source::Order && $this->order->hasBeenPaid()) || $this->invoiceSource->getType() === Source::CreditNote) {
      $result = ConfigInterface::PaymentStatus_Paid;
    }
    else {
      $result = ConfigInterface::PaymentStatus_Due;
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPaymentDate() {
    // @todo: test this for credit slips
    if ($this->invoiceSource->getType() === Source::Order) {
      $paymentDate = NULL;
      foreach ($this->order->getOrderPaymentCollection() as $payment) {
        /** @var OrderPayment $payment */
        if ($payment->date_add && (!$paymentDate || $payment->date_add > $paymentDate)) {
          $paymentDate = $payment->date_add;
        }
      }
    }
    else {
      // Assumption: last modified date is date of actual reimbursement.
      $paymentDate = $this->creditSlip->date_upd;
    }

    $result = $paymentDate ? substr($paymentDate, 0, strlen('2000-01-01')) : NULL;
    return $result;
  }

  /**
   * {@inheritdoc}
   *
   * This override provides the values meta-invoiceamountinc and
   * meta-invoiceamount.
   */
  protected function getInvoiceTotals() {
    // @todo: test this for credit slips
    $sign = $this->getSign();
    if ($this->invoiceSource->getType() === Source::Order) {
      $amount = $this->order->getTotalProductsWithoutTaxes()
        + $this->order->total_shipping_tax_excl
        + $this->order->total_wrapping_tax_excl
        - $this->order->total_discounts_tax_excl;
      $amountInc = $this->order->getTotalProductsWithTaxes()
        + $this->order->total_shipping_tax_incl
        + $this->order->total_wrapping_tax_incl
        - $this->order->total_discounts_tax_incl;
    }
    else {
      $amount = $this->creditSlip->getFieldByLang('total_products_tax_excl')
        + $this->creditSlip->getFieldByLang('total_shipping_tax_excl');
      $amountInc = $this->creditSlip->getFieldByLang('total_products_tax_incl')
        + $this->creditSlip->getFieldByLang('total_shipping_tax_incl');
    }

    return array(
      'meta-invoiceamountinc' => $sign * $amountInc,
      'meta-invoiceamount' => $sign * $amount,
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getItemLines() {
    $result = array();
    if ($this->invoiceSource->getType() === Source::Order) {
      $lines = $this->mergeProductLines($this->order->getProductsDetail(), $this->order->getOrderDetailTaxes());
    }
    else {
      $lines = $this->creditSlip->getOrdersSlipProducts($this->invoiceSource->getId(), $this->order);
    }

    foreach ($lines as $line) {
      $result[] = $this->getItemLine($line);
    }
    return $result;
  }

  /**
   * Merges the product and tax details arrays.
   *
   * @param array $productLines
   * @param array $taxLines
   *
   * @return array
   */
  public function mergeProductLines(array $productLines, array $taxLines) {
    $result = array();
    // Key the product lines on id_order_detail, so we can easily add the tax
    // lines in the 2nd loop.
    foreach ($productLines as $productLine) {
      $result[$productLine['id_order_detail']] = $productLine;
    }
    // Add the tax lines without overwriting existing entries (though in a
    // consistent db the same keys should contain the same values).
    foreach ($taxLines as $taxLine) {
      $result[$taxLine['id_order_detail']] += $taxLine;
    }
    return $result;
  }

  /**
   * Returns 1 item line, both for an order or credit slip.
   *
   * @param array $item
   *   An array of an OrderDetail line combined with a tax detail line.
   *   @todo: describe orderslip detail
   *
   * @return array
   */
  protected function getItemLine(array $item) {
    // @todo: test this for credit slips
    $result = array();
    $sign = $this->getSign();

    $this->addIfNotEmpty($result, 'itemnumber', $item['product_upc']);
    $this->addIfNotEmpty($result, 'itemnumber', $item['product_ean13']);
    $this->addIfNotEmpty($result, 'itemnumber', $item['product_supplier_reference']);
    $this->addIfNotEmpty($result, 'itemnumber', $item['product_reference']);
    $result['product'] = $item['product_name'];

    // Prestashop does not support the margin scheme. So in a standard install
    // this method will always return false. But if this method happens to
    // return true anyway (customisation, hook), the costprice will trigger
    // vattype = 5 for Acumulus.
    if ($this->allowMarginScheme() && !empty($item['purchase_supplier_price'])) {
      // Margin scheme:
      // - Do not put VAT on invoice: send price incl VAT as unitprice.
      // - But still send the VAT rate to Acumulus.
      $result['unitprice'] = $sign * $item['unit_price_tax_incl'];
      // Costprice > 0 is the trigger for Acumulus to use the margin scheme.
      $result['costprice'] = $sign * $item['purchase_supplier_price'];
    }
    else {
      // Unit price is without VAT, so use product_price, not product_price_wt.
      $result['unitprice'] = $sign * $item['unit_price_tax_excl'];
      $result['unitpriceinc'] = $sign * $item['unit_price_tax_incl'];
      $result['meta-lineprice'] = $sign * $item['total_price_tax_excl'];
      $result['meta-linepriceinc'] = $sign * $item['total_price_tax_incl'];
    }
    $result['quantity'] = $item['product_quantity'];
    $result['vatamount'] = $item['unit_amount'];
    $result['meta-linevatamount'] = $item['total_amount'];
    $result['vatrate'] = $item['rate'];
    $result['meta-vatrate-source'] = Creator::VatRateSource_Exact;

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getShippingLine() {
    // @todo: test this for credit slips
    $result = array();

    // @todo: can we add a free shipping line? (but distinguish from shop pick up (probably id_carrier = 1, or better id_carrier with name = "0"
    if (!Number::isZero($this->invoiceSource->getSource()->total_shipping_tax_incl)) {
      $shippingEx = $this->getSign() * $this->invoiceSource->getSource()->total_shipping_tax_excl;
      $shippingInc = $this->getSign() * $this->invoiceSource->getSource()->total_shipping_tax_incl;
      $shippingVat = $shippingInc - $shippingEx;
      $result = array(
          'product' => $this->t('shipping_costs'),
          'unitprice' => $shippingEx,
          'unitpriceinc' => $shippingInc,
          'quantity' => 1,
        ) + $this->getVatRangeTags($shippingVat, $shippingEx, 0.02);
    }

    return $result;
  }

  /**
   * In a Prestashop order the discount lines are specified in Order cart rules
   *
   * @return array[]
   */
  protected function getDiscountLinesOrder() {
    // @todo: test this
    $result = array();

    foreach ($this->order->getCartRules() as $line) {
      $result[] = $this->getDiscountLineOrder($line);
    }

    return $result;
  }

  /**
   * In a Prestashop order the discount lines are specified in Order cart rules
   * that have, a.o, the following fields:
   * - value: total amount inc VAT
   * - value_tax_excl: total amount ex VAT
   *
   * @param array $line
   *
   * @return array
   */
  protected function getDiscountLineOrder(array $line) {
    // @todo: test this for credit slips
    $discountInc = -$line['value'];
    $discountEx = -$line['value_tax_excl'];
    $discountVat = $discountInc - $discountEx;
    return array(
      'itemnumber' => $line['id_cart_rule'],
      'product' => $this->t('discount_code') . ' ' . $line['name'],
      'unitprice' => $discountEx,
      'unitpriceinc' => $discountInc,
      'quantity' => 1,
      // Assuming that the fixed discount amount was entered:
      // - including VAT, the precision would be 0.01, 0.01.
      // - excluding VAT, the precision would be 0.01, 0
      // However, for a % discount, it will be: 0.02, 0.01, so use this.
    ) + $this->getVatRangeTags($discountVat, $discountEx, 0.02);
  }

  /**
   * In a Prestashop credit slip, the discounts are not visible anymore.
   *
   * @return array[]
   */
  protected function getDiscountLinesCreditNote() {
    return array();
  }

  /**
   * {@inheritdoc}
   *
   * This override returns an empty array: WooCommerce does not know gift
   * wrapping lines, i.e. it does not define a fee line specifically for this
   * fee type.
   */
  protected function getGiftWrappingLine() {
    // @todo: test this for credit slips
    $result = array();
    if ($this->invoiceSource->getType() === Source::Order && $this->order->gift && !Number::isZero($this->order->total_wrapping_tax_incl)) {
      $wrappingEx = $this->order->total_wrapping_tax_excl;
      $wrappingInc = $this->order->total_wrapping_tax_incl;
      $wrappingVat = $wrappingInc - $wrappingEx;
      $result = array(
        'product' => $this->t('gift_wrapping'),
        'unitprice' => $wrappingEx,
        'quantity' => 1,
        ) + $this->getVatRangeTags($wrappingVat, $wrappingEx, 0.02);
    }
    return $result;
  }

}
