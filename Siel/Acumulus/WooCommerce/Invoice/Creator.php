<?php
namespace Siel\Acumulus\WooCommerce\Invoice;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\ConfigInterface;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use WC_Abstract_Order;
use WC_Coupon;
use WC_Order;
use WC_Product;
use WC_Tax;

/**
 * Allows to create arrays in the Acumulus invoice structure from a WordPress
 * order or order refund.
 */
class Creator extends BaseCreator
{
    /** @var WC_Abstract_Order The order or refund that is sent to Acumulus. */
    protected $shopSource;

    /** @var WC_Order The order self or the order that got refunded. */
    protected $order;

    /** @var bool Whether the order has (non empty) item lines. */
    protected $hasItemLines;

    /**
     * {@inheritdoc}
     *
     * This override also initializes WooCommerce specific properties related to
     * the source.
     */
    protected function setInvoiceSource($invoiceSource)
    {
        parent::setInvoiceSource($invoiceSource);
        $this->shopSource = $this->invoiceSource->getSource();
        switch ($this->invoiceSource->getType()) {
            case Source::Order:
                $this->order = $this->shopSource;
                break;
            case Source::CreditNote:
                $this->order = new WC_Order($this->shopSource->post->post_parent);
                break;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function setPropertySources()
    {
        parent::setPropertySources();
        $this->propertySources['meta'] = array($this, 'getSourceMeta');
        if ($this->invoiceSource->getType() === Source::CreditNote) {
            $this->propertySources['order'] = $this->order;
            $this->propertySources['order_meta'] = array($this, 'getOrderMeta');
        }
    }

    /**
     * Token callback to access the post meta when resolving tokens.
     *
     * @param string $property
     *
     * @return null|string
     *   The value for the meta data with the given name, null if not available.
     */
    public function getSourceMeta($property) {
        $value = get_post_meta($this->invoiceSource->getSource()->id, $property, true);
        // get_post_meta() can return false or ''.
        if (empty($value)) {
            // Not found: indicate so by returning null.
            $value = null;
        }
        return $value;
    }

    /**
     * Token callback to access the order post meta when resolving tokens.
     *
     * @param string $property
     *
     * @return null|string
     *   The value for the meta data with the given name, null if not available.
     */
    public function getOrderMeta($property) {
        $value = get_post_meta($this->order->id, $property, true);
        // get_post_meta() can return false or ''.
        if (empty($value)) {
            // Not found: indicate so by returning null.
            $value = null;
        }
        return $value;
    }

    /**
     * {@inheritdoc}
     */
    protected function getCountryCode()
    {
        return isset($this->order->billing_country) ? $this->order->billing_country : '';
    }

    /**
     * {@inheritdoc}
     */
    protected function getInvoiceNumber($invoiceNumberSource)
    {
        return $this->invoiceSource->getReference();
    }

    /**
     * Returns the date to use as invoice date for the order.
     *
     * param int $dateToUse
     *   \Siel\Acumulus\Invoice\ConfigInterface\InvoiceDate_InvoiceCreate or
     *   \Siel\Acumulus\Invoice\ConfigInterface\InvoiceDate_OrderCreate
     *
     * @return string
     *   Date to send to Acumulus as the invoice date: yyyy-mm-dd.
     */
    protected function getInvoiceDateOrder(/*$dateToUse*/)
    {
        // order_date (= post_date) returns yyyy-mm-dd hh:mm:ss, take date part.
        return substr($this->shopSource->order_date, 0, strlen('2000-01-01'));
    }

    /**
     * Returns the date to use as invoice date for the order refund.
     *
     * param int $dateToUse
     *   \Siel\Acumulus\Invoice\ConfigInterface\InvoiceDate_InvoiceCreate or
     *   \Siel\Acumulus\Invoice\ConfigInterface\InvoiceDate_OrderCreate
     *
     * @return string
     *   Date to send to Acumulus as the invoice date: yyyy-mm-dd.
     */
    protected function getInvoiceDateCreditNote(/*$dateToUse*/)
    {
        return substr($this->shopSource->post->post_date, 0, strlen('2000-01-01'));
    }

    /**
     * {@inheritdoc}
     *
     * This override returns the id of a WC_Payment_Gateway.
     */
    protected function getPaymentMethod()
    {
        if (isset($this->shopSource->payment_method)) {
            return $this->shopSource->payment_method;
        }
        return parent::getPaymentMethod();
    }

    /**
     * Returns whether the order has been paid or not.
     *
     * @return int
     *   \Siel\Acumulus\Invoice\ConfigInterface::PaymentStatus_Paid or
     *   \Siel\Acumulus\Invoice\ConfigInterface::PaymentStatus_Due
     */
    protected function getPaymentStateOrder()
    {
        return $this->shopSource->needs_payment() ? ConfigInterface::PaymentStatus_Due : ConfigInterface::PaymentStatus_Paid;
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
    protected function getPaymentStateCreditNote()
    {
        return ConfigInterface::PaymentStatus_Paid;
    }

    /**
     * Returns the payment date of the order.
     *
     * @return string
     *   The payment date of the order (yyyy-mm-dd).
     */
    protected function getPaymentDateOrder()
    {
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
    protected function getPaymentDateCreditNote()
    {
        return substr($this->shopSource->post->post_modified, 0, strlen('2000-01-01'));
    }

    /**
     * {@inheritdoc}
     *
     * This override provides the values meta-invoice-amountinc and
     * meta-invoice-vatamount.
     */
    protected function getInvoiceTotals()
    {
        return array(
            'meta-invoice-amountinc' => $this->shopSource->get_total(),
            'meta-invoice-vatamount' => $this->shopSource->get_total_tax(),
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getItemLines()
    {
        $result = array();
        $lines = $this->shopSource->get_items(apply_filters('woocommerce_admin_order_item_types', 'line_item'));
        foreach ($lines as $order_item_id => $line) {
            $line['order_item_id'] = $order_item_id;
            $itemLine = $this->getItemLine($line);
            if ($itemLine) {
                $result[] = $itemLine;
            }
        }

        $this->hasItemLines = count($result) > 0;
        return $result;
    }

    /**
     * Returns 1 item line.
     *
     * @param array $item
     *   An array representing an order item line, meta values are already
     *   available under their own names and as an array under key 'item_meta'.
     *
     * @return array
     *   May be empty if the line should not be sent (e.g. qty = 0 on a refund).
     */
    protected function getItemLine(array $item)
    {
        $result = array();

        // Qty = 0 can happen on refunds: products that are not returned are
        // still listed but have qty = 0.
        if (Number::isZero($item['qty'])) {
            return $result;
        }

        $sign  = $this->invoiceSource->getType() === source::CreditNote ? -1 : 1;

        // $product can be NULL if the product has been deleted.
        $product = $this->shopSource->get_product_from_item($item);
        $vatLookupTags = array();
        if ($product instanceof WC_Product) {
            $this->addIfNotEmpty($result, 'itemnumber', $product->get_sku());
            $vatLookupTags = $this->getVatRateLookupMetadata($product->get_tax_class());
        }
        $result['product'] = $item['name'];

        // get_item_total() returns cost per item after discount and ex vat (2nd
        // param).
        $productPriceEx = $this->shopSource->get_item_total($item, false, false);
        $productPriceInc = $this->shopSource->get_item_total($item, true, false);
        // get_item_tax returns tax per item after discount.
        $productVat = $this->shopSource->get_item_tax($item, false);

        // WooCommerce does not support the margin scheme. So in a standard
        // install this method will always return false. But if this method
        // happens to return true anyway (customisation, hook), the costprice
        // tag will trigger vattype = 5 for Acumulus.
        if ($this->allowMarginScheme() && !empty($item['cost_price'])) {
            // Margin scheme:
            // - Do not put VAT on invoice: send price incl VAT as unitprice.
            // - But still send the VAT rate to Acumulus.
            // Costprice > 0 triggers the margin scheme in Acumulus.
            $result += array(
                'unitprice' => $productPriceInc,
                'costprice' => $item['cost_price'],
            );
        } else {
            $result += array(
                'unitprice' => $productPriceEx,
                'unitpriceinc' => $productPriceInc,
            );
        }

        // Precision: one of the prices is entered by the administrator and thus
        // can be considered exact. The computed one is not rounded, so we can
        // assume a very high precision for all values here.
        $vatRangeTags = $this->getVatRangeTags($productVat, $productPriceEx, 0.001, 0.001);
        // Quantity is negative on refunds
        $parentTags = array('quantity' => $sign * $item['qty']) + $vatRangeTags + $vatLookupTags;
        $result += $parentTags;

        // Add variants/options, but set vatamount to 0 on the child lines.
        $parentTags['vatamount'] = 0;
        if ($product instanceof WC_Product && !empty($item['variation_id'])) {
            $result[Creator::Line_Children] = $this->getVariantLines($item, $product, $parentTags);
        } elseif (is_plugin_active('woocommerce-tm-extra-product-options/tm-woo-extra-product-options.php') && !empty($item['tmcartepo_data'])) {
            $result[Creator::Line_Children] = $this->getExtraProductOptionsLines($item, $parentTags);
        }

        return $result;
    }

    /**
     * Looks up and returns, if only 1 rate was found, vat rate metadata.
     *
     * @param string $taxClass
     *
     * @return array
     *   Either an array with keys 'meta-lookup-vatrate' and
     *  'meta-lookup-vatrate-label' or an empty array.
     */
    protected function getVatRateLookupMetadata($taxClass) {
        $result = array();
        $taxRates = WC_Tax::get_rates($taxClass);
        if (count($taxRates) === 1) {
            $taxRate = reset($taxRates);
            $result = array(
                'meta-lookup-vatrate' => $taxRate['rate'],
                'meta-lookup-vatrate-label' => $taxRate['label'],
            );
        }
        return $result;
    }

    /**
     * Returns an array of lines that describes this variant.
     *
     * This method supports the default WooCommerce variant functionality.
     *
     * @param array $item
     * @param \WC_Product $product
     * @param array $parentTags
     *   An array of tags from the parent product to add to the child lines.
     *
     * @return array[]
     *   An array of lines that describes this variant.
     */
    protected function getVariantLines(array $item, WC_Product $product, array $parentTags)
    {
        $result = array();

        if ($metadata = $this->shopSource->has_meta($item['order_item_id'])) {
            // Define hidden core fields.
            $hiddenOrderItemMeta = apply_filters('woocommerce_hidden_order_itemmeta', array(
                '_qty',
                '_tax_class',
                '_product_id',
                '_variation_id',
                '_line_subtotal',
                '_line_subtotal_tax',
                '_line_total',
                '_line_tax',
            ));
            foreach ($metadata as $meta) {
                // Skip hidden core fields and serialized data (also hidden core
                // fields).
                if (in_array($meta['meta_key'], $hiddenOrderItemMeta) || is_serialized($meta['meta_value'])) {
                    continue;
                }

                // Get attribute data.
                if (taxonomy_exists(wc_sanitize_taxonomy_name($meta['meta_key']))) {
                    $term = get_term_by('slug', $meta['meta_value'], wc_sanitize_taxonomy_name($meta['meta_key']));
                    $meta['meta_key'] = wc_attribute_label(wc_sanitize_taxonomy_name($meta['meta_key']));
                    $meta['meta_value'] = isset($term->name) ? $term->name : $meta['meta_value'];
                } else {
                    $meta['meta_key'] = apply_filters('woocommerce_attribute_label', wc_attribute_label($meta['meta_key'], $product), $meta['meta_key']);
                }

                $result[] = array(
                        'product' => $meta['meta_key'] . ': ' . rawurldecode($meta['meta_value']),
                        'unitprice' => 0,
                    ) + $parentTags;
            }
        }

        return $result;
    }

    /**
     * Returns an array of lines that describes this variant.
     *
     * This method supports the WooCommerce Extra Product Options plugin. This
     * plugin places its data in the meta data under keys that start wth tm_epo
     * or tmcartepo. We need the the tncartepo_data value as that contains the
     * options.
     *
     * @param array $item
     *   The item line
     * @param array $parentTags
     *   An array of tags from the parent product to add to the child lines.
     *
     * @return array[]
     *   An array of lines that describes this variant.
    *
     */
    protected function getExtraProductOptionsLines(array $item, array $parentTags)
    {
        $result = array();

        $options = unserialize($item['tmcartepo_data']);
        foreach ($options as $option) {
            // Get option name and choice.
            $label = $option['name'];
            $choice = $option['value'];
            // @todo: price, quantity, vat rate?
            $result[] = array(
                    'product' => $label . ': ' . $choice,
                    'unitprice' => 0,
                ) + $parentTags;
        }

        return $result;
    }

    /**
     * @inheritdoc
     *
     * WooCommerce has general fee lines, so we have to override this method to
     * add these general fees (type unknown to us)
     */
    protected function getFeeLines()
    {
        $result = parent::getFeeLines();

        // So far, all amounts found on refunds are negative, so we probably
        // don't need to correct the sign on these lines either: but this has
        // not been tested yet!.
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
    protected function getFeeLine(array $line)
    {
        $feeEx = $line['line_total'];
        $feeVat = $line['line_tax'];

        $result = array(
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
    protected function getShippingLine()
    {
        // Check if a shipping line item exists for this order.
        $lines = $this->shopSource->get_items(apply_filters('woocommerce_admin_order_item_types', 'shipping'));
        if (empty($lines)) {
            return array();
        }

        // If we have only 1 line, we use it to lookup the tax rate. If we have
        // multiple lines we use the first line for the name.
        // @todo: allow for multiple shipping lines.
        $vatLookupTags = array();
        $line = reset($lines);
        if (count($lines) === 1) {
            $taxes = !empty($line['taxes']) ? maybe_unserialize($line['taxes']) : array();
            if (count($taxes) === 1) {
                // @todo: $tax contains amount: can we use that?
                //$tax = reset($taxes);
                $vatLookupTags = array(
                    // Will contain a % at the end of the string.
                    'meta-lookup-vatrate' => substr(WC_Tax::get_rate_percent(key($taxes)), 0, -1),
                    'meta-lookup-vatrate-label' => WC_Tax::get_rate_label(key($taxes)),
                );
            } else {
                // Apparently we have free shipping (or a misconfigured shipment
                // method). Use a fall-back: WooCommerce only knows 1 tax rate
                // for all shipping methods, stored in config:
                $shipping_tax_class = get_option('woocommerce_shipping_tax_class');
                if ( $shipping_tax_class === 'standard') {
                    $shipping_tax_class = '';
                }
                if (is_string($shipping_tax_class)) {
                    $tax_rates = WC_Tax::get_rates($shipping_tax_class);
                    if (count($tax_rates) === 1) {
                        $tax_rate = reset($tax_rates);
                        $vatLookupTags = array(
                            'meta-lookup-vatrate' => $tax_rate['rate'],
                            'meta-lookup-vatrate-label' => $tax_rate['label'],
                        );
                    }
                }
            }
        }

        // Precision: shipping costs are entered ex VAT, so that may be very
        // precise, but it will be rounded to the cent by WC. For orders, the
        // VAT is as precise as a float can be and is based on the shipping cost
        // as entered by the admin. However, for refunds it is also rounded to
        // the cent.
        // @todo: to avoid rounding errors, can we get the non-formatted amount?
        $shippingEx = $this->shopSource->get_total_shipping();
        $shippingVat = $this->shopSource->get_shipping_tax();
        $precisionNumerator = $this->invoiceSource->getType() === Source::CreditNote ? 0.01 : 0.0001;

        $result = array(
                'product' => $this->getShippingMethodName(),
                'unitprice' => $shippingEx,
                'quantity' => 1,
                'vatamount' => $shippingVat,
            )
            + $this->getVatRangeTags($shippingVat, $shippingEx, $precisionNumerator, 0.01)
            + $vatLookupTags;

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getShippingMethodName()
    {
        // Check if a shipping line item exists for this order.
        $lines = $this->shopSource->get_items(apply_filters('woocommerce_admin_order_item_types', 'shipping'));
        if (!empty($lines)) {
            $line = reset($lines);
            return $line['name'];
        }
        return parent::getShippingMethodName();
    }

    /**
     * {@inheritdoc}
     */
    protected function getDiscountLines()
    {
        $result = array();

        // For refunds without any articles (probably just a manual refund) we
        // don't need to know what discounts were applied on the original order.
        // So skip get_used_coupons() on refunds without articles.
        if ($this->invoiceSource->getType() !== Source::CreditNote || $this->hasItemLines) {
            // Add a line for all coupons applied. Coupons are only stored on
            // the order, not on refunds, so use the order property.
            $usedCoupons = $this->order->get_used_coupons();
            foreach ($usedCoupons as $code) {
                $coupon = new WC_Coupon($code);
                $result[] = $this->getDiscountLine($coupon);
            }
        }
        return $result;
    }

    /**
     * Returns 1 order discount line for 1 coupon usage.
     *
     * In WooCommerce, discounts are implemented with coupons. Multiple coupons
     * can be used per order. Coupons can:
     * - have a fixed amount or a percentage.
     * - be applied to the whole cart or only be used for a set of products.
     *
     * Discounts are already applied, add a descriptive line with 0 amount. The
     * VAT rate to categorize this line under should be determined by the
     * completor.
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
    protected function getDiscountLine(WC_Coupon $coupon)
    {
        // Get a description for the value of this coupon.
        // Entered discount amounts follow the wc_prices_include_tax() setting.
        // Use that info in the description.
        if ($coupon->exists) {
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
            } else {
                // Value may be entered with or without % sign at the end.
                // Remove it by converting to a float.
                $description .= ((float) $coupon->coupon_amount) . '%';
                if ($coupon->enable_free_shipping()) {
                    $description .= ' + ' . $this->t('free_shipping');
                }
            }
        } else {
            $description = $this->t('discount_code');
        }
        return array(
            'itemnumber' => $coupon->code,
            'product' => $description,
            'unitprice' => 0,
            'unitpriceinc' => 0,
            'quantity' => 1,
            'vatrate' => null,
            'vatamount' => 0,
            'meta-vatrate-source' => static::VatRateSource_Completor,
        );
    }
}
