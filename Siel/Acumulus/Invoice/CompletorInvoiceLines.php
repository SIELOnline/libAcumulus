<?php
namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\Number;

/**
 * The invoice lines completor class provides functionality to correct and
 * complete invoice lines before sending them to Acumulus.
 *
 * This class:
 * - Validates (and correct rounding errors of) vat rates using the VAT rate
 *   lookup webservice call.
 * - Adds required but missing fields on the invoice lines.
 * - Adds vat rates to 0 price lines (with a 0 price and thus 0 vat, not all
 *   web shops can fill in a vat rate).
 * - Completes meta data that may be used in the strategy phase or just for
 *   support purposes.
 */
class CompletorInvoiceLines
{
    /**
     * The list of possible vat types, initially filled with possible vat types
     * type based on client country, invoiceHasLineWithVat(), is_company(), and
     * the digital services setting.
     *
     * @var int[]
     */
    protected $possibleVatTypes;

    /** @var array[] */
    protected $possibleVatRates;

    /** @var \Siel\Acumulus\Invoice\ConfigInterface  */
    protected $config;

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
     * Completes the invoice with default settings that do not depend on shop
     * specific data.
     *
     * @param array $invoice
     *   The invoice to complete.
     * @param int[] $possibleVatTypes
     * @param array[] $possibleVatRates
     *
     * @return array
     *   The completed invoice.
     */
    public function complete(array $invoice, array $possibleVatTypes, array $possibleVatRates)
    {
        $this->possibleVatTypes = $possibleVatTypes;
        $this->possibleVatRates = $possibleVatRates;

        $invoice['customer']['invoice']['line'] = $this->completeInvoiceLinesRecursive($invoice['customer']['invoice']['line']);
        $invoice['customer']['invoice']['line'] = $this->flattenInvoiceLines($invoice['customer']['invoice']['line']);
        $invoice['customer']['invoice']['line'] = $this->completeInvoiceLines($invoice['customer']['invoice']['line']);

        return $invoice;
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
                // Determine whether to add as a single line or add them
                // separately.
                if ($this->keepSeparateLines($line, $children)) {
                    // Keep them separate but allow for some web shop specific
                    // corrections; indent product descriptions; sanitize VAT
                    // rates and add some meta data to relate them.
                    $this->correctInfoBetweenParentAndChildren($line, $children);
                    if (!empty($children)) {
                        $parentIndex = count($result);
                        $line['meta-parent-index'] = $parentIndex;
                        $line['meta-children'] = count($children);
                        foreach ($children as &$child) {
                            $child['product'] = ' - ' . $child['product'];
                            $child['meta-parent'] = $parentIndex;
                        }
                    }
                } else {
                    $line['product'] = $this->getMergedLinesText($line, $children);
                    $line['meta-children-merged'] = count($children);
                    $children = null;
                }
                // Remove children now that they have been flattened or merged.
                unset($line[Creator::Line_Children]);
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
     * - The settings for:
     *   * optionsAllOn1Line
     *   * optionsAllOnOwnLine
     *   * optionsMaxLength
     * - Whether all lines have the same VAT rate (different VAT rates => keep)
     *
     * Override if you want other logic to decide on.
     *
     * @param array $parent
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
     * @param array[] $children
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
     * This base implementation does not do any correction. Web shops may add
     * their own by overriding this method.
     *
     * Examples;
     * - remove double amounts, e.g. parent amount = sum of children amounts and
     *   these amounts appear both on the parent on its children.
     *
     * @param array $parent
     * @param array[] $children
     */
    protected function correctInfoBetweenParentAndChildren(array &$parent, array &$children)
    {
    }

    /**
     * Removes price info from all children.
     *
     * This prevents that amounts appear twice on the invoice. This can only be
     * done if all children have the same vat rate as the parent, otherwise the
     * price (and vat) info should be on the children and not on the parent
     *
     * Note: in Magento children could have 0 as vatrate, so we copy vat info
     * from the parent to the children.
     *
     * @param array $parent
     *   The parent line.
     * @param array[] $children
     *   The child lines.
     *
     * @return array[]
     *   The children with price info removed.
     */
    protected function removePriceInfoFromChildren(array $parent, array $children)
    {
        foreach ($children as &$child) {
            $child['unitprice'] = 0;
            $child['unitpriceinc'] = 0;
            $child['vatamount'] = 0;
            $child['vatrate'] = $parent['vatrate'];
            $child['meta-vatrate-source'] = $parent['meta-vatrate-source'];
            if (isset($parent['meta-vatrate-min'])) {
                $child['meta-vatrate-min'] = $parent['meta-vatrate-min'];
            }
            else {
                unset($child['meta-vatrate-min']);
            }
            if (isset($parent['meta-vatrate-max'])) {
                $child['meta-vatrate-max'] = $parent['meta-vatrate-max'];
            }
            else {
                unset($child['meta-vatrate-max']);
            }
            $child['meta-line-vatamount'] = 0;
            unset($child['meta-line-price']);
            unset($child['meta-line-priceinc']);
            unset($child['meta-line-vatamount']);
            unset($child['meta-line-discount-amountinc']);
            unset($child['meta-line-discount-vatamount']);
        }
        return $children;
    }

    /**
     * Removes price info from the parent.
     *
     * This prevents that amounts appear twice on the invoice.
     *
     * @param array $parent
     *   The parent line.
     * @param array[] $children
     *   The child lines.
     *
     * @return array
     *   The parent line with price info removed.
     */
    protected function removePriceInfoFromParent(array $parent, array $children)
    {
        $parent['unitprice'] = 0;
        $parent['unitpriceinc'] = 0;
        $parent['vatamount'] = 0;
        // Copy vat rate info from a child when the parent has no vat rate info.
        if (empty($parent['vatrate']) || Number::isZero($parent['vatrate'])) {
            $parent['vatrate'] = $this->getMaxAppearingVatRate($children, $index);
            $parent['meta-vatrate-source'] = $children[$index]['meta-vatrate-source'];
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

        unset($parent['meta-line-price']);
        unset($parent['meta-line-priceinc']);
        unset($parent['meta-line-vatamount']);
        unset($parent['meta-line-discount-amountinc']);
        unset($parent['meta-line-discount-vatamount']);
        return $parent;
    }

    /**
     * Completes the invoice lines before they have been flattened.
     *
     * This means that the lines have to be walked recursively.
     *
     * The actions that can be done this way are those who operate on a line in
     * isolation and thus do not need totals, maxs or things like that.
     *
     * @param array[] $lines
     *   The lines to complete recursively.
     *
     * @return array[]
     *   The completed lines.
     */
    protected function completeInvoiceLinesRecursive(array $lines)
    {
        // correctCalculatedVatRates() only uses vatrate, meta-vatrate-min, and
        // meta-vatrate-max, so may be called before completeLineRequiredData().
        $lines = $this->correctCalculatedVatRates($lines);
        $lines = $this->completeLineRequiredData($lines);
        $lines = $this->addVatRateToLookupLines($lines);
        return $lines;
    }

    /**
     * Completes the invoice lines after they have been flattened.
     *
     * @param array[] $lines
     *   The lines to complete.
     *
     * @return array[]
     *   The completed lines.
     */
    protected function completeInvoiceLines(array $lines)
    {
        $lines = $this->addVatRateTo0PriceLines($lines);
        $lines = $this->completeLineMetaData($lines);
        return $lines;
    }

    /**
     * Completes fields that are required by the rest of this completor phase.
     *
     * The creator filled in the fields that are directly available from the
     * shops' data store. This method completes (if not filled in):
     * - unitprice.
     *
     * @param array[] $lines
     *   The lines to complete with required data.
     *
     * @return array[]
     *   The completed lines.
     */
    protected function completeLineRequiredData(array $lines)
    {
        foreach ($lines as &$line) {
            if (!isset($line['unitprice'])) {
                if (isset($line['unitpriceinc'])) {
                    if (Number::isZero($line['unitpriceinc'])) {
                        $line['unitprice'] = 0;
                    } elseif (isset($line['vatrate']) && in_array($line['meta-vatrate-source'], Completor::$CorrectVatRateSources)) {
                        if (isset($line['costprice'])) {
                            $margin = $line['unitpriceinc'] - $line['costprice'];
                            if ($margin > 0) {
                                // Calculate VAT over margin part only.
                                $line['unitprice'] = $line['costprice'] + $margin / (100.0 + $line['vatrate']) * 100.0;
                            } else {
                                // VAT = 0 with no or a negative margin.
                                $line['unitprice'] = $line['unitpriceinc'];
                            }
                        } else {
                            $line['unitprice'] = $line['unitpriceinc'] / (100.0 + $line['vatrate']) * 100.0;
                        }
                    } elseif (isset($line['vatamount'])) {
                        $line['unitprice'] = $line['unitpriceinc'] - $line['vatamount'];
                    } else {
                        // We cannot fill in unitprice reliably, so better to
                        // leave it empty to get a clear error message.
                    }
                    $line['meta-calculated-fields'][] = 'unitprice';
                }
                if (!empty($line[Creator::Line_Children])) {
                    $line[Creator::Line_Children] = $this->correctCalculatedVatRates($line[Creator::Line_Children]);
                }
            }
            if (!empty($line[Creator::Line_Children])) {
                $line[Creator::Line_Children] = $this->completeLineRequiredData($line[Creator::Line_Children]);
            }
        }
        return $lines;
    }

    /**
     * Corrects 'calculated' vat rates.
     *
     * Tries to correct 'calculated' vat rates for rounding errors by matching
     * them with possible vatRates obtained from the vat lookup service.
     *
     * @param array[] $lines
     *   The lines to correct.
     *
     * @return array[]
     *   The corrected lines.
     */
    protected function correctCalculatedVatRates(array $lines)
    {
        foreach ($lines as &$line) {
            if (!empty($line['meta-vatrate-source']) && $line['meta-vatrate-source'] === Creator::VatRateSource_Calculated) {
                $line = $this->correctVatRateByRange($line);
            }
            if (!empty($line[Creator::Line_Children])) {
                $line[Creator::Line_Children] = $this->correctCalculatedVatRates($line[Creator::Line_Children]);
            }
        }
        return $lines;
    }

    /**
     * Checks and corrects a 'calculated' vat rate to an allowed vat rate.
     *
     * The meta-vatrate-source must be Creator::VatRateSource_Calculated.
     *
     * The check is done on comparing allowed vat rates with the
     * meta-vatrate-min and meta-vatrate-max values. If only 1 match is found
     * that will be used.
     *
     * If multiple matches are found with all equal rates - e.g. Dutch and
     * Belgium 21% - the vat rate will be corrected, but the VAT Type will
     * remain undecided.
     *
     * This method is public to allow a 2nd call to just this method for a
     * single line (a missing amount line) added after a 1st round of
     * correcting. Do not use unless $this->possibleVatRates has been
     * initialized.
     *
     * @param array $line
     *   A line with a calculated vat rate.
     *
     * @return array
     *   The line with a corrected vat rate.
     */
    public function correctVatRateByRange(array $line)
    {
        $matchedVatRates = array();
        foreach ($this->possibleVatRates as $vatRateInfo) {
            if ($vatRateInfo['vatrate'] >= $line['meta-vatrate-min'] && $vatRateInfo['vatrate'] <= $line['meta-vatrate-max']) {
                $matchedVatRates[] = $vatRateInfo;
            }
        }

        $vatRate = $this->getUniqueVatRate($matchedVatRates);
        if ($vatRate !== null && $vatRate !== false) {
            // We have a single match: fill it in as the vat rate for this line.
            $line['vatrate'] = $vatRate;
            $line['meta-vatrate-source'] = Completor::VatRateSource_Calculated_Corrected;
        } else {
            // We remove the calculated vatrate.
            unset($line['vatrate']);
            if ($vatRate === null) {
                $line['meta-vatrate-matches'] = 'none';
            } else {
                $line['meta-vatrate-matches'] = array_reduce($matchedVatRates,
                  function ($carry, $item) {
                      return $carry . ($carry === '' ? '' : ',') . $item['vatrate'] . '(' . $item['vattype'] . ')';
                  }, '');
            }

            // If this line may be split, we make it a strategy line (even
            // though 2 out of the 3 fields ex, inc, and vat are known). This
            // way the strategy phase gets a chance to correct this line.
            if (!empty($line['meta-strategy-split'])) {
                $line['meta-vatrate-source'] = Creator::VatRateSource_Strategy;
            }
        }
        return $line;
    }

    /**
     * Determines if all (matched) vat rates are equal.
     *
     * @param array $matchedVatRates
     *
     * @return float|FALSE|NULL
     *   If all vat rates are equal that vat rate, null if $matchedVatRates is
     *   empty, false otherwise (multiple but different vat rates).
     */
    protected function getUniqueVatRate(array $matchedVatRates)
    {
        $result = array_reduce($matchedVatRates, function ($carry, $matchedVatRate) {
            if ($carry === null) {
                // 1st item: return its vat rate.
                return $matchedVatRate['vatrate'];
            } elseif ($carry == $matchedVatRate['vatrate']) {
                // Note that in PHP: '21' == '21.0000' returns true. So using ==
                // works. Vat rate equals all previous vat rates: return that
                // vat rate.
                return $carry;
            } else {
                // Vat rate does not match previous vat rates or carry is
                // already false: return false.
                return false;
            }
        }, null);
        return $result;
    }

    /**
     * Completes lines that have meta-lookup-... data.
     *
     * Meta-lookup-vatrate data is added by the Creators using vat rates from
     * the products. However as VAT rates may have changed between the date of
     * the order and now, we cannot fully rely on it and use it only as an
     * (almost) last resort.
     *
     * @param array[] $lines
     *   The lines to correct using lookup data.
     *
     * @return array[]
     *   The corrected lines.
     */
    protected function addVatRateToLookupLines(array $lines)
    {
        foreach ($lines as &$line) {
            if ($line['meta-vatrate-source'] === Creator::VatRateSource_Completor && $line['vatrate'] === null && Number::isZero($line['unitprice'])) {
                if (!empty($line['meta-lookup-vatrate']) && $this->isPossibleVatRate($line['meta-lookup-vatrate'])) {
                    // The vat rate looked up on the product is a possible vat
                    // rate, so apparently that vat rate did not change between
                    // the date of the order and now: take that one.
                    $line['vatrate'] = $line['meta-lookup-vatrate'];
                    $line['meta-vatrate-source'] = Completor::VatRateSource_Looked_Up;
                }
            }
            if (!empty($line[Creator::Line_Children])) {
                $line[Creator::Line_Children] = $this->addVatRateToLookupLines($line[Creator::Line_Children]);
            }
        }
        return $lines;
    }

    /**
     * Completes lines with free items (price = 0) by giving them the maximum
     * tax rate that appears in the other lines.
     *
     * @param array[] $lines
     *   The lines to correct by adding vat rates to lines with 0 prices.
     *
     * @return array[]
     *   The corrected lines.
     */
    protected function addVatRateTo0PriceLines(array $lines)
    {
        // Get the highest appearing vat rate. We could get the most often
        // appearing vat rate, but IMO the highest vat rate will be more likely
        // to be correct.
        $maxVatRate = $this->getMaxAppearingVatRate($lines);

        foreach ($lines as &$line) {
            if ($line['meta-vatrate-source'] === Creator::VatRateSource_Completor && $line['vatrate'] === null && Number::isZero($line['unitprice'])) {
                if ($maxVatRate !== null) {
                    $line['vatrate'] = $maxVatRate;
                    $line['meta-vatrate-source'] = Completor::VatRateSource_Completor_Completed;
                } else {
                    $line['meta-vatrate-source'] = Creator::VatRateSource_Strategy;
                }
            }
        }
        return $lines;
    }

    /**
     * Returns a list of vat rates that actually appear in the given lines.
     *
     * @param array[] $lines
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

    /**
     * Returns the maximum vat rate that appears in the given set of lines.
     *
     * @param array[] $lines
     *   The lines to search.
     * @param int $index
     *   If passed, the index of the max vat rate is returned via this parameter.
     *
     * @return float|null
     *   The maximum vat rate in the given set of lines.
     */
    protected function getMaxAppearingVatRate(array $lines, &$index = 0) {
        $index = null;
        $maxVatRate = -1.0;
        foreach ($lines as $key => $line) {
            if (isset($line['vatrate']) && (float) $line['vatrate'] > $maxVatRate) {
                $index = $key;
                $maxVatRate = (float) $line['vatrate'];
            }
        }
        return $maxVatRate;
    }

    /**
     * Returns whether the given vat rate is a possible vat rate.
     *
     * @param float $vatRate
     *   The vat rate to lookup. May also be passed in as a string or int.
     *
     * @return bool
     *   True if the given $vatRate is a possible vat rate.
     */
    protected function isPossibleVatRate($vatRate) {
        $result = false;
        foreach ($this->possibleVatRates as $vatRateInfo) {
            if (Number::floatsAreEqual($vatRate, $vatRateInfo['vatrate'])) {
                $result = TRUE;
                break;
            }
        }
        return $result;
    }

    /**
     * Completes each (non-strategy) line with missing (meta) info.
     *
     * All non strategy lines have unitprice and vatrate filled in and should by
     * now have correct(ed) VAT rates. In some shops these non strategy lines
     * may have a meta-line-discount-vatamount or meta-line-discount-amountinc
     * field, that can be used with the SplitKnownDiscountLine strategy.
     * Complete (if missing):
     * - unitpriceinc
     * - vatamount
     * - meta-line-discount-amountinc (if meta-line-discount-vatamount is
     *   available).
     * For strategy lines that may be split with the non matching line strategy,
     * we need to know the line totals. Complete (if missing):
     * - meta-line-price
     * - meta-line-priceinc
     *
     * @param array[] $lines
     *   The lines to complete with meta data.
     *
     * @return array[]
     *   The completed lines.
     */
    protected function completeLineMetaData(array $lines)
    {
        foreach ($lines as &$line) {
            if (in_array($line['meta-vatrate-source'], Completor::$CorrectVatRateSources)) {
                if (!isset($line['unitpriceinc'])) {
                    $line['unitpriceinc'] = $line['unitprice'] / 100.0 * (100.0 + $line['vatrate']);
                    $line['meta-calculated-fields'][] = 'unitpriceinc';
                }
                if (!isset($line['vatamount'])) {
                    $line['vatamount'] = $line['vatrate'] / 100.0 * $line['unitprice'];
                    $line['meta-calculated-fields'][] = 'vatamount';
                }
                if (isset($line['meta-line-discount-vatamount']) && !isset($line['meta-line-discount-amountinc'])) {
                    $line['meta-line-discount-amountinc'] = $line['meta-line-discount-vatamount'] / $line['vatrate'] * (100 + $line['vatrate']);
                    $line['meta-calculated-fields'][] = 'meta-line-discount-amountinc';
                }
            } elseif ($line['meta-vatrate-source'] == Creator::VatRateSource_Strategy && !empty($line['meta-strategy-split'])) {
                if (isset($line['unitprice']) && isset($line['unitpriceinc'])) {
                    if (!isset($line['meta-line-price'])) {
                        $line['meta-line-price'] = $line['unitprice'] * $line['quantity'];
                    }
                    if (!isset($line['meta-line-priceinc'])) {
                        $line['meta-line-priceinc'] = $line['unitpriceinc'] * $line['quantity'];
                    }
                }
            }

            if (isset($line['meta-calculated-fields'])) {
                $line['meta-calculated-fields'] = implode(',', array_unique($line['meta-calculated-fields']));
            }
        }
        return $lines;
    }
}
