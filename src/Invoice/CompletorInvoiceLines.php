<?php
namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

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
     * based on client country, invoiceHasLineWithVat(), is_company(), and the
     * foreign vat setting.
     *
     * @var int[]
     */
    protected $possibleVatTypes;

    /**
     * The list of possible vat rates, based on the possible vat types and
     * extended with the zero rates (0 and -1 (vat-free)) if they might be
     * applicable.
     *
     * @var array[]
     */
    protected $possibleVatRates;

    /** @var \Siel\Acumulus\Config\Config  */
    protected $config;

    /** @var \Siel\Acumulus\Invoice\Completor */
    protected $completor = null;

    /** @var \Siel\Acumulus\Invoice\FlattenerInvoiceLines */
    protected $invoiceLineFlattener = null;

    /**
     * Constructor.
     *
     * @param \Siel\Acumulus\Invoice\FlattenerInvoiceLines $invoiceLinesFlattener
     * @param \Siel\Acumulus\Config\Config $config
     */
    public function __construct(FlattenerInvoiceLines $invoiceLinesFlattener, Config $config)
    {
        $this->invoiceLineFlattener = $invoiceLinesFlattener;
        $this->config = $config;
    }

    /**
     * Sets the completor (that can be used to call some methods).
     *
     * @param \Siel\Acumulus\Invoice\Completor $completor
     */
    public function setCompletor(Completor $completor)
    {
        $this->completor = $completor;
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

        $invoice = $this->completeInvoiceLinesRecursive($invoice);
        $lines = $invoice[Tag::Customer][Tag::Invoice][Tag::Line];
        $lines = $this->invoiceLineFlattener->complete($lines);
        $lines = $this->completeInvoiceLines($lines);
        $invoice[Tag::Customer][Tag::Invoice][Tag::Line] = $lines;

        return $invoice;
    }

    /**
     * Completes the invoice lines before they are flattened.
     *
     * This means that the lines have to be walked recursively.
     *
     * The actions that can be done this way are those who operate on a line in
     * isolation and thus do not need totals, maximums or things like that.
     *
     * @param array[] $invoice
     *   The invoice with the lines to complete recursively.
     *
     * @return array[]
     *   The invoice with the completed invoice lines.
     */
    protected function completeInvoiceLinesRecursive(array $invoice)
    {
        $lines = $invoice[Tag::Customer][Tag::Invoice][Tag::Line];

        if ($this->completor->shouldConvertCurrency($invoice)) {
            $lines = $this->convertToEuro($lines, $invoice[Tag::Customer][Tag::Invoice][Meta::CurrencyRate]);
        }
        // @todo: we could combine all completor phase methods of getting the
        //   correct vat rate:
        //     - possible vat rates
        //     - filter by range
        //     - filter by tax class = foreign vat (or not)
        //     - filter by lookup vat
        //   Why? addVatRateUsingLookupData() actually already does so, but will
        //   not work when we do have a lookup vat class but not a lookup vat
        //   rate.
        //   This would allow to combine VatRateSource_Calculated and
        //   VatRateSource_Completor.
        // correctCalculatedVatRates() only uses vatrate, meta-vatrate-min, and
        // meta-vatrate-max and may lead to more (required) data filled in, so
        // should be called before completeLineRequiredData().
        $lines = $this->correctCalculatedVatRates($lines);
        // addVatRateToLookupLines() only uses meta-vat-rate-lookup and may lead
        // to more (required) data filled in, so should be called before
        // completeLineRequiredData().
        $lines = $this->addVatRateUsingLookupData($lines);
        $lines = $this->completeLineRequiredData($lines);
        // Completing the required data may lead to new lines that contain
        // calculated VAT rates and thus can be corrected with
        // correctCalculatedVatRates(): call again.
        $lines = $this->correctCalculatedVatRates($lines);

        $invoice[Tag::Customer][Tag::Invoice][Tag::Line] = $lines;
        return $invoice;
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
        $lines = $this->recalculateLineData($lines);
        $lines = $this->completeLineMetaData($lines);
        return $lines;
    }

    /**
     * Converts amounts to euro if another currency was used.
     *
     * This method only converts amounts at the line level. The invoice level
     * is handled by the completor and already has been converted.
     *
     * @param array[] $lines
     *   The invoice lines to convert recursively.
     * @param float $conversionRate
     *
     * @return array[]
     *   The completed invoice lines.
     */
    protected function convertToEuro(array $lines, $conversionRate)
    {
        foreach ($lines as &$line) {
            $this->completor->convertAmount($line, Tag::UnitPrice, $conversionRate);
            // Cost price may well be in purchase currency, let's assume it already is in euros ...
            //$this->completor->convertAmount($line, Tag::CostPrice, $conversionRate);
            $this->completor->convertAmount($line, Meta::UnitPriceInc, $conversionRate);
            $this->completor->convertAmount($line, Meta::VatAmount, $conversionRate);
            $this->completor->convertAmount($line, Meta::LineAmount, $conversionRate);
            $this->completor->convertAmount($line, Meta::LineAmountInc, $conversionRate);
            $this->completor->convertAmount($line, Meta::LineVatAmount, $conversionRate);
            $this->completor->convertAmount($line, Meta::LineDiscountAmount, $conversionRate);
            $this->completor->convertAmount($line, Meta::LineDiscountAmountInc, $conversionRate);
            $this->completor->convertAmount($line, Meta::LineDiscountVatAmount, $conversionRate);

            // Recursively convert any amount.
            if (!empty($line[Meta::ChildrenLines])) {
                $line[Meta::ChildrenLines] = $this->convertToEuro($line[Meta::ChildrenLines], $conversionRate);
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
            if (!empty($line[Meta::VatRateSource]) && $line[Meta::VatRateSource] === Creator::VatRateSource_Calculated) {
                $line = $this->correctVatRateByRange($line);
            }
            if (!empty($line[Meta::ChildrenLines])) {
                $line[Meta::ChildrenLines] = $this->correctCalculatedVatRates($line[Meta::ChildrenLines]);
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
     * remain undecided, unless the vat class could be looked up and thus used
     * to differentiate between national and foreign vat.
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
        $line[Meta::VatRateRangeMatches] = $this->filterVatRateInfosByRange($line[Meta::VatRateMin], $line[Meta::VatRateMax]);
        $vatRate = $this->getUniqueVatRate($line[Meta::VatRateRangeMatches]);

        if ($vatRate === null) {
            // No match at all.
            unset($line[Tag::VatRate]);
            if (!empty($line[Meta::StrategySplit])) {
                // If this line may be split, we make it a strategy line (even
                // though 2 out of the 3 fields ex, inc, and vat are known).
                // This way the strategy phase may be able to correct this line.
                $line[Tag::VatRate] = null;
                $line[Meta::VatRateSource] = Creator::VatRateSource_Strategy;
            } else {
                // Set vatrate to null and try to use lookup data to get a vat
                // rate. It will be invalid but may be better than the "setting
                // to standard 21%".
                $line[Tag::VatRate] = null;
                $line[Meta::VatRateSource] = Creator::VatRateSource_Completor;
                $this->completor->changeInvoiceToConcept('message_warning_no_vatrate', 821);
                // @todo: this can also happen with exact or looked up vat rates
                //   add a checker in the Completor that checks all lines for
                //   no or an incorrect vat rate and changes the invoice into a
                //   concept.
            }
        } elseif ($vatRate === false) {
            // Multiple matches: set vatrate to null and try to use lookup data.
            $line[Tag::VatRate] = null;
            $line[Meta::VatRateSource] = Creator::VatRateSource_Completor;
        } else {
            // Single match: fill it in as the vat rate for this line.
            $line[Tag::VatRate] = $vatRate;
            $line[Meta::VatRateSource] = Completor::VatRateSource_Completor_Range;
        }

        return $line;
    }

    /**
     * Completes lines that have meta-vatrate-lookup(-...) data.
     *
     * Meta-vatrate-lookup data is added by the Creator class using vat rates
     * from the products. However as VAT rates may have changed between the date
     * of the order and now, we cannot fully rely on it and use it only as a(n
     * almost) last resort. In the following cases it may be used:
     * - 0 price: With free products we cannot calculate a vat rate so we have
     *   to rely on lookup.
     * - The calculated vat rate range is so wide that it contains multiple
     *   possible vat rates. If the looked up vat rate is one of them, we use
     *   it.
     *
     * @param array[] $lines
     *   The invoice lines to correct using lookup data.
     *
     * @return array[]
     *   The corrected invoice lines.
     */
    protected function addVatRateUsingLookupData(array $lines)
    {
        foreach ($lines as &$line) {
            if ($line[Meta::VatRateSource] === Creator::VatRateSource_Completor) {
                if (!empty($line[Meta::VatRateLookup])) {
                    $line[Meta::VatRateLookupMatches] = $this->filterVatRateInfosByVatRates($line[Meta::VatRateLookup]);
                    $vatRateSource = Completor::VatRateSource_Completor_Lookup;
                    if (!$this->getUniqueVatRate($line[Meta::VatRateLookupMatches]) && !empty($line[Meta::VatRateRangeMatches])) {
                        // Try to reduce the set by intersecting with the vat
                        // rate range matches.
                        $line[Meta::VatRateLookupMatches] = $this->filterVatRateInfosByVatRates(
                            $line[Meta::VatRateRangeMatches],
                            $line[Meta::VatRateLookupMatches]);
                        $vatRateSource = Completor::VatRateSource_Completor_Range_Lookup;
                    }

                    if (!$this->getUniqueVatRate($line[Meta::VatRateLookupMatches]) && !empty($line[Meta::VatClassId])) {
                        // Try to reduce the set by filtering on the vat type.
                        $line[Meta::VatRateLookupMatches] = $this->filterVatRateInfosByForeignVat(
                            $this->completor->isForeignVatClass($line[Meta::VatClassId]),
                            $line[Meta::VatRateLookupMatches]);
                        $vatRateSource = Completor::VatRateSource_Completor_Range_Lookup_Foreign;
                    }

                    if ($this->getUniqueVatRate($line[Meta::VatRateLookupMatches])) {
                        // Only a single vat rate remains: take that one.
                        $vatRateInfo = reset($line[Meta::VatRateLookupMatches]);
                        $line[Tag::VatRate] = is_array($vatRateInfo) ? $vatRateInfo[Tag::VatRate] : $vatRateInfo;
                        $line[Meta::VatRateSource] = $vatRateSource;
                    } else {
                        // Have the strategy phase give it another try.
                        $line[Meta::VatRateSource] = Creator::VatRateSource_Strategy;
                    }
                } else {
                    // We either do not have lookup data or the looked up vat
                    // rate is not possible. If this is not a 0-price line we
                    // may still have a chance by using the vat range tactics
                    // on the line totals or reverting to the strategy phase if
                    // the line may be split.
                    // For now I am not going to use the line totals as they are
                    // hardly available.
                    $line[Meta::VatRateSource] = Creator::VatRateSource_Strategy;
                }
            }

            // Recursively complete lines using lookup data.
            if (!empty($line[Meta::ChildrenLines])) {
                $line[Meta::ChildrenLines] = $this->addVatRateUsingLookupData($line[Meta::ChildrenLines]);
            }
        }
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
     * @param array|null $parent
     *   The parent line for this set of lines or null if this is the set of
     *   lines at the top level.
     *
     * @return array[]
     *   The completed invoice lines.
     */
    protected function completeLineRequiredData(array $lines, array $parent = null)
    {
        foreach ($lines as &$line) {
            // Easy gain first. Known usages: Magento.
            if (!isset($line[Meta::VatAmount]) && isset($line[Meta::LineVatAmount])) {
                $line[Meta::VatAmount] = $line[Meta::LineVatAmount] / $line[Tag::Quantity];
                $line[Meta::FieldsCalculated][] = Meta::VatAmount . ' (from ' . Meta::LineVatAmount . ')';
            }

            if (!isset($line[Tag::UnitPrice])) {
                // With margin scheme, the unitprice should be known but may
                // have ended up in the unitpriceinc.
                if (isset($line[Tag::CostPrice])) {
                    if (isset($line[Meta::UnitPriceInc])) {
                        $line[Tag::UnitPrice] = $line[Meta::UnitPriceInc];
                    }
                } elseif (isset($line[Meta::UnitPriceInc])) {
                    if (Number::isZero($line[Meta::UnitPriceInc])) {
                        // Free products are free with and without VAT.
                        $line[Tag::UnitPrice] = 0;
                    } elseif (isset($line[Tag::VatRate]) && Completor::isCorrectVatRate($line[Meta::VatRateSource])) {
                         $line[Tag::UnitPrice] = $line[Meta::UnitPriceInc] / (100.0 + $line[Tag::VatRate]) * 100.0;
                    } elseif (isset($line[Meta::VatAmount])) {
                        $line[Tag::UnitPrice] = $line[Meta::UnitPriceInc] - $line[Meta::VatAmount];
                    } //else {
                        // We cannot fill in unitprice reliably, so better to
                        // leave it empty and fail clearly.
                    //}
                    $line[Meta::FieldsCalculated][] = Tag::UnitPrice;
                }
            }

            if (!isset($line[Meta::UnitPriceInc])) {
                // With margin scheme, the unitpriceinc equals unitprice.
                if (isset($line[Tag::CostPrice])) {
                    if (isset($line[Tag::UnitPrice])) {
                        $line[Meta::UnitPriceInc] = $line[Tag::UnitPrice];
                    }
                } elseif (isset($line[Tag::UnitPrice])) {
                    if (Number::isZero($line[Tag::UnitPrice])) {
                        // Free products are free with and without VAT.
                        $line[Meta::UnitPriceInc] = 0;
                    } elseif (isset($line[Tag::VatRate]) && Completor::isCorrectVatRate($line[Meta::VatRateSource])) {
                         $line[Meta::UnitPriceInc] = $line[Tag::UnitPrice] * (100.0 + $line[Tag::VatRate]) / 100.0;
                    } elseif (isset($line[Meta::VatAmount])) {
                        $line[Meta::UnitPriceInc] = $line[Tag::UnitPrice] + $line[Meta::VatAmount];
                    } //else {
                        // We cannot fill in unitpriceinc reliably, so we leave
                        // it empty as it is metadata after all.
                    // }
                    $line[Meta::FieldsCalculated][] = Meta::UnitPriceInc;
                }
            }

            if (!isset($line[Tag::VatRate])) {
                // Can we copy it from the parent?
                if ($line[Meta::VatRateSource] === Creator::VatRateSource_Parent && $parent !== null) {
                    if (Completor::isCorrectVatRate($parent[Meta::VatRateSource])) {
                        $line[Tag::VatRate] = $parent[Tag::VatRate];
                        $line[Meta::VatRateSource] = Completor::VatRateSource_Copied_From_Parent;
                    } else {
                        // Allow strategy phase to also add a vat rate to the
                        // child lines.
                        $line[Meta::VatRateSource] = Creator::VatRateSource_Strategy;
                    }
                } elseif (isset($line[Meta::VatAmount]) && isset($line[Tag::UnitPrice])) {
                    // This may use the easy gain, so known usages: Magento.
                    // Set (overwrite the tag vatrate-source) vatrate and
                    // accompanying tags.
                    $precision = 0.01;
                    // If the amounts are the sum of amounts taken from
                    // children products, the precision may be lower.
                    if (!empty($line[Meta::ChildrenLines])) {
                        $precision *= count($line[Meta::ChildrenLines]);
                    }
                    $line = array_merge($line, Creator::getVatRangeTags($line[Meta::VatAmount], $line[Tag::UnitPrice], $precision, $precision));
                    $line[Meta::FieldsCalculated][] = Tag::VatRate;
                }
            }

            // Recursively complete the required data.
            if (!empty($line[Meta::ChildrenLines])) {
                $line[Meta::ChildrenLines] = $this->completeLineRequiredData($line[Meta::ChildrenLines], $line);
            }
        }
        return $lines;
    }

    /**
     * Determines if all (matched) vat rates are equal.
     *
     * @param array[] $vatRateInfos
     *   Array of vat rate infos.
     *
     * @return float|false|null
     *   If all vat rate in $vatRates are equal,that vat rate, null if
     *   $matchedVatRates is empty, false otherwise (multiple but different vat
     *   rates).
     */
    protected function getUniqueVatRate(array $vatRateInfos)
    {
        return array_reduce($vatRateInfos, function ($carry, $matchedVatRate) {
            if ($carry === null) {
                // 1st item: return its vat rate.
                return $matchedVatRate[Tag::VatRate];
            } elseif ($carry == $matchedVatRate[Tag::VatRate]) {
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
    }

    /**
     * Completes lines with free items (price = 0) by giving them the maximum
     * tax rate that appears in the other lines.
     *
     * These lines already have gone through the addVatRateToLookupLines()
     * method, but either no lookup vat data is available or the looked up vat
     * rate is not a possible vat rate.
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
            $price = isset($line[Tag::UnitPrice]) ? $line[Tag::UnitPrice] : $line[Meta::UnitPriceInc];
            if ($line[Meta::VatRateSource] === Creator::VatRateSource_Completor && Number::isZero($price)) {
                if ($maxVatRate !== null) {
                    $line[Tag::VatRate] = $maxVatRate;
                    $line[Meta::VatRateSource] = Completor::VatRateSource_Completor_Max_Appearing;
                } else {
                    $line[Meta::VatRateSource] = Creator::VatRateSource_Strategy;
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
     *   The maximum vat rate in the given set of lines or null if no vat rates
     *   could be found.
     */
    public static function getMaxAppearingVatRate(array $lines, &$index = 0)
    {
        $index = null;
        $maxVatRate = -1.0;
        foreach ($lines as $key => $line) {
            if (isset($line[Tag::VatRate]) && (float) $line[Tag::VatRate] > $maxVatRate) {
                $index = $key;
                $maxVatRate = (float) $line[Tag::VatRate];
            }
        }
        return $index !== null ? $maxVatRate : null;
    }

    /**
     * Returns the set of possible vat rates that fall in the given vat range.
     *
     * @param float $min
     * @param float $max
     * @param array|null $vatRateInfos
     *   The set of vat rate infos to filter. If not given, the property
     *   $this->possibleVatRates is used.
     *
     * @return array[]
     *   The, possibly empty, set of vat rate infos that have a vat rate that
     *   falls within the given vat range.
     */
    protected function filterVatRateInfosByRange($min, $max, array $vatRateInfos = null)
    {
        if ($vatRateInfos === null) {
            $vatRateInfos = $this->possibleVatRates;
        }

        $result = array();
        foreach ($vatRateInfos as $vatRateInfo) {
            $vatRate = is_array($vatRateInfo) ? $vatRateInfo[Tag::VatRate] : $vatRateInfo;
            if ($min <= $vatRate && $vatRate <= $max) {
                $result[] = $vatRateInfo;
            }
        }
        return $result;
    }

    /**
     * Returns the subset of the vat rate infos that have a vat rate that appears within the given set of vat rates.
     *
     * @param float|float[] $vatRates
     *   The vat rate(s) to filter.
     *
     * @param array|null $vatRateInfos
     *   The set of vat rate infos to filter. If not given, the property
     *   $this->possibleVatRates is used.
     *
     * @return array[]
     *   The, possibly empty, set of vat rate infos that have a vat rate that
     *   appears within the set of $vatRates.
     */
    protected function filterVatRateInfosByVatRates($vatRates, array $vatRateInfos = null)
    {
        $vatRates = (array) $vatRates;
        if ($vatRateInfos === null) {
            $vatRateInfos = $this->possibleVatRates;
        }

        $result = array();
        foreach ($vatRateInfos as $vatRateInfo) {
            $vatRate = $vatRateInfo[Tag::VatRate];
            foreach ($vatRates as $vatRateInfo2) {
                $vatRate2 = is_array($vatRateInfo2) ? $vatRateInfo2[Tag::VatRate] : $vatRateInfo2;
                if (Number::floatsAreEqual($vatRate, $vatRate2)) {
                    $result[] = $vatRateInfo;
                }
            }
        }
        return $result;
    }

    /**
     * Returns the subset of the vat rate infos that have (or do not have) a foreign vat type.
     *
     * @param bool $isForeignVatType
     *   True to filter on vat type = Api::VatType_ForeignVat, false to filter
     *   on vat type != Api::VatType_ForeignVat.
     * @param array|null $vatRateInfos
     *   The set of vat rate infos to filter. If not given, the property
     *   $this->possibleVatRates is used.
     *
     * @return array[]
     *   The, possibly empty, set of vat rate infos that have a vat type that
     *   have (or do not have) a foreign vat type.
     */
    protected function filterVatRateInfosByForeignVat($isForeignVatType, array $vatRateInfos = null)
    {
        if ($vatRateInfos === null) {
            $vatRateInfos = $this->possibleVatRates;
        }

        $result = array();
        foreach ($vatRateInfos as $vatRateInfo) {
            if (($vatRateInfo[Tag::VatType] === Api::VatType_ForeignVat) === $isForeignVatType) {
                $result[] = $vatRateInfo;
            }
        }
        return $result;
    }

    /**
     * Recalculates the unitprice(inc) for lines that indicate so.
     *
     * PRE: All non strategy invoice lines have unitprice and vatrate filled in
     * and should by now have correct(ed) VAT rates. In some shops the unitprice
     * or unitpriceinc is imprecise because they are returned rounded to the
     * cent.
     *
     * To prevent differences between the Acumulus and shop invoice (or between
     * the invoice and line totals) we recompute the unitprice if:
     * - vatrate is correct.
     * - meta-recalculate-price is set to Tag::UnitPrice. (Shops should set
     *   so, if prices are entered inc vat and the price ex vat as obtained by
     *   this plugin is known to have a precision worse than 0.0001.
     * - unitpriceinc is available.
     *
     * We recompute the unitpriceinc if:
     * - vatrate is correct.
     * - meta-recalculate-price is set to Meta:UnitPriceInc. (Shops should set
     *   so, if prices are entered ex vat and the price inc vat as obtained by
     *   this plugin is known to have a precision worse than 0.0001.
     * - unitprice is available.
     *
     * @param array[] $lines
     *   The invoice lines to recalculate.
     *
     * @return array[]
     *   The recalculated invoice lines.
     */
    protected function recalculateLineData(array $lines)
    {
        foreach ($lines as &$line) {
            if (!empty($line[Meta::RecalculatePrice]) && Completor::isCorrectVatRate($line[Meta::VatRateSource]) && isset($line[Meta::UnitPriceInc])) {
                if ($line[Meta::RecalculatePrice] === Tag::UnitPrice) {
                    $line[Meta::RecalculateOldPrice] = $line[Tag::UnitPrice];
                    $line[Tag::UnitPrice] = $line[Meta::UnitPriceInc] / (100 + $line[Tag::VatRate]) * 100;
                    $line[Meta::RecalculatedPrice] = true;
                } else {
                    // $line[Meta::RecalculateUnitPrice] === Meta::UnitPriceInc
                    $line[Meta::RecalculateOldPrice] = $line[Meta::UnitPriceInc];
                    $line[Meta::UnitPriceInc] = (1 + $line[Tag::VatRate] / 100) * $line[Tag::UnitPrice];
                    $line[Meta::RecalculatedPrice] = true;
                }
            }
        }
        return $lines;
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
     * line strategy, we need to know the line totals.
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
            if (Completor::isCorrectVatRate($line[Meta::VatRateSource])) {
                if (!isset($line[Meta::UnitPriceInc])) {
                    $line[Meta::UnitPriceInc] = $line[Tag::UnitPrice] / 100.0 * (100.0 + $line[Tag::VatRate]);
                    $line[Meta::FieldsCalculated][] = Meta::UnitPriceInc;
                }
                if (!isset($line[Meta::VatAmount])) {
                    $line[Meta::VatAmount] = $line[Tag::VatRate] / 100.0 * $line[Tag::UnitPrice];
                    $line[Meta::FieldsCalculated][] = Meta::VatAmount. ' (from ' . Tag::VatRate . ')';
                }
                if (isset($line[Meta::LineDiscountAmount]) && !isset($line[Meta::LineDiscountAmountInc])) {
                    $line[Meta::LineDiscountAmountInc] = $line[Meta::LineDiscountAmount] / 100.0 * (100.0 + $line[Tag::VatRate]);
                    $line[Meta::FieldsCalculated][] = Meta::LineDiscountAmountInc;
                }
                elseif (isset($line[Meta::LineDiscountVatAmount]) && !isset($line[Meta::LineDiscountAmountInc])) {
                    $line[Meta::LineDiscountAmountInc] = $line[Meta::LineDiscountVatAmount] / $line[Tag::VatRate] * (100 + $line[Tag::VatRate]);
                    $line[Meta::FieldsCalculated][] = Meta::LineDiscountAmountInc;
                }
            } elseif ($line[Meta::VatRateSource] == Creator::VatRateSource_Strategy && !empty($line[Meta::StrategySplit])) {
                if (isset($line[Tag::UnitPrice]) && isset($line[Meta::UnitPriceInc])) {
                    if (!isset($line[Meta::LineAmount])) {
                        $line[Meta::LineAmount] = $line[Tag::UnitPrice] * $line[Tag::Quantity];
                    }
                    if (!isset($line[Meta::LineAmountInc])) {
                        $line[Meta::LineAmountInc] = $line[Meta::UnitPriceInc] * $line[Tag::Quantity];
                    }
                }
            }
        }
        return $lines;
    }
}
