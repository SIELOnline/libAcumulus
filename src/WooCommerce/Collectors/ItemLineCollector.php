<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Collectors;

use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;
use WC_Order_Item_Product;
use WC_Product;

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
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   An item line with the mapped fields filled in.
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
        $this->getItemLine($acumulusObject, $propertySources);
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
     * @param Line $line
     *   An item line with the mapped fields filled in
     */
    protected function getItemLine(Line $line, PropertySources $propertySources): void
    {
        // Set some often used variables.
        /** @var \Siel\Acumulus\Data\Invoice $invoice */
        $invoice = $propertySources->get('invoice');
        /** @var \Siel\Acumulus\WooCommerce\Invoice\Item $item */
        $item = $propertySources->get('item');
        $product = $item->getProduct();
        $shopItem = $item->getShopObject();
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
                $this->addMessage($line, "Price inc is realistic: $reason", Meta::Info);
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

        $line->unitPrice = $productPriceEx;
        $line->metadataSet(Meta::UnitPriceInc, $productPriceInc);
        $line->metadataSet(Meta::PrecisionUnitPriceInc, $precisionInc);
        $line->metadataSet(Meta::RecalculatePrice, $recalculatePrice);

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
     * Adds child lines that describes this variant.
     *
     * This method supports the default WooCommerce variant functionality.
     *
     * @todo: Can $item->get_formatted_meta_data(''); be used to get this info?
     */
    protected function addVariantLines(Line $line, WC_Order_Item_Product $item, WC_Product $shopProduct): void
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
                        wc_attribute_label($meta->key, $shopProduct),
                        $meta->key,
                        $shopProduct
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
