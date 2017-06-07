<?php
namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Config\ConfigInterface;
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

    /** @var \Siel\Acumulus\Config\ConfigInterface  */
    protected $config;

    /** @var \Siel\Acumulus\Invoice\FlattenerInvoiceLines */
    protected $invoiceLineFlattener = null;

    /**
     * Constructor.
     *
     * @param \Siel\Acumulus\Config\ConfigInterface $config
     * @param \Siel\Acumulus\Invoice\FlattenerInvoiceLines $invoiceLinesFlattener
     */
    public function __construct(ConfigInterface $config, FlattenerInvoiceLines $invoiceLinesFlattener)
    {
        $this->config = $config;
        $this->invoiceLineFlattener = $invoiceLinesFlattener;
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
        $invoice['customer']['invoice']['line'] = $this->invoiceLineFlattener->complete($invoice['customer']['invoice']['line']);
        $invoice['customer']['invoice']['line'] = $this->completeInvoiceLines($invoice['customer']['invoice']['line']);

        return $invoice;
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
     *   The invoice lines to complete recursively.
     *
     * @return array[]
     *   The completed invoice lines.
     */
    protected function completeInvoiceLinesRecursive(array $lines)
    {
        // correctCalculatedVatRates() only uses vatrate, meta-vatrate-min, and
        // meta-vatrate-max and may lead to more (required) data filled in, so
        // should be called before completeLineRequiredData().
        $lines = $this->correctCalculatedVatRates($lines);
        $lines = $this->completeLineRequiredData($lines);
        // Completing the required data may lead to new lines that contain
        // calculated VAT rates and thus can be corrected with
        // correctCalculatedVatRates(): call again.
        $lines = $this->correctCalculatedVatRates($lines);
        $lines = $this->addVatRateToLookupLines($lines);
        return $lines;
    }

    /**
     * Completes the invoice lines after they have been flattened.
     *
     * @param array[] $lines
     *   The invoice lines to complete.
     *
     * @return array[]
     *   The completed invoice lines.
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
     * - vatamount
     * - unitpriceinc
     *
     * @param array[] $lines
     *   The invoice lines to complete with required data.
     *
     * @return array[]
     *   The completed invoice lines.
     */
    protected function completeLineRequiredData(array $lines)
    {
        foreach ($lines as &$line) {
            // Easy gain first. Known usages: Magento (1?).
            if (!isset($line['vatamount']) && isset($line['meta-line-vatamount'])) {
                $line['vatamount'] = $line['meta-line-vatamount'] / $line['quantity'];
                $line['meta-calculated-fields'][] = 'vatamount';
            }

            if (!isset($line['unitprice'])) {
                // With margin scheme, the unitprice should be known but may
                // have ended up in the unitpriceinc.
                if (isset($line['costprice'])) {
                    if (isset($line['unitpriceinc'])) {
                        $line['unitprice'] = $line['unitpriceinc'];
                    }
                } elseif (isset($line['unitpriceinc'])) {
                    if (Number::isZero($line['unitpriceinc'])) {
                        // Free products are free with and without VAT.
                        $line['unitprice'] = 0;
                    } elseif (isset($line['vatrate']) && Completor::isCorrectVatRate($line['meta-vatrate-source'])) {
                         $line['unitprice'] = $line['unitpriceinc'] / (100.0 + $line['vatrate']) * 100.0;
                    } elseif (isset($line['vatamount'])) {
                        $line['unitprice'] = $line['unitpriceinc'] - $line['vatamount'];
                    } else {
                        // We cannot fill in unitprice reliably, so better to
                        // leave it empty and fail clearly.
                    }
                    $line['meta-calculated-fields'][] = 'unitprice';
                }
            }

            if (!isset($line['unitpriceinc'])) {
                // With margin scheme, the unitpriceinc equals unitprice.
                if (isset($line['costprice'])) {
                    if (isset($line['unitprice'])) {
                        $line['unitpriceinc'] = $line['unitprice'];
                    }
                } elseif (isset($line['unitprice'])) {
                    if (Number::isZero($line['unitprice'])) {
                        // Free products are free with and without VAT.
                        $line['unitpriceinc'] = 0;
                    } elseif (isset($line['vatrate']) && Completor::isCorrectVatRate($line['meta-vatrate-source'])) {
                         $line['unitpriceinc'] = $line['unitprice'] * (100.0 + $line['vatrate']) / 100.0;
                    } elseif (isset($line['vatamount'])) {
                        $line['unitpriceinc'] = $line['unitprice'] + $line['vatamount'];
                    } else {
                        // We cannot fill in unitpriceinc reliably, so we leave
                        // it empty as it is metadata after all.
                    }
                    $line['meta-calculated-fields'][] = 'unitpriceinc';
                }
            }

            if (!isset($line['vatrate'])) {
                if (isset($line['vatamount']) && isset($line['unitprice'])) {
                    // Set (overwrite the tag vatrate-source) vatrate and
                    // accompanying tags.
                    $line = array_merge($line, Creator::getVatRangeTags($line['vatamount'], $line['unitprice']));
                    $line['meta-calculated-fields'][] = 'vatrate';
                }
            }

            // Recursively complete the required data.
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
     *   The invoice lines to correct.
     *
     * @return array[]
     *   The corrected invoice lines.
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
     *   An invoice line with a calculated vat rate.
     *
     * @return array
     *   The invoice line with a corrected vat rate.
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
     * Completes lines that have meta-vatrate-lookup(-...) data.
     *
     * Meta-vatrate-lookup data is added by the Creator class using vat rates
     * from the products. However as VAT rates may have changed between the date
     * of the order and now, we cannot fully rely on it and use it only as a
     * last resort.
     *
     * @param array[] $lines
     *   The invoice lines to correct using lookup data.
     *
     * @return array[]
     *   The corrected invoice lines.
     */
    protected function addVatRateToLookupLines(array $lines)
    {
        foreach ($lines as &$line) {
            if ($line['meta-vatrate-source'] === Creator::VatRateSource_Completor && $line['vatrate'] === null && Number::isZero($line['unitprice'])) {
                if (!empty($line['meta-vatrate-lookup']) && $this->isPossibleVatRate($line['meta-vatrate-lookup'])) {
                    // The vat rate looked up on the product is a possible vat
                    // rate, so apparently that vat rate did not change between
                    // the date of the order and now: take that one.
                    $line['vatrate'] = $line['meta-vatrate-lookup'];
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
     *   The invoice lines to correct by adding a vat rate to 0 amounts.
     *
     * @return array[]
     *   The corrected invoice lines.
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
     * Returns the maximum vat rate that appears in the given set of lines.
     *
     * @param array[] $lines
     *   The invoice lines to search.
     * @param int $index
     *   If passed, the index of the max vat rate is returned via this parameter.
     *
     * @return float|null
     *   The maximum vat rate in the given set of lines.
     */
    public static function getMaxAppearingVatRate(array $lines, &$index = 0) {
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
     * Completes each (non-strategy) invoice line with missing (meta) info.
     *
     * All non strategy invoice lines have unitprice and vatrate filled in and
     * should by now have correct(ed) VAT rates. In some shops these non
     * strategy invoice lines may have a meta-line-discount-vatamount or
     * meta-line-discount-amountinc field, that can be used with the
     * SplitKnownDiscountLine strategy.
     *
     * Complete (if missing):
     * - unitpriceinc
     * - vatamount
     * - meta-line-discount-amountinc (if meta-line-discount-vatamount is
     *   available).
     *
     * For strategy invoice lines that may be split with the SplitNonMatching
     * line  strategy, we need to know the line totals.
     *
     * Complete (if missing):
     * - meta-line-price
     * - meta-line-priceinc
     *
     * @param array[] $lines
     *   The invoice lines to complete with meta data.
     *
     * @return array[]
     *   The completed invoice lines.
     */
    protected function completeLineMetaData(array $lines)
    {
        foreach ($lines as &$line) {
            if (Completor::isCorrectVatRate($line['meta-vatrate-source'])) {
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
