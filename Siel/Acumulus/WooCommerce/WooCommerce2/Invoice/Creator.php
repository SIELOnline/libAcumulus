<?php
namespace Siel\Acumulus\WooCommerce\WooCommerce2\Invoice;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\WooCommerce\Invoice\Creator as BaseCreator;
use WC_Coupon;
use WC_Product;
use WC_Tax;

/**
 * Allows to create an Acumulus invoice from a WooCommerce2 order or refund.
 *
 * This class only overrides methods that contain non BC compatible changes of
 * WooCommerce 3.
 */
class Creator extends BaseCreator
{
    /**
     * Precision in WC2: one of the prices is entered by the administrator and
     * thus can be considered exact. The computed one is not rounded, so we can
     * assume a very high precision for all values here.
     *
     * @var float
     */
    protected $precision  = 0.001;

    /**
     * {@inheritdoc}
     */
    protected function setPropertySources()
    {
        parent::setPropertySources();
        $this->propertySources['meta'] = array($this, 'getSourceMeta');
        if ($this->invoiceSource->getType() === Source::CreditNote) {
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
        $value = get_post_meta($this->invoiceSource->getId(), $property, true);
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
     * Returns the payment date of the order.
     *
     * @return string
     *   The payment date of the order (yyyy-mm-dd).
     */
    protected function getPaymentDateOrder()
    {
        /** @noinspection PhpUndefinedFieldInspection */
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
     */
    protected function getItemLines()
    {
        $result = array();
        /** @var array[] $lines */
        $lines = $this->shopSource->get_items(apply_filters('woocommerce_admin_order_item_types', 'line_item'));
        foreach ($lines as $order_item_id => $line) {
            $line['order_item_id'] = $order_item_id;
            $product = $this->shopSource->get_product_from_item($line);
            $itemLine = $this->getItemLine($line, $product);
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
     * @param WC_Product|bool $product
     *   The product that was sold on this line, may also be a bool according to
     *   the WC3 php documentation. I guess it will be false if the product has
     *   been deleted since.
     *
     * @return array
     *   May be empty if the line should not be sent (e.g. qty = 0 on a refund).
     */
    protected function getItemLine($item, $product)
    {
        $result = array();

        // Qty = 0 can happen on refunds: products that are not returned are
        // still listed but have qty = 0.
        if (Number::isZero($item['qty'])) {
            return $result;
        }

        // $product can be NULL if the product has been deleted.
        if ($product instanceof WC_Product) {
            $this->addPropertySource('product', $product);
        }
        $this->addPropertySource('item', $item);

        $invoiceSettings = $this->config->getInvoiceSettings();
        $this->addTokenDefault($result, 'itemnumber', $invoiceSettings['itemNumber']);
        $this->addTokenDefault($result, 'product', $invoiceSettings['productName']);
        $this->addTokenDefault($result, 'nature', $invoiceSettings['nature']);

        $sign  = $this->invoiceSource->getType() === source::CreditNote ? -1 : 1;
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
        if ($this->allowMarginScheme() && !empty($invoiceSettings['costPrice'])) {
            $value = $this->getTokenizedValue($invoiceSettings['costPrice']);
            if (!empty($value)) {
                // Margin scheme:
                // - Do not put VAT on invoice: send price incl VAT as unitprice.
                // - But still send the VAT rate to Acumulus.
                // Costprice > 0 triggers the margin scheme in Acumulus.
                $result['unitprice'] = $productPriceInc;
                $result['costprice'] = $value;
            }
        } else {
            $result += array(
                'unitprice' => $productPriceEx,
                'unitpriceinc' => $productPriceInc,
            );
        }

        // Quantity is negative on refunds, make it positive.
        $parentTags = array('quantity' => $sign * $item['qty']);
        $parentTags += $this->getVatRangeTags($productVat, $productPriceEx, $this->precision, $this->precision);
        if ($product instanceof WC_Product) {
            $parentTags += $this->getVatRateLookupMetadata($product->get_tax_class());
        }
        $result += $parentTags;

        // Add variants/options, but set vatamount to 0 on the child lines.
        $parentTags['vatamount'] = 0;
        if ($product instanceof WC_Product && !empty($item['variation_id'])) {
            $result[Creator::Line_Children] = $this->getVariantLines($item, $product, $parentTags);
        } elseif (is_plugin_active('woocommerce-tm-extra-product-options/tm-woo-extra-product-options.php') && !empty($item['tmcartepo_data'])) {
            $result[Creator::Line_Children] = $this->getExtraProductOptionsLines($item, $parentTags);
        }

        $this->removePropertySource('product');
        $this->removePropertySource('item');

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
     * @return \array[]
     *   An array of lines that describes this variant.
     */
    protected function getVariantLines($item, WC_Product $product, array $parentTags)
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
     * @param array $line
     *
     * @return array
     */
    protected function getFeeLine($line)
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
        $line = func_get_arg(0);
        $vatLookupTags = array();
        $taxes = !empty($line['taxes']) ? maybe_unserialize($line['taxes']) : array();
        if (count($taxes) === 1) {
            // @todo: $tax contains amount: can we use that?
            //$tax = reset($taxes);
            $vatLookupTags = array(
                // Will contain a % at the end of the string.
                'meta-vatrate-lookup' => substr(WC_Tax::get_rate_percent(key($taxes)), 0, -1),
                'meta-vatrate-lookup-label' => WC_Tax::get_rate_label(key($taxes)),
                'meta-vatrate-lookup-source' => '$line[\'taxes\']',
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
                        'meta-vatrate-lookup' => $tax_rate['rate'],
                        'meta-vatrate-lookup-label' => $tax_rate['label'],
                        'meta-vatrate-lookup-source' => "get_option('woocommerce_shipping_tax_class')",
                    );
                }
            }
        }

        // Note: this info is WC2 specific.
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
     * @param \WC_Coupon $coupon
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
