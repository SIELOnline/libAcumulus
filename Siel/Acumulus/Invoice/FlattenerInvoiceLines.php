<?php
namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\Number;

/**
 * The invoice lines flattener class provides functionality to flatten and
 * correct invoice lines that describe bundle or composed products, or just
 * options or variants.
 *
 * This class flattens the invoice lines (recursively). If an invoice line has
 * child lines they are either merged into the parent line or added as separate
 * invoice lines at the same level as the parent invoice line.
 */
class FlattenerInvoiceLines
{
    /** @var \Siel\Acumulus\Invoice\ConfigInterface  */
    protected $config;

    protected $index = 1;

    /**
     * Constructor.
     *
     * @param \Siel\Acumulus\Invoice\ConfigInterface $config
     */
    public function __construct(ConfigInterface $config)
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
            // If it has children, flatten them and determine how to add them.
            if (array_key_exists(Creator::Line_Children, $line)) {
                $children = $this->flattenInvoiceLines($line[Creator::Line_Children]);
                // Remove children from parent.
                unset($line[Creator::Line_Children]);
                // Determine whether to add as a single line or add them as
                // separate lines.
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
        $vatRates = $this->getAppearingVatRates($children);
        if (count($vatRates) > 1) {
            // We MUST keep them separate to retain correct vat info.
            $separateLines = true;
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
            $childrenTexts[] = $child['product'];
        }
        $childrenText = ' (' . implode(', ', $childrenTexts) . ')';
        return $parent['product'] .  $childrenText;
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
            $parent['meta-children'] = count($children);
            foreach ($children as &$child) {
                $child['product'] = $this->indentDescription($child['product']);
                $child['meta-parent-index'] = $this->index++;
            }
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
        $parent['product'] = $this->getMergedLinesText($parent, $children);
        $parent['meta-children-merged'] = count($children);
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
     * - Copy vat rate info from parent to children (may be empty on the children).
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
     * - Remove price info from parent to ensure that the amount appears only once.
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
        if (!Completor::isCorrectVatRate($parent['meta-vatrate-source']) || Number::isZero($parent['vatrate'])) {
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
     *   double each other (base price + extra prices for more expensive options).
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
            $child['vatrate'] = $parent['vatrate'];
            $child['vatamount'] = 0;
            if (Completor::isCorrectVatRate($parent['meta-vatrate-source'])) {
                $child['meta-vatrate-source'] = Completor::VatRateSource_Copied;
                unset($child['meta-vatrate-min']);
                unset($child['meta-vatrate-max']);
                unset($child['meta-lookup-vatrate']);
            } else {
                $child['meta-vatrate-source'] = $parent['meta-vatrate-source'];
                if (isset($parent['meta-vatrate-min'])) {
                    $child['meta-vatrate-min'] = $parent['meta-vatrate-min'];
                } else {
                    unset($child['meta-vatrate-min']);
                }
                if (isset($parent['meta-vatrate-max'])) {
                    $child['meta-vatrate-max'] = $parent['meta-vatrate-max'];
                } else {
                    unset($child['meta-vatrate-max']);
                }
                if (isset($parent['meta-lookup-vatrate'])) {
                    $child['meta-lookup-vatrate'] = $parent['meta-lookup-vatrate'];
                } else {
                    unset($child['meta-lookup-vatrate']);
                }
            }

            $child['meta-line-vatamount'] = 0;
            unset($child['meta-line-discount-vatamount']);
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
    protected function copyVatInfoToParent(array $parent, array $children) {
        $parent['vatamount'] = 0;
        // Copy vat rate info from a child when the parent has no vat rate info.
        if (empty($parent['vatrate']) || Number::isZero($parent['vatrate'])) {
            $parent['vatrate'] = CompletorInvoiceLines::getMaxAppearingVatRate($children, $index);
            $parent['meta-vatrate-source'] = Completor::VatRateSource_Copied;
            if (isset($children[$index]['meta-vatrate-min'])) {
                $parent['meta-vatrate-min'] = $children[$index]['meta-vatrate-min'];
            } else {
                unset($parent['meta-vatrate-min']);
            }
            if (isset($children[$index]['meta-vatrate-max'])) {
                $parent['meta-vatrate-max'] = $children[$index]['meta-vatrate-max'];
            } else {
                unset($parent['meta-vatrate-max']);
            }
        }
        $parent['meta-line-vatamount'] = 0;
        unset($parent['meta-line-discount-vatamount']);

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
            $child['unitprice'] = 0;
            $child['unitpriceinc'] = 0;
            unset($child['meta-line-price']);
            unset($child['meta-line-priceinc']);
            unset($child['meta-line-discount-amountinc']);
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
        $parent['unitprice'] = 0;
        $parent['unitpriceinc'] = 0;
        unset($parent['meta-line-price']);
        unset($parent['meta-line-priceinc']);
        unset($parent['meta-line-discount-amountinc']);
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
    protected function indentDescription($description) {
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
            if (isset($line['vatrate'])) {
                $vatRate = sprintf('%.1f', $line['vatrate']);
                if (isset($vatRates[$vatRate])) {
                    $vatRates[$vatRate]++;
                } else {
                    $vatRates[$vatRate] = 1;
                }
            }
        }
        return $vatRates;
    }
}
