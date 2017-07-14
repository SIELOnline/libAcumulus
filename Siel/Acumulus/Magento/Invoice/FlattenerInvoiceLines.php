<?php
namespace Siel\Acumulus\Magento\Invoice;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\FlattenerInvoiceLines as BaseFlattenerInvoiceLines;
use Siel\Acumulus\Tag;

/**
 * Defines Magento specific invoice line flattener logic.
 */
class FlattenerInvoiceLines extends BaseFlattenerInvoiceLines
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
            return $child[Tag::Product];
        }
        return parent::getMergedLinesText($parent, $children);
    }

    /**
     * {@inheritdoc}
     *
     * This Magento override decides whether to keep the info on the parent or
     * the children based on:
     * If:
     * - All children have the same VAT rate AND
     * - This vat rate is the same as the parent VAT rate or is empty AND
     * - That the parent has price info.
     * We keep the info on the parent and remove it from the children to prevent
     * accounting amounts twice.
     */
    protected function correctInfoBetweenParentAndChildren(array &$parent, array &$children)
    {
        parent::correctInfoBetweenParentAndChildren($parent, $children);

        $useParentInfo = false;
        $vatRates = $this->getAppearingVatRates($children);
        if (count($vatRates) === 1) {
            reset($vatRates);
            $childrenVatRate = key($vatRates);
            if ((Number::isZero($childrenVatRate) || $childrenVatRate == $parent[Tag::VatRate])) {
                if (!Number::isZero($parent[Tag::UnitPrice])) {
                    $useParentInfo = true;
                }
            }
        }

        if ($useParentInfo) {
            // All price and vat info remains on the parent line. Make sure that
            // no price info is left on the child invoice lines.
            $this->keepChildrenAndPriceOnParentOnly($parent, $children);
        } else {
            // All price and vat info remains on the child invoice lines. Make
            // sure that no price info is left on the parent invoice line.
            $this->keepChildrenAndPriceOnChildrenOnly($parent, $children);
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
            if ($parent[Tag::ItemNumber] === $child[Tag::ItemNumber]
              && $parent[Tag::Quantity] === $child[Tag::Quantity]
              && Number::isZero($child[Tag::UnitPrice])
            ) {
                return true;
            }
        }
        return false;
    }
}
