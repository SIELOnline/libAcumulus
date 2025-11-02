<?php
/**
 * @noinspection GrazieInspection False positives about phrases being seen as questions.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Meta;

use function count;
use function sprintf;
use function strlen;

/**
 * The invoice lines flattener flattens hierarchical invoice lines.
 *
 * This class flattens the invoice lines (recursively). If an invoice line has
 * child lines they are either merged into the parent line or added as separate
 * invoice lines at the same level as the parent invoice line.
 *
 * The ideas and reasoning behind hierarchical lines and subsequently flattening
 * it :
 * - To provide the user with some flexibility in how options, variants,
 *   composed products, or bundles are shown on the invoice, the Creator should
 *   create a raw invoice with all options, etc. on separate child lines.
 * - Acumulus only accepts flat lines, so eventually the lines must be
 *   flattened.
 * - There are a number of settings that determine how this is done: e.g. show
 *   all on 1 line; show indented child lines; do not show at all (because e.g.
 *   child lines are only used internally for price calculations or stock
 *   keeping).
 * - If a child vat rate differs from that of the parent, lines may not be
 *   merged. The library takes this into account and ignores any settings in
 *   this case.
 * - While flattening, especially when merging the children into the parent,
 *   price info on the children gets lost, so the flattening phase might have to
 *   fetch it from the children and add it to the parent.
 * - The library has various merge strategies (regarding copying, ignoring or
 *   adding price info) already implemented. You might have to override the
 *   flattener to select the strategy that applies to your shop.
 *
 * @todo: Shortly describe strategies and its methods to simplify overriding and
 *   choosing the correct strategy.
 */
class FlattenerInvoiceLines
{
    protected Config $config;
    protected int $parentIndex = 1;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Completes the invoice lines by flattening them.
     */
    public function complete(Invoice $invoice): void
    {
        $this->flattenInvoiceLines($invoice);
    }

    /**
     * Flattens the invoice lines for variants or composed products.
     *
     * Invoice lines may recursively contain other invoice lines to indicate
     * that a product has variant lines or is a composed product (if supported
     * by the web shop).
     *
     * With composed or variant child lines, amounts may appear twice. This will
     * also be corrected by this method.
     *
     * @param \Siel\Acumulus\Data\Invoice $invoice
     */
    protected function flattenInvoiceLines(Invoice $invoice): void
    {
        $invoice->replaceLines($this->flattenLines($invoice->getLines()));
    }

    /**
     * Flattens a set of lines.
     *
     * @param Line[] $lines
     *
     * @return Line[]
     *   The flattened set of lines, so may be larger than $lines
     */
    protected function flattenLines(array $lines): array
    {
        $chunks = [];
        foreach ($lines as $line) {
            $chunks[] = [$line];
            $chunks[] = $this->flattenLine($line);
        }
        return array_merge(...$chunks);
    }

    /**
     * Flattens 1 invoice line.
     *
     * @param \Siel\Acumulus\Data\Line $line
     *
     * @return Line[]
     *   The flattened lines, if children are kept this is an array with > 1 lines.
     */
    protected function flattenLine(Line $line): array
    {
        $children = [];
        // Ignore children if we do not want to show them.
        // If it has children, flatten them and determine how to add them.
        if (count($line->getChildren()) > 0) {
            $children = $this->flattenLines($line->getChildren());
            // Remove children from parent.
            $line->removeChildren();
            // Determine whether to add them at all and if so whether
            // to add them as a single line or as separate lines.
            if ($this->keepSeparateLines($line, $children)) {
                // Keep them separate but perform the following actions:
                // - Allow for some web shop specific corrections.
                // - Add metadata to relate parent and children.
                // - Indent product descriptions.
                $this->correctInfoBetweenParentAndChildren($line, $children);
            } else {
                // Merge the children into the parent product:
                // - Allow for some web shop specific corrections.
                // - Add metadata about removed children.
                // - Add text from children, e.g. the chosen variants, to
                //   the parent.
                $this->collectInfoFromChildren($line, $children);
                // Delete children as their info is merged into the parent.
                $children = [];
            }
        }
        return $children;
    }

    /**
     * Determines whether to keep the children on separate lines.
     *
     * This base implementation decides based on:
     * - Whether all lines have the same VAT rate (different VAT rates => keep)
     * - The settings for:
     *   * optionsShow
     *   * optionsAllOn1Line
     *   * optionsAllOnOwnLine
     *   * optionsMaxLength
     *
     * Override if you want other logic to decide on.
     *
     * @param Line $parent
     *   The parent invoice line.
     * @param Line[] $children
     *   A flattened array of child invoice lines.
     *
     * @return bool
     *   True if the lines should remain separate, false otherwise.
     */
    protected function keepSeparateLines(Line $parent, array $children): bool
    {
        $invoiceSettings = $this->config->getInvoiceSettings();
        if (!$this->haveSameVatRate($children)) {
            // We MUST keep them separate to retain correct vat info.
            $separateLines = true;
        } elseif (!$invoiceSettings['optionsShow']) {
            // Do not show the children info at all, but do collect price info.
            $separateLines = false;
        } elseif (count($children) <= $invoiceSettings['optionsAllOn1Line']) {
            $separateLines = false;
        } elseif (count($children) >= $invoiceSettings['optionsAllOnOwnLine']) {
            $separateLines = true;
        } else {
            $childrenText = $this->getMergedLinesText($parent, $children);
            $separateLines = strlen($childrenText) > $invoiceSettings['optionsMaxLength'];
        }
        return $separateLines;
    }

    /**
     * Returns a 'product' field for the merged lines.
     *
     * @param Line $parent
     *   The parent invoice line.
     * @param Line[] $children
     *   The child invoice lines.
     *
     * @return string
     *   The concatenated product texts.
     */
    protected function getMergedLinesText(Line $parent, array $children): string
    {
        $childrenTexts = [];
        foreach ($children as $child) {
            $childrenTexts[] = $child->product;
        }
        $childrenText = ' (' . implode(', ', $childrenTexts) . ')';
        return $parent->product . $childrenText;
    }

    /**
     * Allows correcting or removing info between or from parent and child
     * lines.
     *
     * This method is called before the child lines are added to the set of
     * invoice lines.
     *
     * This base implementation performs the following actions:
     * - Add metadata to parent and children to link them to each other.
     * - Indent product descriptions of the children.
     *
     * Situations that may have to be covered by web shop specific overrides:
     * - Price info only on parent.
     * - Price info only on children.
     * - Price info both on parent and children and the amounts are doubled.
     *   (price on parent is the sum of the prices of the children).
     * - Price info both on parent and children but the amounts are not doubled
     *   (base price on parent plus extra/fewer charges for options on
     *   children).
     *
     * @param Line $parent
     *   The parent invoice line.
     * @param Line[] $children
     *   The child invoice lines.
     */
    protected function correctInfoBetweenParentAndChildren(Line $parent, array $children): void
    {
        if (count($children) !== 0) {
            $parent->metadataSet(Meta::Parent, $this->parentIndex);
            $parent->metadataSet(Meta::NumberOfChildren, count($children));
            foreach ($children as $child) {
                $child->product = $this->indentDescription($child->product);
                $child->metadataSet(Meta::ParentIndex, $this->parentIndex);
            }
            $this->parentIndex++;
        }
    }

    /**
     * Allows collecting info from the child lines and add it to the parent.
     *
     * This method is called before the child lines are merged into the parent
     * invoice line.
     *
     * This base implementation merges the product descriptions from the child
     * lines into the parent product description.
     *
     * Situations that may have to be covered by web shop specific overrides:
     * - Price info only on parent.
     * - Price info only on children.
     * - Price info both on parent and children and the amounts appear twice
     *   (price on parent is the sum of the prices of the children).
     * - Price info both on parent and children but the amounts are not doubled
     *   (base price on parent plus extra charges for options on children).
     *
     * Examples;
     * - There are amounts on the children but as they are going to be merged
     *   into the parent they would get lost.
     *
     * @param Line $parent
     *   The parent invoice line.
     * @param Line[] $children
     *   The child invoice lines.
     */
    protected function collectInfoFromChildren(Line $parent, array $children): void
    {
        $invoiceSettings = $this->config->getInvoiceSettings();
        if (!$invoiceSettings['optionsShow']) {
            $parent->metadataSet(Meta::ChildrenNotShown, count($children));
        } else {
            $parent->product = $this->getMergedLinesText($parent, $children);
            $parent->metadataSet(Meta::ChildrenMerged, count($children));
        }
    }

    /**
     * Corrects info between parent and child lines.
     *
     * This method should be called when:
     * - Child invoice lines are kept separately.
     * - Price info only on parent.
     *
     * What do we do in this situation:
     * - Copy vat rate info from parent to children (as it may be empty on the children).
     * - Remove price info from children (just to be sure).
     *
     * Known usages:
     * - Magento.
     *
     * @param Line $parent
     *   The parent invoice line.
     * @param Line[] $children
     *   The child invoice lines.
     */
    protected function keepChildrenAndPriceOnParentOnly(Line $parent, array $children): void
    {
        $this->copyVatInfoToChildren($parent, $children);
        $this->removePriceInfoFromChildren($children);
    }

    /**
     * Corrects info between parent and child lines.
     *
     * This method should be called when:
     * - Child invoice lines are kept separately.
     * - Price info is on the children, not on the parent.
     *
     * What do we do in this situation:
     * - Copy vat rate info from 1 child to the parent (as, e.g. in Magento, it may be
     *   empty on the parent).
     * - Remove price info from parent (just to be sure).
     *
     * Known usages:
     * - Magento.
     *
     * @param Line $parent
     *   The parent invoice line.
     * @param Line[] $children
     *   The child invoice lines.
     */
    protected function keepChildrenAndPriceOnChildrenOnly(Line $parent, array $children): void
    {
        $this->copyVatInfoToParent($parent, $children);
        $this->removePriceInfoFromParent($parent);
    }

    /**
     * Corrects info between parent and child lines.
     *
     * This method should be called when:
     * - Child invoice lines are kept separately.
     * - Price info is on the parent and children, but they double each other.
     *
     * What do we do in this situation:
     * - Remove price info from parent to ensure that the amount appears only once.
     * - If the vat rate on the parent is absent or incorrect, it should best be set to
     *   the maximum vat rate appearing on the children.
     *
     * We could remove price info from the children instead, but that would be
     * wrong if the children do not all have the same vat rate.
     *
     * Known usages:
     * - None so far.
     *
     * @param Line $parent
     *   The parent invoice line.
     * @param Line[] $children
     *   The child invoice lines.
     *
     * @noinspection PhpUnused
     */
    protected function keepChildrenAndPriceOnParentAndChildren(Line $parent, array $children): void
    {
        if (!Completor::isCorrectVatRate($parent->metadataGet(Meta::VatRateSource)) || Number::isZero($parent->vatRate)) {
            $this->copyVatInfoToParent($parent, $children);
        }
        $this->removePriceInfoFromParent($parent);
    }

    /**
     * Corrects info between parent and child lines.
     *
     * This method should be called when:
     * - Child invoice lines are kept separately.
     * - Price info is on the parent and children, but these amounts do not
     *   double each other (base price + extra prices for more expensive
     *   options).
     *
     * What do we do in this situation:
     * - Nothing, price and vat info are considered to be correct on all lines.
     *
     * Known usages:
     * - None so far.
     *
     * @param Line $parent
     *   The parent invoice line.
     * @param Line[] $children
     *   The child invoice lines.
     *
     * @noinspection PhpUnused
     */
    protected function keepChildrenAndPriceOnParentPlusChildren(Line $parent, array $children): void
    {
    }

    /**
     * Copies vat info from the parent to all children.
     *
     * In Magento, VAT info on the children may contain a 0 vat rate. To correct
     * this, we copy the vat information (rate, source, correction info).
     *
     * @param Line $parent
     *   The parent invoice line.
     * @param Line[] $children
     *   The child invoice lines.
     */
    protected function copyVatInfoToChildren(Line $parent, array $children): void
    {
        static $vatMetaInfoTags = [
            Meta::VatRateMin,
            Meta::VatRateMax,
            Meta::VatRateLookup,
            Meta::VatRateLookupLabel,
            Meta::VatRateLookupSource,
            Meta::VatRateLookupMatches,
            Meta::VatClassId,
            Meta::VatClassName,
        ];

        foreach ($children as $child) {
            if (isset($parent->vatRate)) {
                $child->vatRate = $parent->vatRate;
            }
            $child->metadataSet(Meta::VatAmount, 0);
            foreach ($vatMetaInfoTags as $tag) {
                $child->metadataRemove($tag);
            }

            if (Completor::isCorrectVatRate($parent->metadataGet(Meta::VatRateSource))) {
                $child->metadataSet(Meta::VatRateSource, VatRateSource::Copied_From_Parent);
            } else {
                // The parent does not yet have correct vat rate info, so also
                // copy the metadata to the child, so later phases can also
                // correct the children.
                $child->metadataSet(Meta::VatRateSource, $parent->metadataGet(Meta::VatRateSource));
                foreach ($vatMetaInfoTags as $tag) {
                    if ($parent->metadataExists($tag)) {
                        $child->metadataCopy($tag, $parent->getMetadata()->getMetadataValue($tag));
                    }
                }
            }

            $child->metadataSet(Meta::LineVatAmount, 0);
            $child->metadataRemove(Meta::LineDiscountVatAmount);
        }
    }

    /**
     * Copies vat info to the parent.
     *
     * This prevents that amounts appear twice on the invoice.
     *
     * @param Line $parent
     *   The parent invoice line.
     * @param Line[] $children
     *   The child invoice lines.
     */
    protected function copyVatInfoToParent(Line $parent, array $children): void
    {
        $parent->metadataSet(Meta::VatAmount, 0);
        // Copy vat rate info from a child when the parent has no vat rate info.
        if (empty($parent->vatRate) || Number::isZero($parent->vatRate)) {
            $parent->vatRate = CompletorInvoiceLines::getMaxAppearingVatRate($children, $index);
            $parent->metadataSet(Meta::VatRateSource, VatRateSource::Copied_From_Children);
            if ($children[$index]->metadataExists(Meta::VatRateMin)) {
                $parent->metadataSet(Meta::VatRateMin, $children[$index]->metadataGet(Meta::VatRateMin));
            } else {
                $parent->metadataRemove(Meta::VatRateMin);
            }
            if ($children[$index]->metadataExists(Meta::VatRateMax)) {
                $parent->metadataSet(Meta::VatRateMax, $children[$index]->metadataGet(Meta::VatRateMax));
            } else {
                $parent->metadataRemove(Meta::VatRateMax);
            }
        }
        $parent->metadataSet(Meta::LineVatAmount, 0);
        $parent->metadataRemove(Meta::LineDiscountVatAmount);
    }

    /**
     * Removes price info from all children.
     *
     * This can prevent that amounts appear twice on the invoice. This can only
     * be done if all children have the same vat rate as the parent, otherwise
     * the price (and vat) info should remain on the children and be removed
     * from the parent.
     *
     * @param Line[] $children
     *   The child invoice lines.
     */
    protected function removePriceInfoFromChildren(array $children): void
    {
        foreach ($children as $child) {
            $child->unitPrice = 0;
            $child->metadataSet(Meta::UnitPriceInc, 0);
            $child->metadataRemove(Meta::LineAmount);
            $child->metadataRemove(Meta::LineAmountInc);
            $child->metadataRemove(Meta::LineDiscountAmount);
            $child->metadataRemove(Meta::LineDiscountAmountInc);
        }
    }

    /**
     * Removes price info from the parent.
     *
     * This can prevent that amounts appear twice on the invoice.
     *
     * @param Line $parent
     *   The parent invoice line.
     */
    protected function removePriceInfoFromParent(Line $parent): void
    {
        $parent->unitPrice = 0;
        $parent->metadataSet(Meta::UnitPriceInc, 0);
        $parent->metadataRemove(Meta::LineAmount);
        $parent->metadataRemove(Meta::LineAmountInc);
        $parent->metadataRemove(Meta::LineDiscountAmount);
        $parent->metadataRemove(Meta::LineDiscountAmountInc);
    }

    /**
     * Indents a product description (to indicate that it is part of a bundle).
     *
     * @param string $description
     *   The description to indent.
     *
     * @return string
     *   The indented product description.
     */
    protected function indentDescription(string $description): string
    {
        if (preg_match('/^ *- /', $description)) {
            $description = '  ' . $description;
        } else {
            $description = ' - ' . $description;
        }
        return $description;
    }

    /**
     * Returns a list of vat rates that actually appear in the given lines.
     *
     * @param Line[] $lines
     *   an array of invoice lines.
     *
     * @return array
     *   An array with the vat rates as key and the number of times they appear
     *   in the invoice lines as value.
     */
    protected function getAppearingVatRates(array $lines): array
    {
        $vatRates = [];
        foreach ($lines as $line) {
            if (isset($line->vatRate)) {
                $vatRate = sprintf('%.1f', $line->vatRate);
                if (!isset($vatRates[$vatRate])) {
                    $vatRates[$vatRate] = 0;
                }
                $vatRates[$vatRate]++;
            }
        }
        return $vatRates;
    }

    /**
     * Returns whether the lines have different vat rates.
     *
     * @param Line[] $lines
     *   The lines to compare.
     *
     * @return bool
     *   True if the lines have different vat rates.
     */
    protected function haveSameVatRate(array $lines): bool
    {
        $vatRates = $this->getAppearingVatRates($lines);
        return count($vatRates) <= 1;
    }
}
