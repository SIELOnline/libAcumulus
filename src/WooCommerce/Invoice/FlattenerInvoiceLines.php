<?php
namespace Siel\Acumulus\WooCommerce\Invoice;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Completor;
use Siel\Acumulus\Invoice\FlattenerInvoiceLines as BaseFlattenerInvoiceLines;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

/**
 * Defines WooCommerce specific invoice line flattener logic.
 */
class FlattenerInvoiceLines extends BaseFlattenerInvoiceLines
{
    /**
     * @inheritDoc
     *
     * This override adds support for the woocommerce-bundled-products plugin.
     * This plugin allows to define a base price for the parent but to keep
     * price info on the children, so this should all be added.
     */
    protected function collectInfoFromChildren(array $parent, array $children): array
    {
        if (isset($parent[Meta::BundleId])) {
            $childrenLineAmount = 0.0;
            $childrenLineAmountPrecision = 0.0;
            $copyIncData = isset($parent[Meta::UnitPriceInc]);
            $childrenLineAmountInc = 0.0;
            $childrenLineAmountIncPrecision = 0.0;
            $childrenVatRate = null;
            foreach ($children as $child) {
                // Collect price ex data.
                $childrenLineAmount += $child[Tag::UnitPrice] * $child[Tag::Quantity];
                $childrenLineAmountPrecision += $child[Meta::PrecisionUnitPrice] * $child[Tag::Quantity];

                // Collect price inc data.
                if (isset($child[Meta::UnitPriceInc])) {
                    $childrenLineAmountInc += $child[Meta::UnitPriceInc] * $child[Tag::Quantity];
                    $childrenLineAmountIncPrecision += $child[Meta::PrecisionUnitPrice] * $child[Tag::Quantity];
                } else {
                    // Price inc data is missing on a child line: do not copy.
                    $copyIncData = false;
                }

                // Collect vat rate data.
                if (empty($child[Tag::VatRate])) {
                    // No vat rate on 1 of the children: do not assume they are
                    // all the same. However, we may ignore this line if it is
                    // an empty price line, i.e. just an informative line.
                    if (!Number::isZero($child[Tag::UnitPrice])) {
                        $childrenVatRate = false;
                    }
                } elseif ($childrenVatRate === null) {
                    // 1st vat rate encountered: set it to this vat rate.
                    $childrenVatRate = $child[Tag::VatRate];
                } elseif ($childrenVatRate != $child[Tag::VatRate]) {
                    // Different vat rates on children: do not use.
                    $childrenVatRate = false;
                }
            }

            // Adjust parent amount and precision with child data.
            $parent[Tag::UnitPrice] +=  $childrenLineAmount / $parent[Tag::Quantity];
            $parent[Meta::PrecisionUnitPrice] +=  $childrenLineAmountPrecision / $parent[Tag::Quantity];
            $parent[Meta::BundleChildrenLineAmount] =  $childrenLineAmount;
            $parent[Meta::PrecisionBundleChildrenLineAmount] =  $childrenLineAmountPrecision;

            // Adjust parent amount inc and precision with child data.
            if ($copyIncData) {
                $parent[Meta::UnitPriceInc] += $childrenLineAmountInc / $parent[Tag::Quantity];
                $parent[Meta::PrecisionUnitPrice] += $childrenLineAmountIncPrecision / $parent[Tag::Quantity];
                $parent[Meta::BundleChildrenLineAmountInc] = $childrenLineAmountInc;
                $parent[Meta::PrecisionBundleChildrenLineAmountInc] = $childrenLineAmountIncPrecision;
            }

            // Copy vat rate of children to parent (if all children have the
            // same vat rate).
            if (empty($parent[Tag::VatRate]) && !empty($childrenVatRate)) {
                $parent[Tag::VatRate] = $childrenVatRate;
                $parent[Meta::VatRateSource] = Completor::VatRateSource_Copied_From_Children;
            }
        }
        return parent::collectInfoFromChildren($parent, $children);
    }
}
