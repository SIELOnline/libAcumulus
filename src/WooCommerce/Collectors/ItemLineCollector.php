<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Collectors;

use Siel\Acumulus\collectors\LineCollector;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;
use WC_Order_Item_Product;
use WC_Product;
use WC_Tax;

use function count;
use function in_array;
use function is_array;

/**
 * ItemLineCollector contains WooCommerce specific {@see LineType::Item} collecting logic.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class ItemLineCollector extends LineCollector
{
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

    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   An item line with the mapped fields filled in.
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        $this->getItemLine($acumulusObject);
    }

    /**
     * Returns 1 item line.
     *
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
     * @legacy: This is a copy of old Creator code: to be integrated in this collector in
     *   a neat/correct way!
     *
     * @param Line $line
     *   An item line with the mapped fields filled in
     */
    protected function getItemLine(Line $line): void
    {
        // Set some often used variables.
        /** @var \Siel\Acumulus\Data\Invoice $invoice */
        $invoice = $this->getPropertySource('invoice');
        /** @var \Siel\Acumulus\Invoice\Item $item */
        $item = $this->getPropertySource('item');
        /** @var WC_Order_Item_Product $shopItem */
        $shopItem = $item->getShopObject();
        /** @var \Siel\Acumulus\Invoice\Product $product */
        $product = $item->getProduct();
        /** @var WC_Product|null $shopProduct */
        $shopProduct = $product?->getShopObject();

        // Return if this "really" is an empty line, not when this is a line
        // with free products or an amount but without a quantity.
        $quantity = (float) $shopItem->get_quantity();
        $total = (float) $shopItem->get_total();

        // Add price info. get_total() and get_taxes() return line totals after
        // discount. get_taxes() returns non-rounded tax amounts per tax class
        // id, whereas get_total_tax() returns either a rounded or non-rounded
        // amount, depending on the 'woocommerce_tax_round_at_subtotal' setting.
        $productPriceEx = $total / $quantity;
        $taxes = $shopItem->get_taxes()['total'];
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
            $reason = $this->isPriceIncRealistic($productPriceInc, $taxes, $shopProduct);
            if ($reason !== '') {
                $this->addWarning($line, "Price inc is realistic: $reason", Meta::Info);
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
            $line->unitPrice = $productPriceInc;
            $precisionEx = $precisionInc;
        } else {
            $line->unitPrice = $productPriceEx;
            $line->metadataSet(Meta::UnitPriceInc, $productPriceInc);
            $line->metadataSet(Meta::PrecisionUnitPriceInc, $precisionInc);
            $line->metadataSet(Meta::RecalculatePrice, $recalculatePrice);
        }

        // Add tax info.
        self::addVatRangeTags($line, $productVat, $productPriceEx, $precisionVat, $precisionEx);
        if ($shopProduct !== null) {
            // get_tax_status() returns 'taxable', 'shipping', or 'none'.
            $taxClass = $shopProduct->get_tax_status() === 'taxable' ? $shopProduct->get_tax_class() : null;
            $this->addVatRateLookupMetadataByTaxClass($line, $taxClass, $invoice);
        }

        // Add variants/options.
        if ($shopProduct instanceof WC_Product && $shopItem->get_variation_id()) {
            $this->addVariantLines($line, $shopItem, $shopProduct);
        }
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
     *   @todo: Can it be null?
     */
    protected function addVatRateLookupMetadataByTaxClass(Line $line, ?string $taxClassId, Invoice $invoice): void
    {
        if ($taxClassId === null) {
            $line->metadataSet(Meta::VatClassId, Config::VatClass_Null);
        } else {
            // '' denotes the 'standard' tax class, use 'standard' in metadata,
            // '' when searching.
            if ($taxClassId === '') {
                $taxClassId = 'standard';
            }
            $line->metadataSet(Meta::VatClassId,sanitize_title($taxClassId));
            if ($taxClassId === 'standard') {
                $taxClassId = '';
            }
            // Vat class name is the non-sanitized version of the id
            // and thus does not convey more information: don't add.
            $line->metadataSet(Meta::VatRateLookup, []);
            $line->metadataSet(Meta::VatRateLookupLabel, []);

            // Find applicable vat rates. We use WC_Tax::find_rates() to find
            // them.
            /** @noinspection NullPointerExceptionInspection  getCustomer() will NOT return null */
            $args = [
                'tax_class' => $taxClassId,
                'country' => $invoice->getCustomer()->getFiscalAddress()->countryCode,
                'city' => $invoice->getCustomer()->getFiscalAddress()->city ?? '',
                'postcode' => $invoice->getCustomer()->getFiscalAddress()->postalCode ?? '',
            ];
            $taxRates = WC_Tax::find_rates($args);
            foreach ($taxRates as $taxRate) {
                // Set here to overwrite the defaults above, add later.
                $line->metadataSet(Meta::VatRateLookup, $taxRate['rate']);
                $line->metadataSet(Meta::VatRateLookupLabel, $taxRate['label']);
            }
        }
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
        /** @noinspection PhpUndefinedFunctionInspection   false positive */
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
     * @param \WC_Product|null $product
     *   The product that has the given price inc and taxes.
     *
     * @return string
     *   true if the price inc can be considered realistic, false otherwise.
     */
    protected function isPriceIncRealistic(float $productPriceInc, array $taxes, ?WC_Product $product): string
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
        if ($product !== null) {
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

    /**
     * Adds child lines that describes this variant.
     *
     * This method supports the default WooCommerce variant functionality.
     *
     * @todo: Can $item->get_formatted_meta_data(''); be used to get this info?
     */
    protected function addVariantLines(Line $line, WC_Order_Item_Product $item, WC_Product $product): void
    {
        /**
         * An array of objects with properties id, key, and value.
         *
         * @var object[] $metadata
         */
        $metadata = $item->get_meta_data();
        if (count($metadata) > 0) {
            // Define hidden core fields: check this when new versions from WC are
            // released with the list in e.g.
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
                    || str_starts_with($meta->key, '_')
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

                /** @var Line $child */
                $child = $this->createAcumulusObject();
                // @todo: Why a rawurldecode() here, is that a "filter" to apply?
                $child->product = $variantLabel . ': ' . rawurldecode($variantValue);
                $child->quantity = $line->quantity;
                $child->unitPrice = 0;
                $child->metadataSet(Meta::VatRateSource, VatRateSource::Parent);
                $line->addChild($child);
            }
        }
    }
}
