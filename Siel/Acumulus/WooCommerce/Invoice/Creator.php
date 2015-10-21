<?php
namespace Siel\Acumulus\WooCommerce\Invoice;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\ConfigInterface;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use WC_Abstract_Order;
use WC_Coupon;
use WC_Order;
use WC_Product;

/**
 * Allows to create arrays in the Acumulus invoice structure from a WordPress
 * order or order refund.
 */
class Creator extends BaseCreator {

  // More specifically typed property.
  /** @var Source */
  protected $invoiceSource;

  /** @var WC_Abstract_Order The order or refund that is sent to Acumulus. */
  protected $shopSource;

  /** @var WC_Order The order self or the order that got refunded. */
  protected $order;

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
        $this->shopSource = $this->invoiceSource->getSource();
        $this->order = $this->shopSource;
        break;
      case Source::CreditNote:
        $this->shopSource = $this->invoiceSource->getSource();
        $this->order = new WC_Order($this->shopSource->post->post_parent);
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getCustomer() {
    $result = array();

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
    // The EU VAT Number plugin allows customers to indicate their VAT number as
    // to apply for the reversed VAT scheme. The vat number is stored under the
    // '_vat_number' meta key, though older versions did so under the
    // 'VAT Number' key.
    // See http://docs.woothemes.com/document/eu-vat-number-2/
    $this->addIfNotEmpty($result, 'vatnumber', get_post_meta($order->id, 'VAT Number', TRUE));
    $this->addIfNotEmpty($result, 'vatnumber', get_post_meta($order->id, 'vat_number', TRUE));
    $this->addIfNotEmpty($result, 'telephone', $order->billing_phone);
    $result['email'] = $order->billing_email;

    return $result;
  }

  /**
   * {@inheritdoc}
   *
   * For refunds, this override also searches in the order that gets refunded.
   */
  protected function searchProperty($property) {
    $value = parent::searchProperty($property);
    if (empty($value) && $this->invoiceSource->getType() === Source::CreditNote) {
      // Also try the order that gets refunded.
      $value = $this->getProperty($property, $this->order);
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  protected function getInvoiceNumber($invoiceNumberSource) {
    return $this->invoiceSource->getReference();
  }

  /**
   * Returns the date to use as invoice date for the order.
   *
   * param int $dateToUse
   *   \Siel\Acumulus\Shop\ConfigInterface\InvoiceDate_InvoiceCreate or
   *   \Siel\Acumulus\Shop\ConfigInterface\InvoiceDate_OrderCreate
   *
   * @return string
   *   Date to send to Acumulus as the invoice date: yyyy-mm-dd.
   */
  protected function getInvoiceDateOrder(/*$dateToUse*/) {
    // createdAt returns yyyy-mm-dd hh:mm:ss, take date part.
    return substr($this->shopSource->order_date, 0, strlen('2000-01-01'));
  }

  /**
   * Returns the date to use as invoice date for the order refund.
   *
   * param int $dateToUse
   *   \Siel\Acumulus\Shop\ConfigInterface\InvoiceDate_InvoiceCreate or
   *   \Siel\Acumulus\Shop\ConfigInterface\InvoiceDate_OrderCreate
   *
   * @return string
   *   Date to send to Acumulus as the invoice date: yyyy-mm-dd.
   */
  protected function getInvoiceDateCreditNote(/*$dateToUse*/) {
    return substr($this->shopSource->post->post_date, 0, strlen('2000-01-01'));
  }

  /**
   * Returns whether the order has been paid or not.
   *
   * @return int
   *   \Siel\Acumulus\Invoice\ConfigInterface::PaymentStatus_Paid or
   *   \Siel\Acumulus\Invoice\ConfigInterface::PaymentStatus_Due
   */
  protected function getPaymentStateOrder() {
    if (empty($this->shopSource->paid_date) || $this->shopSource->needs_payment()) {
      $result = ConfigInterface::PaymentStatus_Due;
    }
    else {
      $result = ConfigInterface::PaymentStatus_Paid;
    }
    return $result;
  }

  /**
   * Returns whether the order refund has been paid or not.
   *
   * For now we assume that a refund is paid back on creation.
   *
   * @return int
   *   \Siel\Acumulus\Invoice\ConfigInterface::PaymentStatus_Paid or
   *   \Siel\Acumulus\Invoice\ConfigInterface::PaymentStatus_Due
   */
  protected function getPaymentStateCreditNote() {
    $result = ConfigInterface::PaymentStatus_Paid;
    return $result;
  }

  /**
   * Returns the payment date of the order.
   *
   * @return string
   *   The payment date of the order (yyyy-mm-dd).
   */
  protected function getPaymentDateOrder() {
    return substr($this->shopSource->paid_date, 0, strlen('2000-01-01'));
  }

  /**
   * Returns the payment date of the order refund.
   *
   * We take the last modified date as pay date.
   *
   * @return string
   *   The payment date of the order refund (yyyy-mm-dd).
   */
  protected function getPaymentDateCreditNote() {
    return substr($this->shopSource->post->post_modified, 0, strlen('2000-01-01'));
  }

  /**
   * {@inheritdoc}
   *
   * This override provides the values meta-invoiceamountinc and
   * meta-invoicevatamount.
   */
  protected function getInvoiceTotals() {
    return array(
      'meta-invoiceamountinc' => $this->shopSource->get_total(),
      'meta-invoicevatamount' => $this->shopSource->get_total_tax(),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getItemLines() {
    $result = array();
    foreach ($this->shopSource->get_items() as $line) {
      $itemLine = $this->getItemLine($line, $this->shopSource);
      if ($itemLine) {
        $result[] = $itemLine;
      }
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
   *   May be empty if the line should not be sent (e.g. qty = 0 on a refund).
   */
  protected function getItemLine(array $item, WC_Abstract_Order $order) {
    $result = array();

    // Qty = 0 happens on refunds: products that are not returned are still
    // listed but have qty = 0.
    if (Number::isZero($item['qty'])) {
      return $result;
    }

    $isVariation = !empty($item['variation_id']);
    $product = wc_get_product($isVariation ? $item['variation_id'] : $item['product_id']);
    // get_item_total() returns cost per item after discount and ex vat (2nd
    // param).
    $productPriceEx = $order->get_item_total($item, FALSE, FALSE);
    $productPriceInc = $order->get_item_total($item, TRUE, FALSE);
    // get_item_tax returns tax per item after discount.
    $productVat = $order->get_item_tax($item, FALSE);

    $result['product'] = $item['name'];
    if ($product instanceof WC_Product) {
      $this->addIfNotEmpty($result, 'itemnumber', $product->get_sku());
      if ($isVariation) {
        $result['product'] .= ' (' . wc_get_formatted_variation($product->variation_data, TRUE) . ')';
      }
    }

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
    // Precision: one of the prices is entered by the administrator and thus can
    // be considered exact. The computed one is not rounded, so we can assume a
    // very high precision for all values here.
    $result += $this->getVatRangeTags($productVat, $productPriceEx, 0.001, 0.001);

    return $result;
  }

  /**
   * @inheritdoc
   *
   * WooCommerce has general fee lines, so we have to override this method to
   * add these general fees (type unknown to us)
   */
  protected function getFeeLines() {
    $result = parent::getFeeLines();

    // So far, all amounts found on refunds are negative, so we probably don't
    // need to correct the sign on these lines either: but this has not been
    // tested yet!.
    foreach ($this->shopSource->get_fees() as $feeLine) {
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
    // Precision: shipping costs are entered ex VAT, so that may be rounded to
    // the cent by the administrator. The computed costs inc VAT is rounded to
    // the cent as well, so both are to be considered precise to the cent.
    $shippingEx = $this->shopSource->get_total_shipping();
    $shippingVat = $this->shopSource->get_shipping_tax();
    if (!Number::isZero($shippingEx)) {
      $description = $this->t('shipping_costs');
    }
    else {
      // We do not need to indicate that free shipping is not refunded on an
      // order refund.
      if ($this->invoiceSource->getType() == Source::CreditNote) {
        return array();
      }
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
    $result = array();

    // Add a line for all coupons applied. Coupons are only stored on the order,
    // not on refunds, so use the order property.
    $usedCoupons = $this->order->get_used_coupons();
    foreach ($usedCoupons as $code) {
      $coupon = new WC_Coupon($code);
      $result[] = $this->getDiscountLine($coupon);
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
   * @param WC_Coupon $coupon
   *
   * @return array
   */
  protected function getDiscountLine(WC_Coupon $coupon) {
    // Get a description for the value of this coupon.
    // Entered discount amounts follow the wc_prices_include_tax() setting. Use
    // that info in the description.
    $description = sprintf('%s %s: ', $this->t('discount_code'), $coupon->code);
    if (in_array($coupon->discount_type, array('fixed_product', 'fixed_cart'))) {
      $amount = $this->getSign() * $coupon->coupon_amount;
      if (!Number::isZero($amount)) {
        $description .= sprintf('€%.2f (%s)', $amount, wc_prices_include_tax() ? $this->t('inc_vat') : $this->t('ex_vat'));
      }
      if ($coupon->enable_free_shipping()) {
        if (!Number::isZero($amount)) {
          $description .= ' + ';
        }
        $description .= $this->t('free_shipping');
      }
    }
    else {
      $description .= $coupon->coupon_amount . '%';
      if ($coupon->enable_free_shipping()) {
        $description .= ' + ' . $this->t('free_shipping');
      }
    }

    // Discounts are already applied, add a descriptive line with 0 amount.
    // The VAT rate to categorize this line under should be determined by the
    // completor.
    $amount = 0;
    $vatrate = NULL;
    $metaVatrateSource = static::VatRateSource_Completor;

    return array(
      'itemnumber' => $coupon->code,
      'product' => $description,
      'unitprice' => -$amount,
      'vatrate' => $vatrate,
      'meta-vatrate-source' => $metaVatrateSource,
      'quantity' => 1,
    );
  }

  /**
   * {@inheritdoc}
   *
   * This override corrects a credit invoice if the amount does not match the
   * sum of the lines so far. This can happen if an amount was entered manually.
   */
  protected function getManualLines() {
    if ($this->invoiceSource->getType() === Source::CreditNote) {
      $amount = (float) $this->shopSource->order_total;
      $linesAmount = $this->getLinesTotal();
      if (!Number::floatsAreEqual($amount, $linesAmount)) {
        // @todo: can we get the tax amount/rate over the manually entered refund?
        $line = array (
          'product' => $this->t('refund_adjustment'),
          'quantity' => 1,
          'unitpriceinc' => $amount - $linesAmount,
          'unitprice' => $amount - $linesAmount,
          'vatrate' => 0,
          'meta-vatrate-source' => Creator::VatRateSource_Exact,
        );
        return array($line);
      }
    }
    return parent::getManualLines();
  }


}
