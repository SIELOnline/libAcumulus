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
 *
 * @noinspection TypeUnsafeComparisonInspection
 * @noinspection PhpMissingStrictTypesDeclarationInspection
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection DuplicatedCode  This is a copy of the old Completor.
 */

namespace Siel\Acumulus\Magento\Invoice;

use Siel\Acumulus\Data\Line;
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
    protected function correctInfoBetweenParentAndChildren(Line $parent, array &$children): void
    {
        parent::correctInfoBetweenParentAndChildren($parent, $children);

        $useParentInfo = false;
        $vatRates = $this->getAppearingVatRates($children);
        if (count($vatRates) === 1) {
            $childrenVatRate = array_key_first($vatRates);
            if ((Number::isZero($childrenVatRate) || $childrenVatRate == $parent[Tag::VatRate])
                && !Number::isZero($parent[Tag::UnitPrice])
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
