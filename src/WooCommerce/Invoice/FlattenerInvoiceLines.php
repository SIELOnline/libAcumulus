<?php
/**
 * @noinspection DuplicatedCode  This is indeed a copy of the original Invoice\FlattenerInvoiceLines.
 */
declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Invoice;

use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\FlattenerInvoiceLines as BaseFlattenerInvoiceLines;
use Siel\Acumulus\Meta;

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
    protected function collectInfoFromChildren(Line $parent, array $children): Line
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
                $childrenLineAmount += $child[Fld::UnitPrice] * $child[Fld::Quantity];
                $childrenLineAmountPrecision += $child[Meta::PrecisionUnitPrice] * $child[Fld::Quantity];

                // Collect price inc data.
                if (isset($child[Meta::UnitPriceInc])) {
                    $childrenLineAmountInc += $child[Meta::UnitPriceInc] * $child[Fld::Quantity];
                    $childrenLineAmountIncPrecision += $child[Meta::PrecisionUnitPrice] * $child[Fld::Quantity];
                } else {
                    // Price inc data is missing on a child line: do not copy.
                    $copyIncData = false;
                }

                // Collect vat rate data.
                if (empty($child[Fld::VatRate])) {
                    // No vat rate on 1 of the children: do not assume they are
                    // all the same. However, we may ignore this line if it is
                    // an empty price line, i.e. just an informative line.
                    if (!Number::isZero($child[Fld::UnitPrice])) {
                        $childrenVatRate = false;
                    }
                } elseif ($childrenVatRate === null) {
                    // 1st vat rate encountered: set it to this vat rate.
                    $childrenVatRate = (float) $child[Fld::VatRate];
                } elseif (!Number::floatsAreEqual((float) $childrenVatRate, (float) $child[Fld::VatRate])) {
                    // Different vat rates on children: do not use.
                    $childrenVatRate = false;
                }
            }

            // Adjust parent amount and precision with child data.
            $parent[Fld::UnitPrice] +=  $childrenLineAmount / $parent[Fld::Quantity];
            $parent[Meta::PrecisionUnitPrice] +=  $childrenLineAmountPrecision / $parent[Fld::Quantity];
            $parent[Meta::BundleChildrenLineAmount] =  $childrenLineAmount;
            $parent[Meta::PrecisionBundleChildrenLineAmount] =  $childrenLineAmountPrecision;

            // Adjust parent amount inc and precision with child data.
            if ($copyIncData) {
                $parent[Meta::UnitPriceInc] += $childrenLineAmountInc / $parent[Fld::Quantity];
                $parent[Meta::PrecisionUnitPrice] += $childrenLineAmountIncPrecision / $parent[Fld::Quantity];
                $parent[Meta::BundleChildrenLineAmountInc] = $childrenLineAmountInc;
                $parent[Meta::PrecisionBundleChildrenLineAmountInc] = $childrenLineAmountIncPrecision;
            }

            // Copy vat rate of children to parent (if all children have the
            // same vat rate).
            if (empty($parent[Fld::VatRate]) && !empty($childrenVatRate)) {
                $parent[Fld::VatRate] = $childrenVatRate;
                $parent[Meta::VatRateSource] = VatRateSource::Copied_From_Children;
            }
        }
        return parent::collectInfoFromChildren($parent, $children);
    }
}
