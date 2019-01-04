<?php
namespace Siel\Acumulus\WooCommerce\Invoice;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;
use WC_Coupon;
use WC_Order_Item_Product;
use WC_Product;
use WC_Tax;

/**
 * Allows to create an Acumulus invoice from a WooCommerce order or refund.
 */
class Creator extends BaseCreator
{
    /** @var bool Whether the order has (non empty) item lines. */
    protected $hasItemLines;

    /**
     * Product price precision in WC3: one of the prices is entered by the
     * administrator and may be assumed exact. The computed one is based on the
     * subtraction/addition of 2 amounts, so has a precision that may be twice
     * as worse. WC tended to round amounts to the cent, but does not seem to
     * any longer do so.
     *
     * @var float
     */
    protected $precision = 0.001;

    /**
     * {@inheritdoc}
     */
    protected function getItemLines()
    {
        $result = array();
        /** @var WC_Order_Item_Product[] $items */
        $items = $this->invoiceSource->getSource()->get_items(apply_filters('woocommerce_admin_order_item_types', 'line_item'));
        foreach ($items as $item) {
            $product = $item->get_product();
            /** @noinspection PhpUnhandledExceptionInspection */
            $line = $this->getItemLine($item, $product);
            if ($line) {
                $result[] = $line;
            }
        }

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

        $creatorPluginSupport = $this->container->getInstance('CreatorPluginSupport', 'Invoice');
        $creatorPluginSupport->getItemLineBefore($this, $item, $product);
        // $product can be null if the product has been deleted.
        if ($product instanceof WC_Product) {
            $this->addPropertySource('product', $product);
        }
        $this->addPropertySource('item', $item);

        $this->addProductInfo($result);
        $result[Meta::Id] = $item->get_id();

        // Add quantity: quantity is negative on refunds, the unit price will be
        // positive.
        $quantity = $item->get_quantity();
        $commonTags = array(Tag::Quantity => $quantity);
        $result += $commonTags;

        // Add price info. get_total() and get_total_tax() return line totals
        // after discount (and will be negative on refunds).
        $productPriceEx = $item->get_total() / $quantity;
        $productVat = $item->get_total_tax() / $quantity;
        $productPriceInc = $productPriceEx + $productVat;

        // Get precision info.
        if ($this->productPricesIncludeTax()) {
            $precisionEx = 2 * $this->precision;
            $precisionInc = $this->precision;
            $recalculateUnitPrice = true;
        } else {
            $precisionEx = $this->precision;
            $precisionInc = 2 * $this->precision;
            $recalculateUnitPrice = false;
        }

        // Check for cost price and margin scheme.
        if (!empty($line['costPrice']) && $this->allowMarginScheme()) {
            // Margin scheme:
            // - Do not put VAT on invoice: send price incl VAT as unitprice.
            // - But still send the VAT rate to Acumulus.
            $result += array(
                Tag::UnitPrice => $productPriceInc,
            );
            $precisionEx = $precisionInc;
        } else {
            $result += array(
                Tag::UnitPrice => $productPriceEx,
                Meta::UnitPriceInc => $productPriceInc,
                Meta::PrecisionUnitPriceInc => $precisionInc,
                Meta::RecalculateUnitPrice => $recalculateUnitPrice,
            );
        }

        // Add tax info.
        $result += $this->getVatRangeTags($productVat, $productPriceEx, $this->precision, $precisionEx);
        if ($product instanceof WC_Product) {
            $result += $this->getVatRateLookupMetadataByTaxClass($product->get_tax_class());
        }

        // Add variants/options.
        $commonTags[Meta::VatRateSource] = static::VatRateSource_Parent;
        if ($product instanceof WC_Product && $item->get_variation_id()) {
            $result[Meta::ChildrenLines] = $this->getVariantLines($item, $product, $commonTags);
        }

        $this->removePropertySource('product');
        $this->removePropertySource('item');

        $creatorPluginSupport->getItemLineAfter($this, $item, $product);

        return $result;
    }

    /**
     * Looks up and returns vat rate metadata for product lines.
     *
     * A product has a tax class. A tax class can have multiple tax rates,
     * depending on the region of the customer. As we don't have that data here,
     * this method will only return metadata if only 1 rate was found.
     *
     * @param string $taxClassId
     *   The tax class of the product. For the default tax class it can be
     *   'standard' or empty.
     *
     * @return array
     *   An array with keys:
     *   - Meta::VatClassId: string
     *   - Meta::VatRateLookup: float[]
     *   - Meta::VatRateLookupLabel: string[]
     */
    protected function getVatRateLookupMetadataByTaxClass($taxClassId)
    {
        // '' denotes the 'standard'tax class, use 'standard' in meta data, ''
        // when searching.
        if ($taxClassId === '') {
            $taxClassId = 'standard';
        }
        $result = array(
            Meta::VatClassId => sanitize_title($taxClassId),
            // Vat class name is the non-sanitized version of the id
            // and thus does not convey more information: don't add.
            Meta::VatRateLookup => array(),
            Meta::VatRateLookupLabel => array(),
        );
        if ($taxClassId === 'standard') {
            $taxClassId = '';
        }

        // Find applicable vat rates. We use WC_Tax::find_rates() to find them.
        $args = array(
            'tax_class' => $taxClassId,
            'country' => $this->invoice[Tag::Customer][Tag::CountryCode],
            'city' => $this->invoice[Tag::Customer][Tag::City],
            'postcode' => $this->invoice[Tag::Customer][Tag::PostalCode],
        );
        $taxRates = WC_Tax::find_rates($args);
        foreach ($taxRates as $taxRate) {
            $result[Meta::VatRateLookup][] = $taxRate['rate'];
            $result[Meta::VatRateLookupLabel][] = $taxRate['label'];
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
     * @param array $commonTags
     *   An array of tags from the parent product to add to the child lines.
     *
     * @return array[]
     *   An array of lines that describes this variant.
     */
    protected function getVariantLines($item, WC_Product $product, array $commonTags)
    {
        $result = array();

        /**
         * An array of objects with properties id, key, and value.
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
                } else {
                    $variantLabel = apply_filters('woocommerce_attribute_label', wc_attribute_label($meta->key, $product), $meta->key);
                    $variantValue = $meta->value;
                }

                $result[] = array(
                        Tag::Product => $variantLabel . ': ' . rawurldecode($variantValue),
                        Tag::UnitPrice => 0,
                    ) + $commonTags;
            }
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
        foreach ($this->invoiceSource->getSource()->get_fees() as $feeLine) {
            $line = $this->getFeeLine($feeLine);
            $line = $this->addLineType($line, static::LineType_Other);
            $result[] = $line;
        }
        return $result;
    }

    /**
     * Returns an invoice line for 1 fee line.
     *
     * @param \WC_Order_Item_Fee $item
     *
     * @return array
     *   The invoice line for the given fee line.
     */
    protected function getFeeLine($item)
    {
        $quantity = $item->get_quantity();
        $feeEx = $item->get_total() / $quantity;
        $feeVat = $item->get_total_tax() / $quantity;

        $result = array(
                Tag::Product => $this->t($item->get_name()),
                Tag::UnitPrice => $feeEx,
                Tag::Quantity => $item->get_quantity(),
            ) + $this->getVatRangeTags($feeVat, $feeEx, $this->precision, $this->precision);

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
        $shippingItems = $this->invoiceSource->getSource()->get_items(apply_filters('woocommerce_admin_order_item_types', 'shipping'));
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
        /** @var \WC_Order_Item_Shipping $item */
        $item = func_get_arg(0);
        $taxes = $item->get_taxes();
        $vatLookupTags = $this->getShippingVatRateLookupMetadata($taxes);

        // Note: this info is WC3 specific.
        // Precision: shipping costs are entered ex VAT, so that may be very
        // precise, but it will be rounded to the cent by WC. The VAT is also
        // rounded to the cent.
        $shippingEx = $item->get_total();
        $precisionShippingEx = 0.01;

        // To avoid rounding errors, we try to get the non-formatted amount.
        // Due to changes in how WC configures shipping methods (now based on
        // zones), storage of order item meta data has changed. Therefore we
        // have to try several option names.
        $methodId = $item->get_method_id();
        if (substr($methodId, 0, strlen('legacy_')) === 'legacy_') {
            $methodId = substr($methodId, strlen('legacy_'));
        }
        // Instance id is the zone, will return an empty value if not present.
        $instanceId = $item->get_instance_id();

        if (!empty($instanceId)) {
            $optionName = "woocommerce_{$methodId}_{$instanceId}_settings";
        } else {
            $optionName = "woocommerce_{$methodId}_settings";
        }
        $option = get_option($optionName);
        if (isset($option['cost'])) {
            // Cost may be entered with a comma ...
            $cost = str_replace(',', '.', $option['cost']);
            if (Number::floatsAreEqual($cost, $shippingEx)) {
                $shippingEx = $cost;
                $precisionShippingEx = 0.001;
            }
        }
        $quantity = $item->get_quantity();
        $shippingEx /= $quantity;
        $shippingVat = $item->get_total_tax() / $quantity;
        $precisionVat = 0.01;

        $result = array(
                Tag::Product => $item->get_name(),
                Tag::UnitPrice => $shippingEx,
                Tag::Quantity => $quantity,
            )
            + $this->getVatRangeTags($shippingVat, $shippingEx, $precisionVat, $precisionShippingEx)
            + $vatLookupTags;

        return $result;
    }

    /**
     * Looks up and returns vat rate metadata for shipping lines.
     *
     * In WooCommerce, a shipping line can have multiple taxes. I am not sure if
     * that is possible for Dutch web shops, but if a shipping line does have
     * multiple taxes we fall back to the tax class setting for shipping
     * methods, that can have multiple tax rates itself (@see
     * getVatRateLookupMetadataByTaxClass()). Anyway, this method will only
     * return metadata if only 1 rate was found.
     *
     * @param array|null $taxes
     *   The taxes applied to a shipping line.
     *
     * @return array
     *   An empty array or an array with keys:
     *   - Meta::VatClassId
     *   - Meta::VatRateLookup (*)
     *   - Meta::VatRateLookupLabel (*)
     *   - Meta::VatRateLookupSource (*)
     */
    protected function getShippingVatRateLookupMetadata($taxes)
    {
        $result = array();
        if (is_array($taxes)) {
            // Since version ?.?, $taxes has an indirection by key 'total'.
            if (!is_numeric(key($taxes))) {
                $taxes = current($taxes);
            }
            if (is_array($taxes)) {
                foreach ($taxes as $taxRateId => $amount) {
                    if (!Number::isZero($amount)) {
                        $taxRate = WC_Tax::_get_tax_rate($taxRateId, OBJECT);
                        if ($taxRate) {
                            if (empty($result)) {
                                $result = array(
                                    Meta::VatClassId => $taxRate->tax_rate_class !== '' ? $taxRate->tax_rate_class : 'standard',
                                    // Vat class name is the non-sanitized
                                    // version of the id and thus does not
                                    // convey more information: don't add.
                                    Meta::VatRateLookup => array(),
                                    Meta::VatRateLookupLabel => array(),
                                    Meta::VatRateLookupSource => 'shipping line taxes',
                                );
                            }
                            // get_rate_percent() contains a % at the end of the
                            // string: remove it.
                            $result[Meta::VatRateLookup][] = substr(WC_Tax::get_rate_percent($taxRateId), 0, -1);
                            $result[Meta::VatRateLookupLabel][] = WC_Tax::get_rate_label($taxRate);
                        }
                    }
                }
            }
        }

        if (empty($result)) {
            // Apparently we have free shipping (or a misconfigured shipment
            // method). Use a fall-back: WooCommerce only knows 1 tax rate
            // for all shipping methods, stored in config:
            $shippingTaxClass = get_option('woocommerce_shipping_tax_class');
            if (is_string($shippingTaxClass)) {
                /** @var \WC_Order $order */
                $order = $this->invoiceSource->getOrder()->getSource();

                // Since WC3, the shipping tax class can be "inherited" from the
                // product items (which should be the preferred value for this
                // setting). The code to get the "inherited" tax class is more
                // or less copied from WC_Abstract_Order.
                if ($shippingTaxClass === 'inherit') {
                    $foundClasses = array_intersect(array_merge(array(''), WC_Tax::get_tax_class_slugs()), $order->get_items_tax_classes());
                    $shippingTaxClass = count($foundClasses) === 1 ? reset($foundClasses) : false;
                }

                if (is_string($shippingTaxClass)) {
                    $result = $this->getVatRateLookupMetadataByTaxClass($shippingTaxClass);
                    if (!empty($result)) {
                        $result[Meta::VatRateLookupSource] = "get_option('woocommerce_shipping_tax_class')";
                    }
                }
            }
        }

        return $result;
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
            // the order, not on refunds, so use the order.
	        /** @var \WC_Order $order */
            $order = $this->invoiceSource->getOrder()->getSource();
	        $usedCoupons = $order->get_used_coupons();
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
     * @param \WC_Coupon $coupon
     *
     * @return array
     */
    protected function getDiscountLine(WC_Coupon $coupon)
    {
        // Get a description for the value of this coupon. Entered discount
        // amounts follow the productPricesIncludeTax() setting. Use that info
        // in the description.
        if ($coupon->get_id()) {
            // Coupon still exists: extract info from coupon.
            $description = sprintf('%s %s: ', $this->t('discount_code'), $coupon->get_code());
            if (in_array($coupon->get_discount_type(), array('fixed_product', 'fixed_cart'))) {
                $amount = $this->invoiceSource->getSign() * (float) $coupon->get_amount();
                if (!Number::isZero($amount)) {
                    $description .= sprintf('€%.2f (%s)', $amount, $this->productPricesIncludeTax() ? $this->t('inc_vat') : $this->t('ex_vat'));
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
            Tag::ItemNumber => $coupon->get_code(),
            Tag::Product => $description,
            Tag::UnitPrice => 0,
            Meta::UnitPriceInc => 0,
            Tag::Quantity => 1,
            Tag::VatRate => null,
            Meta::VatAmount => 0,
            Meta::VatRateSource => static::VatRateSource_Completor,
        );
    }

    /**
     * Returns whether the prices entered by an admin include taxes or not.
     *
     * @return bool
     *   True if the prices as entered by an admin include VAT, false if they are
     *   entered ex VAT.
     */
    protected function productPricesIncludeTax()
    {
        return wc_prices_include_tax();
    }
}
