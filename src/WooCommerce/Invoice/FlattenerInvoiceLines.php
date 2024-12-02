<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Invoice;

use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
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
    protected function collectInfoFromChildren(Line $parent, array $children): void
    {
        if ($parent->metadataExists(Meta::BundleId)) {
            $childrenLineAmount = 0.0;
            $childrenLineAmountPrecision = 0.0;
            $copyIncData = $parent->metadataExists(Meta::UnitPriceInc);
            $childrenLineAmountInc = 0.0;
            $childrenLineAmountIncPrecision = 0.0;
            $childrenVatRate = null;
            foreach ($children as $child) {
                // Collect price ex data.
                $childrenLineAmount += $child->unitPrice * $child->quantity;
                $childrenLineAmountPrecision += $child->metadataGet(Meta::PrecisionUnitPrice) * $child->quantity;

                // Collect price inc data.
                if ($child->metadataExists(Meta::UnitPriceInc)) {
                    $childrenLineAmountInc += $child->metadataGet(Meta::UnitPriceInc) * $child->quantity;
                    $childrenLineAmountIncPrecision += $child->metadataGet(Meta::PrecisionUnitPrice) * $child->quantity;
                } else {
                    // Price inc data is missing on a child line: do not copy.
                    $copyIncData = false;
                }

                // Collect vat rate data.
                if (empty($child->vatRate)) {
                    // No vat rate on 1 of the children: do not assume they are
                    // all the same. However, we may ignore this line if it is
                    // an empty price line, i.e. just an informative line.
                    if (!Number::isZero($child->unitPrice)) {
                        $childrenVatRate = false;
                    }
                } elseif ($childrenVatRate === null) {
                    // 1st vat rate encountered: set it to this vat rate.
                    $childrenVatRate = $child->vatRate;
                } elseif (!Number::floatsAreEqual($childrenVatRate, $child->vatRate)) {
                    // Different vat rates on children: do not use.
                    $childrenVatRate = false;
                }
            }

            // Adjust parent amount and precision with child data.
            $parent->unitPrice += $childrenLineAmount / $parent->quantity;
            $parent->metadataSet(
                Meta::PrecisionUnitPrice,
                $parent->metadataGet(Meta::PrecisionUnitPrice) + $childrenLineAmountPrecision / $parent->quantity
            );
            $parent->metadataSet(Meta::BundleChildrenLineAmount, $childrenLineAmount);
            $parent->metadataSet(Meta::PrecisionBundleChildrenLineAmount, $childrenLineAmountPrecision);

            // Adjust parent amount inc and precision with child data.
            if ($copyIncData) {
                $parent->metadataSet(
                    Meta::UnitPriceInc,
                    $parent->metadataGet(Meta::UnitPriceInc) + $childrenLineAmountInc / $parent->quantity
                );
                $parent->metadataSet(
                    Meta::PrecisionUnitPrice,
                    $parent->metadataGet(Meta::PrecisionUnitPrice) + $childrenLineAmountIncPrecision / $parent->quantity
                );
                $parent->metadataSet(Meta::BundleChildrenLineAmountInc, $childrenLineAmountInc);
                $parent->metadataSet(Meta::PrecisionBundleChildrenLineAmountInc, $childrenLineAmountIncPrecision);
            }

            // Copy vat rate of children to parent (if all children have the
            // same vat rate).
            if (empty($parent->vatRate) && !empty($childrenVatRate)) {
                $parent->vatRate = $childrenVatRate;
                $parent->metadataSet(Meta::VatRateSource, VatRateSource::Copied_From_Children);
            }
        }
        parent::collectInfoFromChildren($parent, $children);
    }
}
