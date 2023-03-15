<?php
/**
 * Although we would like to use strict equality, i.e. including type equality,
 * unconditionally changing each comparison in this file will lead to problems
 * - API responses return each value as string, even if it is an int or float.
 * - The shop environment may be lax in its typing by, e.g. using strings for
 *   each value coming from the database.
 * - Our own config object is type aware, but, e.g, uses string for a vat class
 *   regardless the type for vat class ids as used by the shop itself.
 * So for now, we will ignore the warnings about non strictly typed comparisons
 * in this code, and we won't use strict_types=1.
 * @noinspection TypeUnsafeComparisonInspection
 * @noinspection PhpMissingStrictTypesDeclarationInspection
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

namespace Siel\Acumulus\WooCommerce\Invoice;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Tag;
use WC_Coupon;
use WC_Order_Item_Fee;
use WC_Order_Item_Product;
use WC_Product;
use WC_Tax;

use function count;
use function in_array;
use function is_array;
use function is_string;
use function strlen;

/**
 * Creates a raw version of the Acumulus invoice from a WooCommerce {@see Source}.
 *
 * @property \Siel\Acumulus\WooCommerce\Invoice\Source $invoiceSource
 */
class Creator extends BaseCreator
{
    /**
     * Whether the order has (non-empty) item lines.
     */
    protected bool $hasItemLines;

    /**
     * Product price precision in WC3: one of the prices is entered by the
     * administrator and may be assumed exact. The computed one is based on the
     * subtraction/addition of 2 amounts, so has a precision that may be twice
     * as worse. WC tended to round amounts to the cent, but does not seem to
     * any longer do so.
     *
     * However, we still get reports of missed vat rates because they are out of
     * range, so we remain on the safe side and only use higher precision when
     * possible.
     */
    protected float $precision = 0.01;

    protected function getItemLines(): array
    {
        $result = [];
        /** @var WC_Order_Item_Product[] $items */
        $items = $this->invoiceSource->getSource()->get_items(apply_filters('woocommerce_admin_order_item_types', 'line_item'));
        foreach ($items as $item) {
            $product = $item->get_product();
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
     * Some processing notes:
     * - Though recently I did not see it anymore, in the past I have seen
     *   refunds where articles that were not returned were still listed but
     *   with qty = 0 (and line total = 0).
     * - It turns out that you can do partial refunds by entering a broken
     *   number in the quantity field when defining a refund on the edit order
     *   page. However, the quantity stored is still rounded towards zero and
     *   thus may result in qty = 0 but line total != 0 or just item price not
     *   being equal to line total divided by the qty.
     *
     * @param WC_Order_Item_Product $item
     *   An object representing an order item line.
     * @param WC_Product|bool|null $product
     *   The product that was sold on this line, may also be a bool according to
     *   the WC3 php documentation. I guess it will be false if the product has
     *   been deleted since.
     *
     * @return array
     *   May be empty if the line should not be sent (e.g. qty = 0 on a refund).
     */
    protected function getItemLine(WC_Order_Item_Product $item, $product): array
    {
        $result = [];

        // Return if this "really" is an empty line, not when this is a line
        // with free products or an amount but without a quantity.
        $quantity = (float) $item->get_quantity();
        $total = (float) $item->get_total();
        if (Number::isZero($quantity) && Number::isZero($total)) {
            return $result;
        }

        // Support for some often used plugins that extend WooCommerce,
        // especially in the area of products (bundles, bookings, ...).
        /** @var \Siel\Acumulus\WooCommerce\Invoice\CreatorPluginSupport $creatorPluginSupport */
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
        // Correct if 0.
        if (Number::isZero($quantity)) {
            $quantity = $this->invoiceSource->getSign();
        }
        $commonTags = [Tag::Quantity => $quantity];
        $result += $commonTags;

        // Add price info. get_total() and get_taxes() return line totals after
        // discount. get_taxes() returns non-rounded tax amounts per tax class
        // id, whereas get_total_tax() returns either a rounded or non-rounded
        // amount, depending on the 'woocommerce_tax_round_at_subtotal' setting.
        $productPriceEx = $total / $quantity;
        $taxes = $item->get_taxes()['total'];
        $productVat = array_sum($taxes) / $quantity;
        $productPriceInc = $productPriceEx + $productVat;

        // Get precision info.
        if ($this->productPricesIncludeTax()) {
            // In the past I have seen WooCommerce store rounded vat amounts
            // together with a not rounded ex price. If that is the case, the
            // precision of the - calculated - inc price is not best, and we
            // should not recalculate the price ex when we have obtained a
            // corrected vat rate as that will worsen the precision of the price
            // ex.
            $precisionEx = $this->precision;
            $reason = $this->isPriceIncRealistic($productPriceInc, $taxes, $product);
            if ($reason !== '') {
                $this->addWarning($result, "Price inc is realistic: $reason");
                $precisionInc = 0.001;
                $recalculatePrice = Tag::UnitPrice;
            } else {
                $precisionInc = 0.02;
                $recalculatePrice = Meta::UnitPriceInc;
            }
        } else {
            $precisionEx = 0.001;
            $precisionInc = $this->precision;
            $recalculatePrice = Meta::UnitPriceInc;
        }
        // Note: this assumes that line calculations are done in a very precise
        // way (in other words: total_tax has not a precision of
        // base_precision * quantity) ...
        $precisionVat = max(abs($this->precision / $quantity), 0.001);

        // Check for cost price and margin scheme.
        if (!empty($line['costPrice']) && $this->allowMarginScheme()) {
            // Margin scheme:
            // - Do not put VAT on invoice: send price incl VAT as 'unitprice'.
            // - But still send the VAT rate to Acumulus.
            $result += [
                Tag::UnitPrice => $productPriceInc,
            ];
            $precisionEx = $precisionInc;
        } else {
            $result += [
                Tag::UnitPrice => $productPriceEx,
                Meta::UnitPriceInc => $productPriceInc,
                Meta::PrecisionUnitPriceInc => $precisionInc,
                Meta::RecalculatePrice => $recalculatePrice,
            ];
        }

        // Add tax info.
        $result += self::getVatRangeTags($productVat, $productPriceEx, $precisionVat, $precisionEx);
        if ($product instanceof WC_Product) {
            // get_tax_status() returns 'taxable', 'shipping', or 'none'.
            $taxClass = $product->get_tax_status() === 'taxable' ? $product->get_tax_class() : null;
            $result += $this->getVatRateLookupMetadataByTaxClass($taxClass);
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
     * A product has a tax class. A tax class can have multiple tax rates,
     * depending on the region of the customer. We use the customers address in
     * the raw invoice that is being created, to get the possible rates.
     *
     * @param string|null $taxClassId
     *   The tax class of the product. For the default tax class it can be
     *   'standard' or the empty string. For no tax class at all, it will be
     *   PluginConfig::VatClass_Null.
     * @todo: Can it be null?
     *
     * @return array
     *   An array with keys:
     *   - Meta::VatClassId: string
     *   - Meta::VatRateLookup: float[]
     *   - Meta::VatRateLookupLabel: string[]
     */
    protected function getVatRateLookupMetadataByTaxClass(?string $taxClassId): array
    {
        if ($taxClassId === null) {
            $result = [
                Meta::VatClassId => Config::VatClass_Null,
            ];
        } else {
            // '' denotes the 'standard' tax class, use 'standard' in metadata,
            // '' when searching.
            if ($taxClassId === '') {
                $taxClassId = 'standard';
            }
            $result = [
                Meta::VatClassId => sanitize_title($taxClassId),
                // Vat class name is the non-sanitized version of the id
                // and thus does not convey more information: don't add.
                Meta::VatRateLookup => [],
                Meta::VatRateLookupLabel => [],
            ];
            if ($taxClassId === 'standard') {
                $taxClassId = '';
            }

            // Find applicable vat rates. We use WC_Tax::find_rates() to find
            // them.
            $args = [
                'tax_class' => $taxClassId,
                'country' => $this->invoice[Tag::Customer][Tag::CountryCode],
                'city' => $this->invoice[Tag::Customer][Tag::City] ?? '',
                'postcode' => $this->invoice[Tag::Customer][Tag::PostalCode] ?? '',
            ];
            $taxRates = WC_Tax::find_rates($args);
            foreach ($taxRates as $taxRate) {
                $result[Meta::VatRateLookup][] = $taxRate['rate'];
                $result[Meta::VatRateLookupLabel][] = $taxRate['label'];
            }
        }
        return $result;
    }

    /**
     * Returns an array of lines that describes this variant.
     * This method supports the default WooCommerce variant functionality.
     *
     * @param \WC_Order_Item_Product $item
     * @param \WC_Product $product
     * @param array $commonTags
     *   An array of tags from the parent product to add to the child lines.
     *
     * @return array[]
     *   An array of lines that describes this variant.
     * @todo: Can $item->get_formatted_meta_data(''); be used to get this info?
     */
    protected function getVariantLines(WC_Order_Item_Product $item, WC_Product $product, array $commonTags): array
    {
        $result = [];

        /**
         * An array of objects with properties id, key, and value.
         *
         * @var object[] $metadata
         */
        $metadata = $item->get_meta_data();
        if (count($metadata) > 0) {
            // Define hidden core fields: check this when new versions from WC
            // are released with the list in e.g.
            // wp-content\plugins\woocommerce\includes\admin\meta-boxes\views\html-order-item-meta.php
            $hiddenOrderItemMeta = apply_filters('woocommerce_hidden_order_itemmeta', [
                    '_qty',
                    '_tax_class',
                    '_product_id',
                    '_variation_id',
                    '_line_subtotal',
                    '_line_subtotal_tax',
                    '_line_total',
                    '_line_tax',
                    'method_id',
                    'cost',
                    '_reduced_stock',
                    '_restock_refunded_items',
                ]
            );
            foreach ($metadata as $meta) {
                // Skip hidden fields:
                // - arrays
                // - serialized data (which are also hidden fields)
                // - tm extra product options plugin metadata which should be
                //   removed by that plugin via the
                //  'woocommerce_hidden_order_itemmeta' filter, but they don't.
                // - all metadata keys starting with an underscore (_). This is
                //   the convention for post metadata, but it is unclear if this
                //   is also the case for woocommerce order item metadata, see
                //   their own list versus the documentation on
                //   https://developer.wordpress.org/plugins/metadata/managing-post-metadata/#hidden-custom-fields
                if (in_array($meta->key, $hiddenOrderItemMeta, true)
                    || is_array($meta->value)
                    || is_serialized($meta->value)
                    || substr($meta->key, 0, strlen('_')) === '_'
                ) {
                    continue;
                }

                // Get attribute data.
                if (taxonomy_exists(wc_sanitize_taxonomy_name($meta->key))) {
                    $term = get_term_by('slug', $meta->value, wc_sanitize_taxonomy_name($meta->key));
                    $variantLabel = wc_attribute_label(wc_sanitize_taxonomy_name($meta->key));
                    $variantValue = $term->name ?? $meta->value;
                } else {
                    $variantLabel = apply_filters(
                        'woocommerce_attribute_label',
                        wc_attribute_label($meta->key, $product),
                        $meta->key,
                        $product
                    );
                    $variantValue = $meta->value;
                }

                // @todo: Why a rawurldecode() here, is that a "filter" to apply?
                $result[] = [
                        Tag::Product => $variantLabel . ': ' . rawurldecode($variantValue),
                        Tag::UnitPrice => 0,
                    ] + $commonTags;
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
    protected function getFeeLines(): array
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
    protected function getFeeLine(WC_Order_Item_Fee $item): array
    {
        $quantity = $item->get_quantity();
        $feeEx = $item->get_total() / $quantity;
        $feeVat = $item->get_total_tax() / $quantity;

        return [
                Tag::Product => $this->t($item->get_name()),
                Tag::UnitPrice => $feeEx,
                Tag::Quantity => $item->get_quantity(),
                Meta::Id => $item->get_id(),
            ] + self::getVatRangeTags($feeVat, $feeEx, $this->precision, $this->precision);
    }

    protected function getShippingLines(): array
    {
        $result = [];
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

    protected function getShippingLine(): array
    {
        /** @var \WC_Order_Item_Shipping $item */
        $item = func_get_arg(0);
        $taxes = $item->get_taxes();
        $vatLookupTags = $this->getShippingVatRateLookupMetadata($taxes);

        // Note: this info is WC3+ specific.
        // Precision: shipping costs are entered ex VAT, so that may be very
        // precise, but it will be rounded to the cent by WC. The VAT is also
        // rounded to the cent.
        $shippingEx = (float) $item->get_total();
        $precisionShippingEx = 0.01;

        // To avoid rounding errors, we try to get the non-formatted amount.
        // Due to changes in how WC configures shipping methods (now based on
        // zones), storage of order item metadata has changed. Therefore, we
        // have to try several option names.
        $methodId = $item->get_method_id();
        if (str_starts_with($methodId, 'legacy_')) {
            $methodId = substr($methodId, strlen('legacy_'));
        }
        // Instance id is the zone, will return an empty value if not present.
        $instanceId = $item->get_instance_id();
        $optionName = !empty($instanceId)
            ? "woocommerce_{$methodId}_{$instanceId}_settings"
            : "woocommerce_{$methodId}_settings";
        $option = get_option($optionName);

        if (!empty($option['cost'])) {
            // Note that "Cost" may contain a formula or use commas: 'Vul een
            // bedrag(excl. btw) in of een berekening zoals 10.00 * [qty].
            // Gebruik [qty] voor het aantal artikelen, [cost] voor de totale
            // prijs van alle artikelen, en [fee percent="10" min_fee="20"
            // max_fee=""] voor prijzen gebaseerd op percentage.'
            $cost = str_replace(',', '.', $option['cost']);
            if (is_numeric($cost)) {
                $cost = (float) $cost;
                if (Number::floatsAreEqual($cost, $shippingEx)) {
                    $shippingEx = $cost;
                    $precisionShippingEx = 0.001;
                }
            }
        }

        $quantity = $item->get_quantity();
        $shippingEx /= $quantity;
        $shippingVat = $item->get_total_tax() / $quantity;
        $precisionVat = 0.01;

        return [
                Tag::Product => $item->get_name(),
                Tag::UnitPrice => $shippingEx,
                Tag::Quantity => $quantity,
                Meta::Id => $item->get_id(),
            ]
            + self::getVatRangeTags($shippingVat, $shippingEx, $precisionVat, $precisionShippingEx)
            + $vatLookupTags;
    }

    /**
     * Looks up and returns vat rate metadata for shipping lines.
     * In WooCommerce, a shipping line can have multiple taxes. I am not sure if
     * that is possible for Dutch web shops, but if a shipping line does have
     * multiple taxes we fall back to the tax class setting for shipping
     * methods, that can have multiple tax rates itself (@param array|array[]|null $taxes
     *   The taxes applied to a shipping line.
     *
     * @return array
     *   An empty array or an array with keys:
     *   - Meta::VatClassId
     *   - Meta::VatRateLookup (*)
     *   - Meta::VatRateLookupLabel (*)
     *   - Meta::VatRateLookupSource (*)
     * @see
     * getVatRateLookupMetadataByTaxClass()). Anyway, this method will only
     * return metadata if only 1 rate was found.
     */
    protected function getShippingVatRateLookupMetadata(?array $taxes): array
    {
        $result = [];
        if (is_array($taxes)) {
            // Since version ?.?, $taxes has an indirection by key 'total'.
            if (is_string(key($taxes))) {
                /** @noinspection CallableParameterUseCaseInTypeContextInspection */
                $taxes = current($taxes);
            }
            /** @noinspection NotOptimalIfConditionsInspection */
            if (is_array($taxes)) {
                foreach ($taxes as $taxRateId => $amount) {
                    if (!empty($amount) && !Number::isZero($amount)) {
                        $taxRate = WC_Tax::_get_tax_rate($taxRateId, OBJECT);
                        if ($taxRate) {
                            if (count($result) === 0) {
                                $result = [
                                    Meta::VatClassId => $taxRate->tax_rate_class !== '' ? $taxRate->tax_rate_class : 'standard',
                                    // Vat class name is the non-sanitized
                                    // version of the id and thus does not
                                    // convey more information: don't add.
                                    Meta::VatRateLookup => [],
                                    Meta::VatRateLookupLabel => [],
                                    Meta::VatRateLookupSource => 'shipping line taxes',
                                ];
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

        if (count($result) === 0) {
            // Apparently we have free shipping (or a misconfigured shipment
            // method). Use a fall-back: WooCommerce only knows 1 tax rate
            // for all shipping methods, stored in config:
            $shippingTaxClass = get_option('woocommerce_shipping_tax_class');
            if (is_string($shippingTaxClass)) {
                /** @var \WC_Order $order */
                $order = $this->invoiceSource->getShopOrder()->getShopSource();

                // Since WC3, the shipping tax class can be based on those from
                // the product items in the cart (which should be the preferred
                // value for this setting). The code to get the derived tax
                // class is more or less copied from WC_Abstract_Order.
                if ($shippingTaxClass === 'inherit') {
                    $foundClasses = array_intersect(array_merge([''], WC_Tax::get_tax_class_slugs()), $order->get_items_tax_classes());
                    $shippingTaxClass = count($foundClasses) === 1 ? reset($foundClasses) : false;
                }

                /** @noinspection NotOptimalIfConditionsInspection */
                if (is_string($shippingTaxClass)) {
                    $result = $this->getVatRateLookupMetadataByTaxClass($shippingTaxClass);
                    if (count($result) > 0) {
                        $result[Meta::VatRateLookupSource] = "get_option('woocommerce_shipping_tax_class')";
                    }
                }
            }
        }

        return $result;
    }

    protected function getDiscountLines(): array
    {
        $result = [];

        // For refunds without any articles (probably just a manual refund) we
        // don't need to know what discounts were applied on the original order.
        // So skip get_used_coupons() on refunds without articles.
        /** @noinspection PhpClassConstantAccessedViaChildClassInspection */
        if ($this->invoiceSource->getType() !== Source::CreditNote || $this->hasItemLines) {
            // Add a line for all coupons applied. Coupons are only stored on
            // the order, not on refunds, so use the order.
            /** @var \WC_Order $order */
            $order = $this->invoiceSource->getShopOrder()->getShopSource();
            $usedCoupons = $order->get_coupon_codes();
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
    protected function getDiscountLine(WC_Coupon $coupon): array
    {
        // Get a description for the value of this coupon. Entered discount
        // amounts follow the productPricesIncludeTax() setting. Use that info
        // in the description.
        $couponId = $coupon->get_id();
        if ($couponId) {
            // Coupon still exists: extract info from coupon.
            $description = sprintf('%s %s: ', $this->t('discount_code'), $coupon->get_code());
            if (in_array($coupon->get_discount_type(), ['fixed_product', 'fixed_cart'])) {
                $amount = $this->invoiceSource->getSign() * $coupon->get_amount();
                if (!Number::isZero($amount)) {
                    $description .= sprintf(
                        '€%.2f (%s)',
                        $amount,
                        $this->productPricesIncludeTax() ? $this->t('inc_vat') : $this->t('ex_vat')
                    );
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
                $description .= $coupon->get_amount() . '%';
                if ($coupon->get_free_shipping()) {
                    $description .= ' + ' . $this->t('free_shipping');
                }
            }
        } else {
            // Coupon no longer exists: use generic name.
            $description = $this->t('discount_code');
        }
        return [
            Tag::ItemNumber => $coupon->get_code(),
            Tag::Product => $description,
            Tag::UnitPrice => 0,
            Meta::UnitPriceInc => 0,
            Tag::Quantity => 1,
            Tag::VatRate => null,
            Meta::VatAmount => 0,
            Meta::VatRateSource => static::VatRateSource_Completor,
            Meta::Id => $couponId,
        ];
    }

    /**
     * Returns whether the prices entered by an admin include taxes or not.
     *
     * @return bool
     *   True if the prices as entered by an admin include VAT, false if they are
     *   entered ex VAT.
     */
    protected function productPricesIncludeTax(): bool
    {
        return wc_prices_include_tax();
    }

    /**
     * Returns whether the price inc can be considered realistic.
     * Precondition: product prices as entered by the shop manager include tax
     *  and thus can be considered to be expressed in cents.
     * If a price inc is not considered realistic, we should not recalculate the
     * product price ex based on the product price inc after we have obtained a
     * corrected vat rate.
     *
     * @param float $productPriceInc
     *   The product price including vat found on an item line, this includes
     *   any discount.
     * @param float[] $taxes
     *   May be passed as strings.
     * @param \WC_Product|bool|null $product
     *   The product that has the given price inc and taxes.
     *
     * @return string
     *   true if the price inc can be considered realistic, false otherwise.
     */
    protected function isPriceIncRealistic(float $productPriceInc, array $taxes, $product): string
    {
        $reason = '';
        // Given the precondition that product prices as entered include vat, we
        // consider a price in cents realistic.
        if ((Number::isRounded($productPriceInc, 2))) {
            $reason = "price inc is rounded: $productPriceInc";
        }
        // If the price equals the actual product price, we consider it
        // realistic. Note that there may be valid reasons that the price differs
        // from the actual price, e.g. a price change since the order was placed,
        // or a discount that has been applied to the item line.
        if ($product instanceof WC_Product) {
            $productPriceOrg = $product->get_price();
            if (Number::floatsAreEqual($productPriceInc, $productPriceOrg, 0.000051)) {
                $reason = "item price inc ($productPriceInc) = product price inc ($productPriceOrg)";
            }
        }
        if (!Number::areRounded($taxes, 2)) {
            $reason = sprintf('not all taxes are rounded => taxes are realistic (%s)',
                str_replace('"', "'", json_encode($taxes, Meta::JsonFlags)));
        }
        return $reason;
    }
}
