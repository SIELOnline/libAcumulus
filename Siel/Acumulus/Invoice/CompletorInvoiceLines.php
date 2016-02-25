<?php
namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\Number;

/**
 * The invoice lines completor class provides functionality to correct and
 * complete invoice lines before sending them to Acumulus.
 *
 * This class:
 * - Adds required but missing fields on the invoice lines.
 * - Validates (and correct rounding errors of) vat rates using the VAT rate
 *   lookup webservice call.
 * - Adds vat rates to 0 price lines (with a 0 price and thus 0 vat, not all
 *   web shops can fill in a vat rate).
 *
 * @package Siel\Acumulus
 */
class CompletorInvoiceLines
{
    /** @var array[] */
    protected $invoice;

    /** @var array[] */
    protected $invoiceLines;

    /**
     * @var int[]
     *   The list of possible vat types, initially filled with possible vat types
     *   based on client country, invoiceHasLineWithVat(), is_company(), and the
     *   digital services setting. But then reduced by VAT rates we find on the
     *   order lines.
     */
    protected $possibleVatTypes;

    /** @var array[] */
    protected $possibleVatRates;

    /**
     * Constructor.
     */
    public function __construct()
    {
    }

    /**
     * Completes the invoice with default settings that do not depend on shop
     * specific data.
     *
     * @param array $invoice
     *   The invoice to complete.
     * @param int[] $possibleVatTypes
     * @param array[] $possibleVatRates
     *   A response structure where errors and warnings can be added. Any local
     *   messages will be added to arrays under the keys 'errors' and 'warnings'.
     *
     * @return array The completed invoice.
     * The completed invoice.
     */
    public function complete(array $invoice, array $possibleVatTypes, array $possibleVatRates)
    {
        $this->invoice = $invoice;
        $this->invoiceLines = &$this->invoice['customer']['invoice']['line'];
        $this->possibleVatTypes = $possibleVatTypes;
        $this->possibleVatRates = $possibleVatRates;

        $this->completeInvoiceLines();

        return $this->invoice;
    }

    /**
     * Completes the invoice lines.
     */
    protected function completeInvoiceLines()
    {
        $this->completeLineRequiredData();
        $this->correctCalculatedVatRates();
        $this->addVatRateTo0PriceLines();
        $this->completeLineMetaData();
    }

    /**
     * Completes the fields that are required by the rest of this completor phase.
     *
     * The creator filled in the fields that are directly available from the
     * shops' data store. This method completes (if not filled in):
     * - unitprice.
     */
    protected function completeLineRequiredData()
    {
        $invoiceLines = &$this->invoice['customer']['invoice']['line'];
        foreach ($invoiceLines as &$line) {
            $calculatedFields = isset($line['meta-calculated-fields'])
                ? (is_array($line['meta-calculated-fields']) ? $line['meta-calculated-fields'] : explode(',', $line['meta-calculated-fields']))
                : array();

            if (!isset($line['unitprice'])) {
                if (isset($line['unitpriceinc'])) {
                    if (isset($line['vatamount'])) {
                        $line['unitprice'] = $line['unitpriceinc'] - $line['vatamount'];
                        $calculatedFields[] = 'unitprice';
                    } else if (isset($line['vatrate']) && in_array($line['meta-vatrate-source'], Completor::$CorrectVatRateSources)) {
                        $line['unitprice'] = $line['unitpriceinc'] / (100.0 + $line['vatrate']) * 100.0;
                        $calculatedFields[] = 'unitprice';
                    }
                }
            }

            if (!empty($calculatedFields)) {
                $line['meta-calculated-fields'] = implode(',', $calculatedFields);
            }
        }
    }

    /**
     * Try to correct 'calculated' vat rates for rounding errors by matching them
     * with possible vatRates
     */
    protected function correctCalculatedVatRates()
    {
        foreach ($this->invoiceLines as &$line) {
            if (!empty($line['meta-vatrate-source']) && $line['meta-vatrate-source'] === Creator::VatRateSource_Calculated) {
                $line = $this->correctVatRateByRange($line);
            }
        }
    }

    /**
     * Checks and corrects a 'calculated' vat rate to an allowed vat rate.
     *
     * The meta-vatrate-source must be Creator::VatRateSource_Calculated.
     *
     * The check is done on comparing allowed vat rates with the meta-vatrate-min
     * and meta-vatrate-max values. If only 1 match is found that will be used.
     *
     * If multiple matches are found with all equal rates - e.g. Dutch and Belgium
     * 21% - the vat rate will be corrected, but the VAT Type will remain
     * undecided.
     *
     * This method is public to allow a 2nd call to just this method for a single
     * line added after a 1st round of correcting. Do not use unless
     * $this->possibleVAtRates has been initialized
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
        foreach ($this->possibleVatRates as $vatRate) {
            if ($vatRate['vatrate'] >= $line['meta-vatrate-min'] && $vatRate['vatrate'] <= $line['meta-vatrate-max']) {
                $matchedVatRates[] = $vatRate;
            }
        }

        $vatRate = $this->getUniqueVatRate($matchedVatRates);
        if ($vatRate === null || $vatRate === false) {
            // We remove the calculated vatrate
            // @todo: pick closest or pick just 1?
            unset($line['vatrate']);
            $line['meta-vatrate-matches'] = $vatRate === null
                ? 'none'
                : array_reduce($matchedVatRates, function ($carry, $item) {
                    return $carry . ($carry === '' ? '' : ',') . $item['vatrate'] . '(' . $item['vattype'] . ')';
                }, '');
            if (!empty($line['meta-strategy-split'])) {
                // Give the strategy phase a chance to correct this line.
                $line['meta-vatrate-source'] = Creator::VatRateSource_Strategy;
            }
        } else {
            $line['vatrate'] = $vatRate;
            $line['meta-vatrate-source'] = Completor::VatRateSource_Calculated_Corrected;
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
            } else if ($carry == $matchedVatRate['vatrate']) {
                // Note that in PHP: '21' == '21.0000' returns true. So using == works.
                // Vat rate equals all previous vat rates: return that vat rate.
                return $carry;
            } else {
                // Vat rate does not match previous vat rates or carry is already false,
                // return false.
                return false;
            }
        }, null);
        return $result;
    }

    /**
     * Completes lines with free items (price = 0) by giving them the maximum tax
     * rate that appears in the other lines.
     */
    protected function addVatRateTo0PriceLines()
    {
        // Get appearing vat rates and their frequency.
        $vatRates = $this->getAppearingVatRates();

        // Get the highest vat rate.
        $maxVatRate = -1.0;
        foreach ($vatRates as $vatRate => $frequency) {
            if ((float) $vatRate > $maxVatRate) {
                $maxVatRate = (float) $vatRate;
            }
        }

        foreach ($this->invoiceLines as &$line) {
            if ($line['meta-vatrate-source'] === Creator::VatRateSource_Completor && $line['vatrate'] === null && Number::isZero($line['unitprice'])) {
                $line['vatrate'] = $maxVatRate;
                $line['meta-vatrate-source'] = Completor::VatRateSource_Completor_Completed;
            }
        }
    }

    /**
     * Returns a list of vat rates that actually appear in the invoice.
     *
     * @return array
     *  An array with the vat rates as key and the number of times they appear in
     *  the invoice lines as value.
     */
    protected function getAppearingVatRates()
    {
        $vatRates = array();
        foreach ($this->invoiceLines as $line) {
            if (isset($line['vatrate'])) {
                if (isset($vatRates[$line['vatrate']])) {
                    $vatRates[$line['vatrate']]++;
                } else {
                    $vatRates[$line['vatrate']] = 1;
                }
            }
        }
        return $vatRates;
    }

    /**
     * Completes each (non-strategy) line with missing (meta) info.
     *
     * All non strategy lines have unitprice and vatrate filled in and should by
     * now have correct(ed) VAT rates. In some shops these non strategy lines may
     * have a meta-line-discount-vatamount or meta-line-discount-amountinc field,
     * that can be used with the SplitKnownDiscountLine strategy. Complete (if
     * missing):
     * - unitpriceinc
     * - vatamount
     * - meta-line-discount-amountinc (if meta-line-discount-vatamount is
     *   available).
     */
    protected function completeLineMetaData()
    {
        $invoiceLines = &$this->invoice['customer']['invoice']['line'];
        foreach ($invoiceLines as &$line) {
            $calculatedFields = isset($line['meta-calculated-fields'])
                ? (is_array($line['meta-calculated-fields']) ? $line['meta-calculated-fields'] : explode(',', $line['meta-calculated-fields']))
                : array();

            if (in_array($line['meta-vatrate-source'], Completor::$CorrectVatRateSources)) {
                if (!isset($line['unitpriceinc'])) {
                    $line['unitpriceinc'] = $line['unitprice'] / 100.0 * (100.0 + $line['vatrate']);
                    $calculatedFields[] = 'unitpriceinc';
                }

                if (!isset($line['vatamount'])) {
                    $line['vatamount'] = $line['vatrate'] / 100.0 * $line['unitprice'];
                    $calculatedFields[] = 'vatamount';
                }

                if (isset($line['meta-line-discount-vatamount']) && !isset($line['meta-line-discount-amountinc'])) {
                    $line['meta-line-discount-amountinc'] = $line['meta-line-discount-vatamount'] / $line['vatrate'] * (100 + $line['vatrate']);
                    $calculatedFields[] = 'meta-line-discount-amountinc';
                }

                if (!empty($calculatedFields)) {
                    $line['meta-calculated-fields'] = implode(',', $calculatedFields);
                }
            }
        }
    }
}
