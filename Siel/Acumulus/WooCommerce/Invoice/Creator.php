<?php
namespace Siel\Acumulus\WooCommerce\Invoice;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\ConfigInterface;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use WC_Abstract_Order;
use WC_Coupon;
use WC_Order_Refund;

/**
 * Allows to create arrays in the Acumulus invoice structure from a WordPress
 * order or order refund.
 */
class Creator extends BaseCreator {

  // More specifically typed property.
  /** @var Source */
  protected $source;

  /** @var WC_Abstract_Order */
  protected $order;

  /** @var WC_Order_Refund */
  protected $creditOrder;

  /**
   * {@inheritdoc}
   *
   * This override also initializes WooCommerce specific properties related to
   * the source.
   */
  protected function setSource($source) {
    parent::setSource($source);
    switch ($this->source->getType()) {
      case Source::Order:
        $this->order = $this->source->getSource();
        break;
      case Source::CreditNote:
        $this->order = $this->source->getSource();
        $this->creditOrder = $this->order->getOrder();
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getCustomer() {
    $result = array();

    /** @var WC_Abstract_Order $order */
    $order = $this->order;

    $this->addIfNotEmpty($result, 'contactyourid', $order->customer_user);
    $this->addEmpty($result, 'companyname1', $order->billing_company);
    $result['companyname2'] = '';
    $result['fullname'] = $order->billing_first_name . ' ' . $order->billing_last_name;
    $this->addEmpty($result, 'address1', $order->billing_address_1);
    $this->addEmpty($result, 'address2', $order->billing_address_2);
    $this->addEmpty($result, 'postalcode', $order->billing_postcode);
    $this->addEmpty($result, 'city', $order->billing_city);
    if (isset($order->billing_country)) {
      $result['countrycode'] = $order->billing_country;
    }
    $this->addIfNotEmpty($result, 'vatnumber', get_post_meta($order->id, 'VAT Number', TRUE));
    $this->addIfNotEmpty($result, 'telephone', $order->billing_phone);
    $result['email'] = $order->billing_email;

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getInvoiceNumber($invoiceNumberSource) {
    $result = $this->source->getReference();
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getInvoiceDate($dateToUse) {
    // createdAt returns yyyy-mm-dd hh:mm:ss, take date part.
    $result = date('Y-m-d', strtotime($this->order->order_date));
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPaymentState() {
    if (empty($this->order->paid_date) || $this->order->needs_payment()) {
      $result = ConfigInterface::PaymentStatus_Due;
    }
    else {
      $result = ConfigInterface::PaymentStatus_Paid;
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPaymentDate() {
    return date('Y-m-d', strtotime($this->order->paid_date));
  }

  /**
   * {@inheritdoc}
   *
   * This override provides the values meta-invoiceamountinc and
   * meta-invoicevatamount.
   */
  protected function getInvoiceTotals() {
    // @todo: check sign.
    // @todo: check with display_total_ex_tax/display_cart_ex_tax = true
    // @todo: check with price_include_tax = false (wc_prices_include_tax())
    $sign = $this->getSign();
    return array(
      'meta-invoiceamountinc' => $sign * $this->order->get_total(),
      'meta-invoicevatamount' => $sign * $this->order->get_total_tax(),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getItemLines() {
    $result = array();
    foreach ($this->order->get_items() as $line) {
      $result[] = $this->getItemLine($line, $this->order);
    }
    return $result;
  }

  /**
   * Returns 1 item line.
   *
   * @param array $item
   * @param WC_Abstract_Order $order
   *
   * @return array
   */
  protected function getItemLine(array $item, WC_Abstract_Order $order) {
    $result = array();

    $product = wc_get_product($item['variation_id'] ? $item['variation_id'] : $item['product_id']);
    // get_item_total returns cost per item after discount, ex vat (2nd param).
    // @todo: check/add sign for refunds
    // Precision: one of the prices is entered by the administrator and thus can
    // be considered exact. The computed one is not rounded, so we can assume
    // a very high precision for all values here,
    $productPriceEx = $order->get_item_total($item, FALSE, FALSE);
    $productPriceInc = $order->get_item_total($item, TRUE, FALSE);
    // get_item_tax returns tax per item after discount.
    $productVat = $order->get_item_tax($item, FALSE);

    $this->addIfNotEmpty($result, 'itemnumber', $product->get_sku());
    $result['product'] = $item['name'];

    // WooCommerce does not support the margin scheme. So in a standard install
    // this method will always return false. But if this method happens to
    // return true anyway (customisation, hook), the costprice tag will trigger
    // vattype = 5 for Acumulus.
    if ($this->allowMarginScheme() && !empty($item['cost_price'])) {
      // Margin scheme:
      // - Do not put VAT on invoice: send price incl VAT as unitprice.
      // - But still send the VAT rate to Acumulus.
      // Costprice > 0 is the trigger for Acumulus to use the margin scheme.
      $result += array(
        'unitprice' => $productPriceInc,
        'costprice' => $item['cost_price'],
      );
    }
    else {
      $result += array(
        'unitprice' => $productPriceEx,
        'unitpriceinc' => $productPriceInc,
        'vatamount' => $productVat,
      );
    }

    $result['quantity'] = $item['qty'];
    $result += $this->getVatRangeTags($productVat, $productPriceEx, 0.0001, 0.0001);

    return $result;
  }

  /**
   * @inheritdoc
   *
   * WooCommerce has general fee lines, so we have to override this method to
   * add these general fees (type unknown to us)
   */
  protected function getFeeLines() {
    // @todo: check for refunds.
    $result = parent::getFeeLines();

    foreach ($this->order->get_fees() as $feeLine) {
      $line = $this->getFeeLine($feeLine);
      $line['meta-line-type'] = static::LineType_Other;
      $result[] = $line;
    }
    return $result;
  }

  /**
   * @param array $line
   *
   * @return array
   */
  protected function getFeeLine(array $line) {
    $feeEx = $line['line_total'];
    $feeVat = $line['line_tax'];

    // @todo: check precision
    $result = array(
        'itemnumber' => $this->t('fee_code'),
        'product' => $this->t($line['name']),
        'unitprice' => $feeEx,
        'quantity' => 1,
        'vatamount' => $feeVat,
      ) + $this->getVatRangeTags($feeVat, $feeEx);

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getShippingLine() {
    // @todo: check for refunds.
    // Precision: shipping costs are entered ex VAT, so that may be rounded to
    // the cent by the administrator. The computed costs inc VAT is rounded to
    // the cent as well, so both
    $shippingEx = $this->order->get_total_shipping();
    $shippingVat = $this->order->get_shipping_tax();
    if (!Number::isZero($shippingEx)) {
      $description = $this->t('shipping_costs');
    }
    else {
      $description = $this->t('free_shipping');
    }

    $result = array(
        'product' => $description,
        'unitprice' => $shippingEx,
        'quantity' => 1,
        'vatamount' => $shippingVat,
      ) + $this->getVatRangeTags($shippingVat, $shippingEx);

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscountLines() {
    // @todo: check for refunds.
    $result = array();

    // Add a line for all coupons applied.
    $usedCoupons = $this->order->get_used_coupons();
    foreach ($usedCoupons as $code) {
      $coupon = new WC_Coupon($code);
      $result[] = $this->getDiscountLine($this->order, $coupon);
    }

    return $result;
  }

  /**
   * Returns 1 order discount line for 1 coupon usage.
   *
   * In woocommerce, discounts are implemented with coupons. Multiple coupons
   * can be used per order. Coupons can:
   * - have a fixed amount or a percentage.
   * - be applied to the whole cart or only be used for a set of products.
   *
   * Hooray:
   * As of WooCommerce 2.3, coupons can no longer be set as "apply after tax":
   * https://woocommerce.wordpress.com/2014/12/12/upcoming-coupon-changes-in-woocommerce-2-3/
   * WC_Coupon::apply_before_tax() now always returns true (and thus might be
   * deprecated and removed in the future): do no longer use.
   *
   * @param WC_Abstract_Order $order
   * @param WC_Coupon $coupon
   *
   * @return array
   */
  protected function getDiscountLine(WC_Abstract_Order $order, WC_Coupon $coupon) {
    //@todo: test amount used < coupon amount
    $discountAmount = $order->get_total_discount();

    // Get a description for the value of this coupon.
    if (in_array($coupon->discount_type, array('fixed_product', 'fixed_cart'))) {
      $couponValue = '€'. number_format($coupon->coupon_amount, 2, ',', '.');
    }
    else {
      $couponValue = "{$coupon->coupon_amount}%";
    }

    // Discounts are already applied, add a descriptive line with 0 amount.
    // The VAT rate this 0 amount should be categorized under should be
    // determined by the completor.
    $description = $this->t('discount_code');
    $amount = 0;
    $vatrate = NULL;
    $metaVatrateSource = static::VatRateSource_Completor;
    $description .= " {$coupon->code}: $couponValue";

    return array(
      'itemnumber' => '',
      'product' => $description,
      'unitprice' => -$amount,
      'vatrate' => $vatrate,
      'meta-vatrate-source' => $metaVatrateSource,
      'quantity' => 1,
    );
  }

}
