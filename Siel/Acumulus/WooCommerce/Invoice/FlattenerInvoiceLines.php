<?php
namespace Siel\Acumulus\WooCommerce\Invoice;

use Siel\Acumulus\Invoice\Completor;
use Siel\Acumulus\Invoice\FlattenerInvoiceLines as BaseFlattenerInvoiceLines;

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
    protected function collectInfoFromChildren(array $parent, array $children)
    {
        if (isset($parent['meta-bundle-id'])) {
            $childrenLineAmount = 0.0;
            $childrenLinePrecision = 0.0;
            $copyIncData = isset($parent['unitpriceinc']);
            $childrenLineAmountInc = 0.0;
            $childrenLinePrecisionInc = 0.0;
            $childrenVatRate = null;
            foreach ($children as $child) {
                // Collect price ex data.
                $childrenLineAmount += $child['unitprice'] * $child['quantity'];
                $childrenLinePrecision += $child['meta-unitprice-precision'] * $child['quantity'];

                // Collect price inc data.
                if (isset($child['unitpriceinc'])) {
                    $childrenLineAmountInc += $child['unitpriceinc'] * $child['quantity'];
                    $childrenLinePrecisionInc += $child['meta-unitpriceinc-precision'] * $child['quantity'];
                } else {
                    // Price inc data is missing on a child line: do not copy.
                    $copyIncData = false;
                }

                // Collect vat rate data.
                if (empty($child['vatrate'])) {
                    // no vatrate on 1 of the children: do not assume they are
                    // all the same.
                    $childrenVatRate = false;
                } elseif ($childrenVatRate === null) {
                    // 1st vat rate encountered: set it to this vat rate.
                    $childrenVatRate = $child['vatrate'];
                } elseif ($childrenVatRate != $child['vatrate']) {
                    // Different vat rates on children: do not use.
                    $childrenVatRate = false;
                }
            }

            // Adjust parent amount and precision with child data.
            $parent['unitprice'] +=  $childrenLineAmount / $parent['quantity'];
            $parent['meta-unitprice-precision'] +=  $childrenLinePrecision / $parent['quantity'];
            $parent['meta-bundle-children-line-amount'] =  $childrenLineAmount;
            $parent['meta-bundle-children-line-precision'] =  $childrenLinePrecision;

            // Adjust parent amount inc and precision with child data.
            if ($copyIncData) {
                $parent['unitpriceinc'] += $childrenLineAmountInc / $parent['quantity'];
                $parent['meta-unitpriceinc-precision'] += $childrenLinePrecisionInc / $parent['quantity'];
                $parent['meta-bundle-children-line-amountinc'] = $childrenLineAmountInc;
                $parent['meta-bundle-children-line-precision-inc'] = $childrenLinePrecisionInc;
            }

            // Copy vat rate of children to parent (if all children have the
            // same vat rate).
            if (empty($parent['vatrate']) && !empty($childrenVatRate)) {
                $parent['vatrate'] = $childrenVatRate;
                $parent['meta-vatrate-source'] = Completor::VatRateSource_Copied_From_Children;
            }
        }
        return parent::collectInfoFromChildren($parent, $children);
    }
}
