<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Invoice;

use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\FlattenerInvoiceLines as BaseFlattenerInvoiceLines;

use function count;

/**
 * Defines Magento specific invoice line flattener logic.
 */
class FlattenerInvoiceLines extends BaseFlattenerInvoiceLines
{
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
    protected function correctInfoBetweenParentAndChildren(Line $parent, array $children): void
    {
        parent::correctInfoBetweenParentAndChildren($parent, $children);

        $useParentInfo = false;
        $vatRates = $this->getAppearingVatRates($children);
        if (count($vatRates) === 1) {
            $childrenVatRate = (float) array_key_first($vatRates);
            if ((Number::isZero($childrenVatRate) || $childrenVatRate === $parent->vatRate)
                && !Number::isZero($parent->unitPrice)
            ) {
                $useParentInfo = true;
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
}
