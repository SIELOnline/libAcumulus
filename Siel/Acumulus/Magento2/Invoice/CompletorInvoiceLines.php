<?php
namespace Siel\Acumulus\Magento2\Invoice;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\CompletorInvoiceLines as BaseCompletorInvoiceLines;

/**
 * Class CompletorInvoiceLines completes the invoice lines.
 */
class CompletorInvoiceLines extends BaseCompletorInvoiceLines
{
    /**
     * {@inheritdoc}
     */
    protected function keepSeparateLines(array $parent, array $children)
    {
        if ($this->isChildSameAsParent($parent, $children)) {
            return false;
        }
        return parent::keepSeparateLines($parent, $children);
    }

    protected function getMergedLinesText(array $parent, array $children)
    {
        if ($this->isChildSameAsParent($parent, $children)) {
            $child = reset($children);
            return $child['product'];
        }
        return parent::getMergedLinesText($parent, $children);
    }

    protected function correctInfoBetweenParentAndChildren(array &$parent, array &$children)
    {
        $useParentInfo = false;

        // Check if:
        // - All children have the same VAT rate AND
        // - AND this vat rate is the same as the parent VAT rate or is empty
        // - AND that the bundle has price info
        // If so, we keep the info on the parent and remove it from the child
        // lines to prevent accounting amounts twice.
        $vatRates = $this->getAppearingVatRates($children);
        if (count($vatRates) === 1) {
            $childrenVatRate = reset($vatRates);
            if ((Number::isZero($childrenVatRate) || $childrenVatRate == $parent['vatrate'])) {
                if (!Number::isZero($parent['unitprice'])) {
                    $useParentInfo = TRUE;
                }
            }
        }
        if ($useParentInfo) {
            $children = $this->removePriceInfoFromChildren($parent, $children);
        } else {
            // All price and vat info remains on the child lines. Make sure that
            // no price info is left on the bundle line.
            $parent = $this->removePriceInfoFromParent($parent, $children);
        }
    }

    /**
     * Returns whether a single child line is actually the same as its parent.
     *
     * If:
     * - there is exactly 1 child line
     * - for the same item number and quantity
     * - with no price info on the child
     * We seem to be processing a configurable product that for some reason
     * appears twice: do not add the child, but copy the product description
     * to the result as it contains more option descriptions.
     *
     * @param array $parent
     * @param array[] $children
     *
     * @return bool
     *   True if the single child line is actually the same as its parent.
     */
    protected function isChildSameAsParent(array $parent, array $children)
    {
        if (count($children) === 1) {
            $child = reset($children);
            if ($parent['itemnumber'] === $child['itemnumber']
              && $parent['quantity'] === $child['quantity']
              && Number::isZero($child['unitprice'])
            ) {
                return true;
            }
        }
        return false;
    }
}
