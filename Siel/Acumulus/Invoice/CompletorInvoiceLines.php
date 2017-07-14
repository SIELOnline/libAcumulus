<?php
namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Config\ConfigInterface;
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
        $lines = $this->addVatRateToLookupLines($lines);
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
     * @param array|null $parent
     *
     * @return array[] The completed invoice lines.
     * The completed invoice lines.
     */
    protected function completeLineRequiredData(array $lines, array $parent = null)
    {
        foreach ($lines as &$line) {
            // Easy gain first. Known usages: Magento.
            if (!isset($line[Meta::VatAmount]) && isset($line[Meta::LineVatAmount])) {
                $line[Meta::VatAmount] = $line[Meta::LineVatAmount] / $line[Tag::Quantity];
                $line[Meta::CalculatedFields][] = Meta::VatAmount;
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
                    $line[Meta::CalculatedFields][] = Tag::UnitPrice;
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
                    $line[Meta::CalculatedFields][] = Meta::UnitPriceInc;
                }
            }

            if (!isset($line[Tag::VatRate])) {
                if ($line[Meta::VatRateSource] === Creator::VatRateSource_Parent && $parent !== null && Completor::isCorrectVatRate($parent[Meta::VatRateSource])) {
                    $line[Tag::VatRate] = $parent[Tag::VatRate];
                    $line[Meta::VatRateSource] = Completor::VatRateSource_Copied_From_Parent;
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
                    $line[Meta::CalculatedFields][] = Tag::VatRate;
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
            if ($vatRateInfo[Tag::VatRate] >= $line[Meta::VatRateMin] && $vatRateInfo[Tag::VatRate] <= $line[Meta::VatRateMax]) {
                $matchedVatRates[] = $vatRateInfo;
            }
        }

        $vatRate = $this->getUniqueVatRate($matchedVatRates);
        if ($vatRate !== null && $vatRate !== false) {
            // We have a single match: fill it in as the vat rate for this line.
            $line[Tag::VatRate] = $vatRate;
            $line[Meta::VatRateSource] = Completor::VatRateSource_Calculated_Corrected;
        } else {
            // We remove the calculated vatrate.
            unset($line[Tag::VatRate]);
            if ($vatRate === null) {
                $line[Meta::VatRateMatches] = 'none';
            } else {
                $line[Meta::VatRateMatches] = array_reduce($matchedVatRates,
                  function ($carry, $item) {
                      return $carry . ($carry === '' ? '' : ',') . $item[Tag::VatRate] . '(' . $item[Tag::VatType] . ')';
                  }, '');
            }

            // If this line may be split, we make it a strategy line (even
            // though 2 out of the 3 fields ex, inc, and vat are known). This
            // way the strategy phase gets a chance to correct this line.
            if (!empty($line[Meta::StrategySplit])) {
                $line[Meta::VatRateSource] = Creator::VatRateSource_Strategy;
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
        return $result;
    }

    /**
     * Completes lines that have meta-vatrate-lookup(-...) data.
     *
     * Meta-vatrate-lookup data is added by the Creator class using vat rates
     * from the products. However as VAT rates may have changed between the date
     * of the order and now, we cannot fully rely on it and use it only as a
     * last resort, thus after flattening
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
            if ($line[Meta::VatRateSource] === Creator::VatRateSource_Completor && $line[Tag::VatRate] === null && Number::isZero($line[Tag::UnitPrice])) {
                if (!empty($line[Meta::VatRateLookup]) && $this->isPossibleVatRate($line[Meta::VatRateLookup])) {
                    // The vat rate looked up on the product is a possible vat
                    // rate, so apparently that vat rate did not change between
                    // the date of the order and now: take that one.
                    $line[Tag::VatRate] = $line[Meta::VatRateLookup];
                    $line[Meta::VatRateSource] = Completor::VatRateSource_Looked_Up;
                }
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
            if ($line[Meta::VatRateSource] === Creator::VatRateSource_Completor && $line[Tag::VatRate] === null && Number::isZero($line[Tag::UnitPrice])) {
                if ($maxVatRate !== null) {
                    $line[Tag::VatRate] = $maxVatRate;
                    $line[Meta::VatRateSource] = Completor::VatRateSource_Completor_Completed;
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
     *   The maximum vat rate in the given set of lines.
     */
    public static function getMaxAppearingVatRate(array $lines, &$index = 0) {
        $index = null;
        $maxVatRate = -1.0;
        foreach ($lines as $key => $line) {
            if (isset($line[Tag::VatRate]) && (float) $line[Tag::VatRate] > $maxVatRate) {
                $index = $key;
                $maxVatRate = (float) $line[Tag::VatRate];
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
            if (Number::floatsAreEqual($vatRate, $vatRateInfo[Tag::VatRate])) {
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
            if (Completor::isCorrectVatRate($line[Meta::VatRateSource])) {
                if (!isset($line[Meta::UnitPriceInc])) {
                    $line[Meta::UnitPriceInc] = $line[Tag::UnitPrice] / 100.0 * (100.0 + $line[Tag::VatRate]);
                    $line[Meta::CalculatedFields][] = Meta::UnitPriceInc;
                }
                if (!isset($line[Meta::VatAmount])) {
                    $line[Meta::VatAmount] = $line[Tag::VatRate] / 100.0 * $line[Tag::UnitPrice];
                    $line[Meta::CalculatedFields][] = Meta::VatAmount;
                }
                if (isset($line[Meta::LineDiscountVatAmount]) && !isset($line[Meta::LineDiscountAmountInc])) {
                    $line[Meta::LineDiscountAmountInc] = $line[Meta::LineDiscountVatAmount] / $line[Tag::VatRate] * (100 + $line[Tag::VatRate]);
                    $line[Meta::CalculatedFields][] = Meta::LineDiscountAmountInc;
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

            if (isset($line[Meta::CalculatedFields])) {
                $line[Meta::CalculatedFields] = implode(',', array_unique($line[Meta::CalculatedFields]));
            }
        }
        return $lines;
    }
}
