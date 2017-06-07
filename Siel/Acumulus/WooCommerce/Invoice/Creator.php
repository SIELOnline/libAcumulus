<?php
namespace Siel\Acumulus\WooCommerce\Invoice;

use Siel\Acumulus\Config\ConfigInterface;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use WC_Abstract_Order;
use WC_Coupon;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;
use WC_Tax;

/**
 * Allows to create an Acumulus invoice from a WooCommerce order or refund.
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
     * Precision in WC3: one of the prices is entered by the administrator and
     * thus can be considered exact. The computed one is rounded to the cent,
     * so we can not assume a very high precision for all values here.
     *
     * @var float
     */
    protected $precision  = 0.01;

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
                $this->order = new WC_Order($this->shopSource->get_parent_id());
                break;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function setPropertySources()
    {
        parent::setPropertySources();
        if ($this->invoiceSource->getType() === Source::CreditNote) {
            $this->propertySources['order'] = $this->order;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getCountryCode()
    {
        return $this->order->get_billing_country();
    }

    /**
     * {@inheritdoc}
     *
     * This override returns the id of a WC_Payment_Gateway.
     */
    protected function getPaymentMethod() {
        // Payment method is not stored for credit notes, so it is expected to
        // be the same as for its order.
        return $this->order->get_payment_method();
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
        return $this->order->needs_payment() ? ConfigInterface::PaymentStatus_Due : ConfigInterface::PaymentStatus_Paid;
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
        // This returns a WC_DateTime but that class has a _toString() method.
        $string = $this->order->get_date_paid();
        return substr($string, 0, strlen('2000-01-01'));
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
        // This returns a WC_DateTime but that class has a _toString() method.
        $string = $this->shopSource->get_date_modified();
        return substr($string, 0, strlen('2000-01-01'));
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
        /** @var WC_Order_Item_Product[] $lines */
        $lines = $this->shopSource->get_items(apply_filters('woocommerce_admin_order_item_types', 'line_item'));
        foreach ($lines as $line) {
            $product = $line->get_product();
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
     * @param WC_Order_Item_Product $item
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
        if (Number::isZero($item->get_quantity())) {
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
        $parentTags = array('quantity' => $sign * $item->get_quantity());
        $parentTags += $this->getVatRangeTags($productVat, $productPriceEx, $this->precision, $this->precision);
        if ($product instanceof WC_Product) {
            $parentTags += $this->getVatRateLookupMetadata($product->get_tax_class());
        }
        $result += $parentTags;

        // Add bundle meta data (woocommerce-bundle-products extension).
        $bundleId = $item->get_meta('_bundle_cart_key');
        if (!empty($bundleId)) {
            // Bundle or bundled product.
            $result['meta-bundle-id'] = $bundleId;
        }
        $bundledBy = $item->get_meta('_bundled_by');
        if (!empty($bundledBy)) {
            // Bundled products only.
            $result['meta-bundle-parent'] = $bundledBy;
            $result['meta-bundle-visible'] = $item->get_meta('bundled_item_hidden') !== 'yes';
        }

        // Add variants/options, but set vatamount to 0 on the child lines.
        // @todo: check how to access tmcartepo_data.
        $parentTags['vatamount'] = 0;
        $is_plugin_active = is_plugin_active('woocommerce-tm-extra-product-options/tm-woo-extra-product-options.php');
        if ($product instanceof WC_Product && $item->get_variation_id()) {
            $result[Creator::Line_Children] = $this->getVariantLines($item, $product, $parentTags);
        } elseif ($is_plugin_active && !empty($item['tmcartepo_data'])) {
            $result[Creator::Line_Children] = $this->getExtraProductOptionsLines($item, $parentTags);
        }

        $this->removePropertySource('product');
        $this->removePropertySource('item');

        return $result;
    }

    /**
     * Looks up and returns, if only 1 rate was found, vat rate metadata.
     *
     * @param string $taxClass
     *
     * @return array
     *   Either an array with keys 'meta-vatrate-lookup' and
     *  'meta-vatrate-lookup-label' or an empty array.
     */
    protected function getVatRateLookupMetadata($taxClass) {
        $result = array();
        $taxRates = WC_Tax::get_rates($taxClass);
        if (count($taxRates) === 1) {
            $taxRate = reset($taxRates);
            $result = array(
                'meta-vatrate-lookup' => $taxRate['rate'],
                'meta-vatrate-lookup-label' => $taxRate['label'],
            );
        }
        return $result;
    }

    /**
     * Returns an array of lines that describes this variant.
     *
     * This method supports the default WooCommerce variant functionality.
     *
     * @param \WC_Order_Item_Product $item
     * @param \WC_Product $product
     * @param array $parentTags
     *   An array of tags from the parent product to add to the child lines.
     *
     * @return array[]
     *   An array of lines that describes this variant.
     */
    protected function getVariantLines($item, WC_Product $product, array $parentTags)
    {
        $result = array();

        /**
         * Object with properties id, key, and value.
         *
         * @var object[] $metadata
         */
        $metadata = $item->get_meta_data();
        if (!empty($metadata)) {
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
                if (in_array($meta->key, $hiddenOrderItemMeta) || is_serialized($meta->value)) {
                    continue;
                }

                // Get attribute data.
                if (taxonomy_exists(wc_sanitize_taxonomy_name($meta->key))) {
                    $term = get_term_by('slug', $meta->value, wc_sanitize_taxonomy_name($meta->key));
                    $variantLabel = wc_attribute_label(wc_sanitize_taxonomy_name($meta->key));
                    $variantValue = isset($term->name) ? $term->name : $meta->value;
                }
                else {
                    $variantLabel = apply_filters('woocommerce_attribute_label', wc_attribute_label($meta->key, $product), $meta->key);
                    $variantValue = $meta->value;
                }

                $result[] = array(
                        'product' => $variantLabel . ': ' . rawurldecode($variantValue),
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
     * @param array|\ArrayAccess $item
     *   The item line
     * @param array $parentTags
     *   An array of tags from the parent product to add to the child lines.
     *
     * @return \array[] An array of lines that describes this variant.
     * An array of lines that describes this variant.
     */
    protected function getExtraProductOptionsLines($item, array $parentTags)
    {
        $result = array();

        // @todo: convert to WC3 interface.
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
     * Groups bundled products into the bundle product.
     *
     * This methods supports the woocommerce-product-bundles extension that
     * stores the bundle products as separate item lines below the bundle line
     * and uses the meta data described below to link them to each other. Our
     * getItemLine() method has copied that meta data into the resulting items.
     *
     * This method hierarchically group bundled products into the bundle product
     * and can do so multi-level.
     *
     * Bundle line meta data:
     * - bundle_cart_key (hash) unique identifier.
     * - bundled_items (hash[]) refers to the bundle_cart_key of the bundled
     *     products.
     *
     * Bundled items meta data:
     * - bundled_by (hash) refers to bundle_cart_key of the bundle line.
     * - bundle_cart_key (hash) unique identifier.
     * - bundled_item_hidden: 'yes'|'no' or absent (= 'no').
     *
     * @param array $itemLines
     *
     * @return array
     *   The item line in the set of item lines that turns out to be the parent
     *   of this item line, or null if the item line does not have a parent.
     */
    protected function groupBundles(array $itemLines)
    {
        $result = array();
        foreach ($itemLines as &$itemLine) {
            if (!empty($itemLine['meta-bundle-parent'])) {
                // Find the parent, note that we expect bundle products to
                // appear before their bundled products, so we can search in
                // $result and have a reference to a line in $result returned!
                $parent = &$this->getParentBundle($result, $itemLine['meta-bundle-parent']);
                if ($parent !== null) {
                    // Add the bundled product as a child to the bundle.
                    $parent[Creator::Line_Children][] = $itemLine;
                } else {
                    // Oops: not found. Store a message in the line meta data
                    // and keep it as a separate line.
                    $itemLine['meta-bundle-parent'] .= ': not found';
                    $result[] = $itemLine;
                }
            } else {
                // Not a bundled product: just add it to the result.
                $result[] = $itemLine;
            }
        }
        return $result;
    }

    /**
     * Searches for, and returns by reference, the parent bundle line.
     *
     * @param array $lines
     *   The lines to search for the parent bundle line.
     * @param $parentId
     *   The meta-bundle-id value to search for.
     *
     * @return array|null
     *   The parent bundle line or null if not found.
     */
    protected function &getParentBundle(array &$lines, $parentId)
    {
        foreach ($lines as &$line) {
            if (!empty($line['meta-bundle-id']) && $line['meta-bundle-id'] === $parentId) {
                return $line;
            } elseif (!empty($line[Creator::Line_Children])) {
                // Recursively search for the parent bundle.
                $parent = &$this->getParentBundle($line[Creator::Line_Children], $parentId);
                if ($parent !== null) {
                    return $parent;
                }
            }
        }
        // Not found.
        return null;
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
   * @param \WC_Order_Item_Fee $line
   *
   * @return array
   */
    protected function getFeeLine($line)
    {
        $feeEx = $line->get_total();
        $feeVat = $line->get_total_tax();

        $result = array(
                'product' => $this->t($line->get_name()),
                'unitprice' => $feeEx,
                'quantity' => 1,
                'vatamount' => $feeVat,
            ) + $this->getVatRangeTags($feeVat, $feeEx);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getShippingLines()
    {
        $result = array();
        // Get the shipping lines for this order.
        /** @var \WC_Order_Item_Shipping[] $shippingItems */
        $shippingItems = $this->shopSource->get_items(apply_filters('woocommerce_admin_order_item_types', 'shipping'));
        foreach ($shippingItems as $shippingItem) {
            $shippingLine = $this->getShippingLine($shippingItem);
            if ($shippingLine) {
                $result[] = $shippingLine;
            }
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getShippingLine()
    {
        /** @var \WC_Order_Item_Shipping $shippingItem */
        $shippingItem = func_get_arg(0);
        $vatLookupTags = array();
        $taxes = $shippingItem->get_taxes();
        if (is_array($taxes) && count($taxes) === 1) {
            // @todo: $tax contains amount: can we use that?
            //$tax = reset($taxes);
            $vatLookupTags = array(
                // Will contain a % at the end of the string.
                'meta-vatrate-lookup' => substr(WC_Tax::get_rate_percent(key($taxes)), 0, -1),
                'meta-vatrate-lookup-label' => WC_Tax::get_rate_label(key($taxes)),
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

        // Note: this info is WC3 specific.
        // Precision: shipping costs are entered ex VAT, so that may be very
        // precise, but it will be rounded to the cent by WC. The VAT is also
        // rounded to the cent.
        // @todo: to avoid rounding errors, can we get the non-formatted amount?
        $shippingEx = $this->shopSource->get_shipping_total();
        $shippingVat = $this->shopSource->get_shipping_tax();
        $precisionNumerator = 0.01;

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
        /** @var \WC_Order_Item_Shipping[] $lines */
        $lines = $this->shopSource->get_items(apply_filters('woocommerce_admin_order_item_types', 'shipping'));
        if (!empty($lines)) {
            $line = reset($lines);
            return $line->get_name();
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
     * @param \WC_Coupon $coupon
     *
     * @return array
     */
    protected function getDiscountLine(WC_Coupon $coupon)
    {
        // Get a description for the value of this coupon.
        // Entered discount amounts follow the wc_prices_include_tax() setting.
        // Use that info in the description.
        if ($coupon->get_id()) {
            // Coupon still exists: extract info from coupon.
            $description = sprintf('%s %s: ', $this->t('discount_code'), $coupon->get_code());
            if (in_array($coupon->get_discount_type(), array('fixed_product', 'fixed_cart'))) {
                $amount = $this->getSign() * $coupon->get_amount();
                if (!Number::isZero($amount)) {
                    $description .= sprintf('€%.2f (%s)', $amount, wc_prices_include_tax() ? $this->t('inc_vat') : $this->t('ex_vat'));
                }
                if ($coupon->get_free_shipping()) {
                    if (!Number::isZero($amount)) {
                        $description .= ' + ';
                    }
                    $description .= $this->t('free_shipping');
                }
            } else {
                // Value may be entered with or without % sign at the end.
                // Remove it by converting to a float.
                $description .= ((float) $coupon->get_amount()) . '%';
                if ($coupon->get_free_shipping()) {
                    $description .= ' + ' . $this->t('free_shipping');
                }
            }
        } else {
            // Coupon no longer exists: use generic name.
            $description = $this->t('discount_code');
        }
        return array(
            'itemnumber' => $coupon->get_code(),
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
