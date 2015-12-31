<?php
namespace Siel\Acumulus\PrestaShop\Invoice;

use Address;
use Carrier;
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
 * Allows to create arrays in the Acumulus invoice structure from a PrestaShop
 * order or order slip.
 *
 * Notes:
 * - If needed, PrestaShop allows us to get tax rates by querying the tax table
 *   because as soon as an existing tax rate gets updated it will get a new id,
 *   so old order details still point to a tax record with the tax rate as was
 *   used at the moment the order was placed.
 * - Fixed in 1.6.1.1: bug in partial refund, not executed the hook
 *   actionOrderSlipAdd #PSCSX-6287. So before 1.6.1.1, partial refunds will not
 *   be automatically sent to Acumulus.
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

  /**
   * {@inheritdoc}
   */
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
    $result = substr($this->invoiceSource->getSource()->date_add, 0, strlen('2000-01-01'));
    // Invoice_date is filled with "0000-00-00 00:00:00", so use invoice
    // number instead to check for existence of the invoice.
    if ($dateToUse == ShopConfigInterface::InvoiceDate_InvoiceCreate && $this->invoiceSource->getType() === Source::Order && !empty($this->order->invoice_number)) {
      $result = substr($this->order->invoice_date, 0, strlen('2000-01-01'));
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPaymentState() {
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
      /** @noinspection PhpUndefinedFieldInspection */
      $amount = $this->creditSlip->total_products_tax_excl
        + $this->creditSlip->total_shipping_tax_excl;
      /** @noinspection PhpUndefinedFieldInspection */
      $amountInc = $this->creditSlip->total_products_tax_incl
        + $this->creditSlip->total_shipping_tax_incl;
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
      // Note: getOrderDetailTaxes() is new in 1.6.1.0.
      $lines = method_exists($this->order, 'getOrderDetailTaxes')
        ? $this->mergeProductLines($this->order->getProductsDetail(), $this->order->getOrderDetailTaxes())
        : $this->order->getProductsDetail();
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
   * @inheritDoc
   *
   * This override corrects a credit invoice if the amount does not match the
   * sum of the lines so far. This can happen if an amount was entered manually,
   * or if discount(s) applied during sale were subtracted from the credit
   * amount but we could not find which discounts this were.
   */
  protected function getInvoiceLines() {
    $result = parent::getInvoiceLines();

    if ($this->invoiceSource->getType() === Source::CreditNote) {
      // Only Credit notes can have a manual line. They get one if the total
      // amount does not match the sum of the lines added so far.
      // Notes:
      // - amount is excl vat if not manually entered.
      // - amount is incl vat if manually entered (assuming administrators enter
      //   amounts incl tax, and this is what gets listed on the credit PDF.
      // - shipping_cost_amount is excl vat.
      // So this is never going  to work!!!
      // @todo: can we get a tax amount/rate over the manually entered refund?
      $amount = -$this->creditSlip->amount - $this->creditSlip->shipping_cost_amount;
      $linesAmount = $this->getLinesTotal($result);
      if (!Number::floatsAreEqual($amount, $linesAmount)) {
        $line = array(
          'product' => $this->t('refund_adjustment'),
          'unitprice' => $amount - $linesAmount,
          'unitpriceinc' => $amount - $linesAmount,
          'quantity' => 1,
          'vatrate' => 0,
          'meta-vatrate-source' => Creator::VatRateSource_Exact,
          'meta-line-type' => static::LineType_Manual,
        );
        $result[] = $line;
      }
    }

    return $result;
  }

  /**
   * Returns 1 item line, both for an order or credit slip.
   *
   * @param array $item
   *   An array of an OrderDetail line combined with a tax detail line OR
   *   an array with an OrderSlipDetail line.
   *
   * @return array
   */
  protected function getItemLine(array $item) {
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
    // These 3 fields are only defined for orders and were not filled in or
    // before PS1.6.1.1. So, we have to check if the fields are available.
    if (isset($item['rate'])) {
      $result['vatamount'] = $item['unit_amount'];
      $result['meta-linevatamount'] = $item['total_amount'];
      $result['vatrate'] = $item['rate'];
      $result['meta-vatrate-source'] = Creator::VatRateSource_Exact;
    }
    else {
      $result += $this->getVatRangeTags(-$item['unit_price_tax_incl'] + $item['unit_price_tax_excl'], -$item['unit_price_tax_excl'], 0.02);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getShippingLine() {
    $sign = $this->getSign();
    $shippingEx = $sign * $this->invoiceSource->getSource()->total_shipping_tax_excl;
    $shippingInc = $sign * $this->invoiceSource->getSource()->total_shipping_tax_incl;
    $shippingVat = $shippingInc - $shippingEx;

    if (!Number::isZero($shippingInc)) {
      $result = array(
          'product' => $this->t('shipping_costs'),
          'unitprice' => $shippingEx,
          'unitpriceinc' => $shippingInc,
          'quantity' => 1,
        ) + $this->getVatRangeTags($shippingVat, $shippingEx, 0.02);
    }
    else {
      $carrier = new Carrier($this->order->id_carrier);
      $description = $carrier->id_reference == 1 ? $this->t('pickup') : $this->t('free_shipping');
      $result = array(
        'product' => $description,
        'unitprice' => 0,
        'unitpriceinc' => 0,
        'quantity' => 1,
        'vatamount' => 0,
        'vatrate' => NULL,
        'meta-vatrate-source' => Creator::VatRateSource_Completor,
      );
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   *
   * This override returns can return an invoice line for orders. Credit slips
   * cannot have a wrapping line.
   */
  protected function getGiftWrappingLine() {
    $result = array();
    if ($this->invoiceSource->getType() === Source::Order && $this->order->gift && !Number::isZero($this->order->total_wrapping_tax_incl)) {
      $wrappingEx = $this->order->total_wrapping_tax_excl;
      $wrappingInc = $this->order->total_wrapping_tax_incl;
      $wrappingVat = $wrappingInc - $wrappingEx;
      $result = array(
          'product' => $this->t('gift_wrapping'),
          'unitprice' => $wrappingEx,
          'unitpriceinc' => $wrappingInc,
          'quantity' => 1,
        ) + $this->getVatRangeTags($wrappingVat, $wrappingEx, 0.02);
    }
    return $result;
  }

  /**
   * In a Prestashop order the discount lines are specified in Order cart rules
   *
   * @return array[]
   */
  protected function getDiscountLinesOrder() {
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
    $sign = $this->getSign();
    $discountInc = -$sign * $line['value'];
    $discountEx = -$sign * $line['value_tax_excl'];
    $discountVat = $discountInc - $discountEx;
    return array(
      'itemnumber' => $line['id_cart_rule'],
      'product' => $this->t('discount_code') . ' ' . $line['name'],
      'unitprice' => $discountEx,
      'unitpriceinc' => $discountInc,
      'quantity' => 1,
      // If no match is found, this line may be split.
      'meta-strategy-split' => TRUE,
      'meta-lineprice' => $discountEx,
      'meta-linepriceinc' => $discountInc,
      // Assuming that the fixed discount amount was entered:
      // - including VAT, the precision would be 0.01, 0.01.
      // - excluding VAT, the precision would be 0.01, 0
      // However, for a % discount, it will be: 0.02, 0.01, so use this.
    ) + $this->getVatRangeTags($discountVat, $discountEx, 0.02);
  }

  /**
   * In a Prestashop credit slip, the discounts are not visible anymore, but
   * can be computed by looking at the difference between the value of
   * total_products_tax_incl and the sum of the OrderSlipDetail amounts.
   *
   * @return array[]
   */
  protected function getDiscountLinesCreditNote() {
    // Get total amount credited.
    /** @noinspection PhpUndefinedFieldInspection */
    $creditSlipAmountInc = $this->creditSlip->total_products_tax_incl;

    // Get sum of product lines.
    $lines = $this->creditSlip->getOrdersSlipProducts($this->invoiceSource->getId(), $this->order);
    $detailsAmountInc = array_reduce($lines, function ($sum, $item) {
      $sum += $item['total_price_tax_incl'];
      return $sum;
    }, 0.0);

    // We assume that if the total is smaller than the sum, a discount given on
    // the original order has now been subtracted from the amount credited.
    if (!Number::floatsAreEqual($creditSlipAmountInc, $detailsAmountInc, 0.05)
      && $creditSlipAmountInc < $detailsAmountInc
    ) {
      // PS Error: total_products_tax_excl is not adjusted (whereas
      // total_products_tax_incl is) when a discount is subtracted from the
      // amount to be credited.
      // So we cannot calculate the discount ex VAT ourselves.
      // What we can try is the following: Get the order cart rules to see if
      // 1 or all of those match the discount amount here.
      $discountAmountInc = $detailsAmountInc - $creditSlipAmountInc;
      $totalOrderDiscount = 0.0;
      // Note: The sign of then entries in $orderDiscounts will be correct.
      $orderDiscounts = $this->getDiscountLinesOrder();

      foreach ($orderDiscounts as $key => $orderDiscount) {
        if (Number::floatsAreEqual($orderDiscount['unitpriceinc'], $discountAmountInc)) {
          // Return this single line.
          $from = $to = $key;
          break;
        }
        $totalOrderDiscount += $orderDiscount['unitpriceinc'];
        if (Number::floatsAreEqual($totalOrderDiscount, $discountAmountInc)) {
          // Return all lines up to here.
          $from = 0;
          $to = $key;
          break;
        }
      }

      if (isset($from) && isset($to)) {
        return array_slice($orderDiscounts, $from, $to - $from + 1);
      }
      //else {
      // We could not match a discount with the difference between the total
      // amount credited and the sum of the products returned. A manual line
      // will correct the invoice.
      //}
    }
    return array();
  }

}
