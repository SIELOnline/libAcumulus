<?php
namespace Siel\Acumulus\WooCommerce\WooCommerce2\Invoice;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;
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
     * Product price precision in WC2: one of the prices is entered by the
     * administrator and thus can be considered exact. The computed one is not
     * rounded, so we can assume a very high precision for all values here.
     *
     * @var float
     */
    protected $precisionPriceEntered  = 0.01;
    protected $precisionPriceCalculated  = 0.001;
    protected $precisionVat  = 0.001;

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
    public function getSourceMeta($property)
    {
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
    public function getOrderMeta($property)
    {
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

        $result = $this->groupBundles($result);

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

        // $product can be null if the product has been deleted.
        if ($product instanceof WC_Product) {
            $this->addPropertySource('product', $product);
        }
        $this->addPropertySource('item', $item);

        $invoiceSettings = $this->config->getInvoiceSettings();
        $this->addTokenDefault($result, Tag::ItemNumber, $invoiceSettings['itemNumber']);
        $this->addTokenDefault($result, Tag::Product, $invoiceSettings['productName']);
        $this->addTokenDefault($result, Tag::Nature, $invoiceSettings['nature']);

        // Add quantity: quantity is negative on refunds, make it positive.
        $sign  = $this->invoiceSource->getType() === source::CreditNote ? -1 : 1;
        $commonTags = array(Tag::Quantity => $sign * $item['qty']);
        $result += $commonTags;

        // Add price info.
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
                $result += array(
                    Tag::UnitPrice => $productPriceInc,
                    Meta::PrecisionUnitPrice => $this->precisionPriceCalculated,
                    Tag::CostPrice => $value,
                    Meta::PrecisionCostPrice => $this->precisionPriceCalculated,
                );
            }
        } else {
            $result += array(
                Tag::UnitPrice => $productPriceEx,
                Meta::PrecisionUnitPrice => $this->precisionPriceCalculated,
                Meta::UnitPriceInc => $productPriceInc,
                Meta::PrecisionUnitPriceInc => $this->precisionPriceCalculated,
            );
        }

        // Add tax info.
        $result += $this->getVatRangeTags($productVat, $productPriceEx, $this->precisionVat, $this->precisionPriceCalculated);
        if ($product instanceof WC_Product) {
            $result += $this->getVatRateLookupMetadataByTaxClass($product->get_tax_class());
        }

        // Add bundle meta data (woocommerce-bundle-products extension).
        $bundleId = $item['_bundle_cart_key'];
        if (!empty($bundleId)) {
            // Bundle or bundled product.
            $result[Meta::BundleId] = $bundleId;
        }
        $bundledBy = $item['_bundled_by'];
        if (!empty($bundledBy)) {
            // Bundled products only.
            $result[Meta::BundleParentId] = $bundledBy;
            $result[Meta::BundleVisible] = $item['bundled_item_hidden'] !== 'yes';
        }

        // Add variants/options.
        $commonTags[Meta::VatRateSource] = static::VatRateSource_Parent;
        if ($product instanceof WC_Product && !empty($item['variation_id'])) {
            $result[Meta::ChildrenLines] = $this->getVariantLines($item, $product, $commonTags);
        } elseif (!empty($item['tmcartepo_data'])) {
            // If the plugin is no longer used, we may still have an order with
            // products where the plugin was used. Moreover we don't use any
            // function or method from the plugin, only its stored data, so we
            // don'have to t check for it being active.
            $result[Meta::ChildrenLines] = $this->getExtraProductOptionsLines($item, $commonTags);
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
     * @param array $commonTags
     *   An array of tags from the parent product to add to the child lines.
     *
     * @return \array[]
     *   An array of lines that describes this variant.
     */
    protected function getVariantLines($item, WC_Product $product, array $commonTags)
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
                        Tag::Product => $meta['meta_key'] . ': ' . rawurldecode($meta['meta_value']),
                        Tag::UnitPrice => 0,
                    ) + $commonTags;
            }
        }

        return $result;
    }

    /**
     * @param array $item
     *
     * @return array
     */
    protected function getFeeLine($item)
    {
        $feeEx = $item['line_total'];
        $feeVat = $item['line_tax'];

        $result = array(
                Tag::Product => $this->t($item['name']),
                Tag::UnitPrice => $feeEx,
                Meta::PrecisionUnitPrice => 0.01,
                Tag::Quantity => 1,
            ) + $this->getVatRangeTags($feeVat, $feeEx);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getShippingLine()
    {
        $line = func_get_arg(0);
        $taxes = !empty($line['taxes']) ? maybe_unserialize($line['taxes']) : array();
        $vatLookupTags = $this->getShippingVatRateLookupMetadata($taxes);

        // Note: this info is WC2 specific.
        // Precision: shipping costs are entered ex VAT, so that may be very
        // precise, but it will be rounded to the cent by WC. For orders, the
        // VAT is as precise as a float can be and is based on the shipping cost
        // as entered by the admin. However, for refunds it is also rounded to
        // the cent.
        // @todo: to avoid rounding errors, can we get the non-formatted amount?
        $shippingEx = $this->shopSource->get_total_shipping();
        $shippingExPrecision = 0.01;
        $shippingVat = $this->shopSource->get_shipping_tax();
        $vatPrecision = $this->invoiceSource->getType() === Source::CreditNote ? 0.01 : 0.0001;

        $result = array(
                Tag::Product => !empty($line['name']) ? $line['name'] : $this->getShippingMethodName(),
                Tag::UnitPrice => $shippingEx,
                Meta::PrecisionUnitPrice => $shippingExPrecision,
                Tag::Quantity => 1,
            )
            + $this->getVatRangeTags($shippingVat, $shippingEx, $vatPrecision, $shippingExPrecision)
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
        // Get a description for the value of this coupon. Entered discount
        // amounts follow the productPricesIncludeTax() setting. Use that info
        // in the description.
        if ($coupon->exists) {
            $description = sprintf('%s %s: ', $this->t('discount_code'), $coupon->code);
            if (in_array($coupon->discount_type, array('fixed_product', 'fixed_cart'))) {
                $amount = $this->getSign() * $coupon->coupon_amount;
                if (!Number::isZero($amount)) {
                    $description .= sprintf('€%.2f (%s)', $amount, $this->productPricesIncludeTax() ? $this->t('inc_vat') : $this->t('ex_vat'));
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
            Tag::ItemNumber => $coupon->code,
            Tag::Product => $description,
            Tag::UnitPrice => 0,
            Meta::UnitPriceInc => 0,
            Tag::Quantity => 1,
            Tag::VatRate => null,
            Meta::VatAmount => 0,
            Meta::VatRateSource => static::VatRateSource_Completor,
        );
    }
}
