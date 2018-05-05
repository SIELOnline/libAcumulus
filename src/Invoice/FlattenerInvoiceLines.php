<?php
namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

/**
 * The invoice lines flattener flattens hierarchical invoice lines.
 *
 * This class flattens the invoice lines (recursively). If an invoice line has
 * child lines they are either merged into the parent line or added as separate
 * invoice lines at the same level as the parent invoice line.
 *
 * The ideas and reasoning behind hierarchical lines and subsequently flattening
 * it :
 * - To provide the use some flexibility in how options, variants, composed
 *   products, or bundles are shown on the invoice, the Creator should create a
 *   raw invoice with all options, etc. on separate child lines.
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
 * @todo: shortly describe strategies and its methods to simplify overriding and
 *   choosing the correct strategy.
 */
class FlattenerInvoiceLines
{
    /** @var \Siel\Acumulus\Config\Config  */
    protected $config;

    protected $parentIndex = 1;

    /**
     * Constructor.
     *
     * @param \Siel\Acumulus\Config\Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Completes the invoice lines by flattening them.
     *
     * @param array[] $invoiceLines
     *   The invoice lines to flatten.
     *
     * @return array[]
     *   The flattened invoice lines.
     */
    public function complete(array $invoiceLines)
    {
        $invoiceLines = $this->flattenInvoiceLines($invoiceLines);
        return $invoiceLines;
    }

    /**
     * Flattens the invoice lines for variants or composed products.
     *
     * Invoice lines may recursively contain other invoice lines to indicate
     * that a product has variant lines or is a composed product (if supported
     * by the webshop).
     *
     * With composed or variant child lines, amounts may appear twice. This will
     * also be corrected by this method.
     *
     * @param array[] $lines
     *   The lines to flatten.
     *
     * @return array[]
     *   The flattened lines.
     */
    protected function flattenInvoiceLines(array $lines)
    {
        $result = array();

        foreach ($lines as $line) {
            $children = null;
            // Ignore children if we do not want to show them.
            // If it has children, flatten them and determine how to add them.
            if (array_key_exists(Meta::ChildrenLines, $line)) {
                $children = $this->flattenInvoiceLines($line[Meta::ChildrenLines]);
                // Remove children from parent.
                unset($line[Meta::ChildrenLines]);
                // Determine whether to add them at all and if so whether
                // to add them as a single line or as separate lines.
                if ($this->keepSeparateLines($line, $children)) {
                    // Keep them separate but perform the following actions:
                    // - Allow for some web shop specific corrections.
                    // - Add meta data to relate parent and children.
                    // - Indent product descriptions.
                    $this->correctInfoBetweenParentAndChildren($line, $children);
                } else {
                    // Merge the children into the parent product:
                    // - Allow for some web shop specific corrections.
                    // - Add meta data about removed children.
                    // - Add text from children, eg. chosen variants, to parent.
                    $line = $this->collectInfoFromChildren($line, $children);
                    // Delete children as their info is merged into the parent.
                    $children = null;
                }
            }

            // Add the line and its children, if any.
            $result[] = $line;
            if (!empty($children)) {
                $result = array_merge($result, $children);
            }
        }

        return $result;
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
     * @param array $parent
     *   The parent invoice line.
     * @param array[] $children
     *   A flattened array of child invoice lines.
     *
     * @return bool
     *   True if the lines should remain separate, false otherwise.
     */
    protected function keepSeparateLines(array $parent, array $children)
    {
        $invoiceSettings = $this->config->getInvoiceSettings();
        if (!$this->haveSameVatRate($children)) {
            // We MUST keep them separate to retain correct vat info.
            $separateLines = true;
        } elseif (!$invoiceSettings['optionsShow']) {
            // Do not kshow the children info at all, but do collect price info.
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
     * @param array $parent
     *   The parent invoice line.
     * @param array[] $children
     *   The child invoice lines.
     *
     * @return string
     *   The concatenated product texts.
     */
    protected function getMergedLinesText(array $parent, array $children)
    {
        $childrenTexts = array();
        foreach ($children as $child) {
            $childrenTexts[] = $child[Tag::Product];
        }
        $childrenText = ' (' . implode(', ', $childrenTexts) . ')';
        return $parent[Tag::Product] .  $childrenText;
    }

    /**
     * Allows to correct or remove info between or from parent and child lines.
     *
     * This method is called before the child lines are added to the set of
     * invoice lines.
     *
     * This base implementation performs the following actions:
     * - Add meta data to parent and children to link them to each other.
     * - Indent product descriptions of the children.
     *
     * Situations that may have to be covered by web shop specific overrides:
     * - Price info only on parent.
     * - Price info only on children.
     * - Price info both on parent and children and the amounts are doubled.
     *   (price on parent is the sum of the prices of the children).
     * - Price info both on parent and children but the amounts are not doubled
     *   (base price on parent plus extra/less charges for options on children).
     *
     * @param array $parent
     *   The parent invoice line.
     * @param array[] $children
     *   The child invoice lines.
     */
    protected function correctInfoBetweenParentAndChildren(array &$parent, array &$children)
    {
        if (!empty($children)) {
            $parent[Meta::Parent] = $this->parentIndex;
            $parent[Meta::NumberOfChildren] = count($children);
            foreach ($children as &$child) {
                $child[Tag::Product] = $this->indentDescription($child[Tag::Product]);
                $child[Meta::ParentIndex] = $this->parentIndex;
            }
            $this->parentIndex++;
        }
    }

    /**
     * Allows to collect info from the child lines and add it to the parent.
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
     * @param array $parent
     *   The parent invoice line.
     * @param array[] $children
     *   The child invoice lines.
     *
     * @return array
     *   The parent line extended with the collected info.
     */
    protected function collectInfoFromChildren(array $parent, array $children)
    {
        $invoiceSettings = $this->config->getInvoiceSettings();
        if (!$invoiceSettings['optionsShow']) {
            $parent[Meta::ChildrenNotShown] = count($children);
        } else {
            $parent[Tag::Product] = $this->getMergedLinesText($parent, $children);
            $parent[Meta::ChildrenMerged] = count($children);
        }
        return $parent;
    }

    /**
     * Corrects info between parent and child lines.
     *
     * This method should be called when:
     * - Child invoice lines are kept separately.
     * - Price info only on parent.
     *
     * What do we do in this situation:
     * - Copy vat rate info from parent to children (may be empty on the
     *   children).
     * - Remove price info from children (just to be sure).
     *
     * Known usages:
     * - Magento (2.1+ only?).
     *
     * @param array $parent
     *   The parent invoice line.
     * @param array[] $children
     *   The child invoice lines.
     */
    protected function keepChildrenAndPriceOnParentOnly(array &$parent, array &$children)
    {
       $children = $this->copyVatInfoToChildren($parent, $children);
       $children = $this->removePriceInfoFromChildren($children);
    }

    /**
     * Corrects info between parent and child lines.
     *
     * This method should be called when:
     * - Child invoice lines are kept separately.
     * - Price info is on the children, not on the parent.
     *
     * What do we do in this situation:
     * - Copy vat rate info from 1 child to the parent (as in eg. Magento, it
     *   may be empty on the parent).
     * - Remove price info from parent (just to be sure).
     *
     * Known usages:
     * - Magento.
     *
     * @param array $parent
     *   The parent invoice line.
     * @param array[] $children
     *   The child invoice lines.
     */
    protected function keepChildrenAndPriceOnChildrenOnly(array &$parent, array &$children)
    {
        $parent = $this->copyVatInfoToParent($parent, $children);
        $parent = $this->removePriceInfoFromParent($parent);
    }

    /**
     * Corrects info between parent and child lines.
     *
     * This method should be called when:
     * - Child invoice lines are kept separately.
     * - Price info is on the parent and children, but they double each other.
     *
     * What do we do in this situation:
     * - Remove price info from parent to ensure that the amount appears only
     *   once.
     * - If the vat rate on the parent is absent or incorrect, it should best be
     *   set to the maximum vat rate appearing on the children.
     *
     * We could remove price info from the children instead, but that would be
     * wrong if the children do not all have the same vat rate.
     *
     * Known usages:
     * - None so far.
     *
     * @param array $parent
     *   The parent invoice line.
     * @param array[] $children
     *   The child invoice lines.
     */
    protected function keepChildrenAndPriceOnParentAndChildren(array &$parent, array &$children)
    {
        if (!Completor::isCorrectVatRate($parent[Meta::VatRateSource]) || Number::isZero($parent[Tag::VatRate])) {
            $parent = $this->copyVatInfoToParent($parent, $children);
        }
        $parent = $this->removePriceInfoFromParent($parent);
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
     * @param array $parent
     *   The parent invoice line.
     * @param array[] $children
     *   The child invoice lines.
     */
    protected function keepChildrenAndPriceOnParentPlusChildren(array &$parent, array &$children)
    {
    }

    /**
     * Copies vat info from the parent to all children.
     *
     * In Magento, VAT info on the children may contain a 0 vat rate. To correct
     * this, we copy the vat information (rate, source, correction info).
     *
     * @param array $parent
     *   The parent invoice line.
     * @param array[] $children
     *   The child invoice lines.
     *
     * @return array[]
     *   The child invoice lines with vat info copied form the parent.
     */
    protected function copyVatInfoToChildren(array $parent, array $children)
    {
        foreach ($children as &$child) {
            $child[Tag::VatRate] = $parent[Tag::VatRate];
            $child[Meta::VatAmount] = 0;
            if (Completor::isCorrectVatRate($parent[Meta::VatRateSource])) {
                $child[Meta::VatRateSource] = Completor::VatRateSource_Copied_From_Parent;
                unset($child[Meta::VatRateMin]);
                unset($child[Meta::VatRateMax]);
                unset($child[Meta::VatRateLookup]);
            } else {
                $child[Meta::VatRateSource] = $parent[Meta::VatRateSource];
                if (isset($parent[Meta::VatRateMin])) {
                    $child[Meta::VatRateMin] = $parent[Meta::VatRateMin];
                } else {
                    unset($child[Meta::VatRateMin]);
                }
                if (isset($parent[Meta::VatRateMax])) {
                    $child[Meta::VatRateMax] = $parent[Meta::VatRateMax];
                } else {
                    unset($child[Meta::VatRateMax]);
                }
                if (isset($parent[Meta::VatRateLookup])) {
                    $child[Meta::VatRateLookup] = $parent[Meta::VatRateLookup];
                } else {
                    unset($child[Meta::VatRateLookup]);
                }
            }

            $child[Meta::LineVatAmount] = 0;
            unset($child[Meta::LineDiscountVatAmount]);
        }
        return $children;
    }

    /**
     * Copies vat info to the parent.
     *
     * This prevents that amounts appear twice on the invoice.
     *
     * @param array $parent
     *   The parent invoice line.
     * @param array[] $children
     *   The child invoice lines.
     *
     * @return array
     *   The parent invoice line with price info removed.
     */
    protected function copyVatInfoToParent(array $parent, array $children)
    {
        $parent[Meta::VatAmount] = 0;
        // Copy vat rate info from a child when the parent has no vat rate info.
        if (empty($parent[Tag::VatRate]) || Number::isZero($parent[Tag::VatRate])) {
            $parent[Tag::VatRate] = CompletorInvoiceLines::getMaxAppearingVatRate($children, $index);
            $parent[Meta::VatRateSource] = Completor::VatRateSource_Copied_From_Children;
            if (isset($children[$index][Meta::VatRateMin])) {
                $parent[Meta::VatRateMin] = $children[$index][Meta::VatRateMin];
            } else {
                unset($parent[Meta::VatRateMin]);
            }
            if (isset($children[$index][Meta::VatRateMax])) {
                $parent[Meta::VatRateMax] = $children[$index][Meta::VatRateMax];
            } else {
                unset($parent[Meta::VatRateMax]);
            }
        }
        $parent[Meta::LineVatAmount] = 0;
        unset($parent[Meta::LineDiscountVatAmount]);

        return $parent;
    }

    /**
     * Removes price info from all children.
     *
     * This can prevent that amounts appear twice on the invoice. This can only
     * be done if all children have the same vat rate as the parent, otherwise
     * the price (and vat) info should remain on the children and be removed
     * from the parent.
     *
     * @param array[] $children
     *   The child invoice lines.
     *
     * @return array[]
     *   The child invoice lines with price info removed.
     */
    protected function removePriceInfoFromChildren(array $children)
    {
        foreach ($children as &$child) {
            $child[Tag::UnitPrice] = 0;
            $child[Meta::UnitPriceInc] = 0;
            unset($child[Meta::LineAmount]);
            unset($child[Meta::LineAmountInc]);
            unset($child[Meta::LineDiscountAmountInc]);
        }
        return $children;
    }

    /**
     * Removes price info from the parent.
     *
     * This can prevent that amounts appear twice on the invoice.
     *
     * @param array $parent
     *   The parent invoice line.
     *
     * @return array
     *   The parent invoice line with price info removed.
     */
    protected function removePriceInfoFromParent(array $parent)
    {
        $parent[Tag::UnitPrice] = 0;
        $parent[Meta::UnitPriceInc] = 0;
        unset($parent[Meta::LineAmount]);
        unset($parent[Meta::LineAmountInc]);
        unset($parent[Meta::LineDiscountAmountInc]);
        return $parent;
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
    protected function indentDescription($description)
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
     * @param array[] $lines
     *   an array of invoice lines.
     *
     * @return array
     *   An array with the vat rates as key and the number of times they appear
     *   in the invoice lines as value.
     */
    protected function getAppearingVatRates(array $lines)
    {
        $vatRates = array();
        foreach ($lines as $line) {
            if (isset($line[Tag::VatRate])) {
                $vatRate = sprintf('%.1f', $line[Tag::VatRate]);
                if (isset($vatRates[$vatRate])) {
                    $vatRates[$vatRate]++;
                } else {
                    $vatRates[$vatRate] = 1;
                }
            }
        }
        return $vatRates;
    }

    /**
     * Returns whether the lines have different vat rates.
     *
     * @param array $lines
     *   The lines to compare.
     *
     * @return bool
     *   True if the lines have different vat rates.
     */
    protected function haveSameVatRate(array $lines)
    {
        $vatRates = $this->getAppearingVatRates($lines);
        return count($vatRates) === 1;
    }
}
