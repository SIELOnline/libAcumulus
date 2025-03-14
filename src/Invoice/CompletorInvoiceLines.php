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
 * @noinspection DuplicatedCode  During the transition to Collectors, duplicate code will exist.
 */

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Meta;

use function count;
use function is_scalar;

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
 * - Completes metadata that may be used in the strategy phase or just for
 *   support purposes.
 */
class CompletorInvoiceLines
{
    /**
     * @var int[]
     *   The list of possible vat types, initially filled with possible vat
     *   types based on client country, invoiceHasLineWithVat(), is_company(),
     *   and the EU vat setting.
     */
    protected array $possibleVatTypes;
    /**
     * @var array[]
     *   The list of possible vat rates, based on the possible vat types and
     *   extended with the zero rates (0 and -1 (vat-free)) if they might be
     *   applicable.
     */
    protected array $possibleVatRates;
    protected Config $config;
    protected Completor $completor;
    protected FlattenerInvoiceLines $invoiceLineFlattener;

    public function __construct(FlattenerInvoiceLines $invoiceLinesFlattener, Config $config)
    {
        $this->invoiceLineFlattener = $invoiceLinesFlattener;
        $this->config = $config;
    }

    /**
     * Sets the completor (just so we can call some convenience methods).
     */
    public function setCompletor(Completor $completor): void
    {
        $this->completor = $completor;
    }

    /**
     * Completes the invoice with default settings that do not depend on shop
     * specific data.
     *
     * @param Invoice $invoice
     *   The invoice to complete.
     * @param int[] $possibleVatTypes
     * @param array[] $possibleVatRates
     */
    public function complete(Invoice $invoice, array $possibleVatTypes, array $possibleVatRates): void
    {
        $this->possibleVatTypes = $possibleVatTypes;
        $this->possibleVatRates = $possibleVatRates;

        $this->completeInvoiceLinesRecursive($invoice);
        $this->invoiceLineFlattener->complete($invoice);
        $this->completeInvoiceLines($invoice);
    }

    /**
     * Completes the invoice lines before they are flattened.
     *
     * This means that the lines have to be walked recursively.
     *
     * The actions that can be done this way are those who operate on a line in
     * isolation and thus do not need totals, maximums or things like that.
     *
     * @param Invoice $invoice
     *   The invoice with the lines to complete recursively.
     */
    protected function completeInvoiceLinesRecursive(Invoice $invoice): void
    {
        $this->convertLinesToEuro($invoice->getLines(), $invoice->metadataGet(Meta::Currency));
        // @todo: we could combine all completor phase methods of getting the
        //   correct vat rate:
        //     - possible vat rates
        //     - filter by range
        //     - filter by tax class = EU vat (or not)
        //     - filter by lookup vat
        //   Why? addVatRateUsingLookupData() actually already does so, but will
        //   not work when we do have a lookup vat class but not a lookup vat
        //   rate.
        //   This would allow to combine VatRateSource_Calculated and
        //   VatRateSource_Completor.
        // correctCalculatedVatRates() only uses 'vatrate', 'meta-vatrate-min',
        // and 'meta-vatrate-max' and may lead to more (required) data filled
        // in, so should be called before completeLineRequiredData().
        $this->correctCalculatedVatRates($invoice->getLines());
        // addVatRateUsingLookupData() only uses 'meta-vat-rate-lookup' and may
        // lead to more (required) data filled in, so should be called before
        // completeLineRequiredData().
        $this->addVatRateUsingLookupData($invoice->getLines());
        $this->completeLineRequiredData($invoice->getLines());
        // Completing the required data may lead to new lines that contain
        // calculated VAT rates and thus can be corrected with
        // correctCalculatedVatRates(): call again.
        $this->correctCalculatedVatRates($invoice->getLines());
    }

    /**
     * Completes the invoice lines after they have been flattened.
     *
     * @param \Siel\Acumulus\Data\Invoice $invoice
     */
    protected function completeInvoiceLines(Invoice $invoice): void
    {
        $this->addNatureToNonItemLines($invoice->getLines());
        $this->addVatRateTo0PriceLines($invoice->getLines());
        $this->recalculateLineData($invoice->getLines());
        $this->completeLineMetaData($invoice->getLines());
    }

    /**
     * Converts amounts to euro if another currency was used.
     *
     * This method only converts amounts at the line level. The invoice level is handled
     * by the completor and already has been converted.
     *
     * @param Line[] $lines
     *   The invoice lines to (recursively) convert.
     * @param Currency $currency
     *   The rate of the Euro expressed in the foreign currency.
     */
    protected function convertLinesToEuro(array $lines, Currency $currency): void
    {
        if ($currency->shouldConvert()) {
            foreach ($lines as $line) {
                $line->unitPrice = $currency->convertAmount($line->unitPrice);
                // Cost price may well be in purchase currency, let's assume it already is in euros ...
                //$this->completor->convertAmount($line, Fld::CostPrice, $conversionRate);
                $this->convertAmount($line, Meta::UnitPriceInc, $currency);
                $this->convertAmount($line, Meta::VatAmount, $currency);
                $this->convertAmount($line, Meta::LineAmount, $currency);
                $this->convertAmount($line, Meta::LineAmountInc, $currency);
                $this->convertAmount($line, Meta::LineVatAmount, $currency);
                $this->convertAmount($line, Meta::LineDiscountAmount, $currency);
                $this->convertAmount($line, Meta::LineDiscountAmountInc, $currency);
                $this->convertAmount($line, Meta::LineDiscountVatAmount, $currency);

                // Recursively convert any amount.
                if ($line->hasChildren()) {
                    $this->convertLinesToEuro($line->getChildren(), $currency);
                }
            }
        }
    }

    /**
     * Helper method to convert an amount field to euros.
     *
     * @param \Siel\Acumulus\Data\Line $line
     * @param string $key
     * @param Currency $currency
     *
     * @return bool
     *   Whether the amount was converted.
     */
    protected function convertAmount(Line $line, string $key, Currency $currency): bool
    {
        if ($line->metadataExists($key)) {
            $line->metadataSet($key, $currency->convertAmount($line->metadataGet($key)));
            return true;
        }
        return false;
    }

    /**
     * Corrects 'calculated' vat rates.
     *
     * Tries to correct 'calculated' vat rates for rounding errors by matching
     * them with possible vatRates obtained from the vat lookup service.
     *
     * @param Line[] $lines
     *   The invoice lines to correct.
     */
    protected function correctCalculatedVatRates(array $lines): void
    {
        foreach ($lines as $line) {
            if ($line->metadataGet(Meta::VatRateSource) === VatRateSource::Calculated) {
                $this->correctVatRateByRange($line);
            }
            if ($line->hasChildren()) {
                $this->correctCalculatedVatRates($line->getChildren());
            }
        }
    }

    /**
     * Checks and corrects a 'calculated' vat rate to an allowed vat rate.
     *
     * The 'meta-vatrate-source' must be VatRateSource::Calculated.
     *
     * The check is done on comparing allowed vat rates with the
     * 'meta-vatrate-min' and 'meta-vatrate-max' values. If only 1 match is
     * found that will be used.
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
     * @param Line $line
     *   An invoice line with a calculated vat rate.
     */
    public function correctVatRateByRange(Line $line): void
    {
        $line->metadataAddMultiple(
            Meta::VatRateRangeMatches,
            $this->filterVatRateInfosByRange($line->metadataGet(Meta::VatRateMin), $line->metadataGet(Meta::VatRateMax))
        );
        $vatRate = $this->getUniqueVatRate($line->metadataGet(Meta::VatRateRangeMatches));

        if ($vatRate === null) {
            // No match at all.
            unset($line->vatRate);
            if ($line->metadataGet(Meta::StrategySplit)) {
                // If this line may be split, we make it a strategy line (even
                // though 2 out of the 3 fields ex, inc, and vat are known).
                // This way the strategy phase may be able to correct this line.
                $line->vatRate = null;
                $line->metadataSet(Meta::VatRateSource, VatRateSource::Strategy);
            } else {
                // Set vat rate to null and try to use lookup data to get a vat
                // rate. It will be invalid but may be better than the "setting
                // to standard 21%".
                $line->vatRate = null;
                $line->metadataSet(Meta::VatRateSource, VatRateSource::Completor);
                $this->completor->changeInvoiceToConcept($line, 'message_warning_no_vatrate', 821);
                // @todo: this can also happen with exact or looked up vat rates
                //   add a checker in the Completor that checks all lines for
                //   no or an incorrect vat rate and changes the invoice into a
                //   concept.
            }
        } elseif ($vatRate === false) {
            // Multiple matches: set vat rate to null and try to use lookup data.
            $line->vatRate = null;
            $line->metadataSet(Meta::VatRateSource, VatRateSource::Completor);
        } else {
            // Single match: fill it in as the vat rate for this line.
            $line->vatRate = $vatRate;
            $line->metadataSet(Meta::VatRateSource, VatRateSource::Completor_Range);
        }
    }

    /**
     * Completes lines that have 'meta-vatrate-lookup(-...)' data.
     *
     * Vat rate lookup metadata is added by the Creator class using vat rates
     * from the product info. However, as VAT rates may have changed between the
     * date of the order and now, we cannot fully rely on it and use it only as
     * a(n almost) last resort.
     *
     * We filter the looked up vat rate(s) against:
     * - The possible vat rates (given the possible vat types).
     * - The vat rate range (using the value of Meta::VatRateRangeMatches, if
     *   set).
     * - If still multiple vat rate (infos) remains, we filter by national
     *   versus EU vat (e.g. to distinguish between NL and BE 21%).
     *
     * In the following cases it may be used:
     *   1. The calculated vat rate range is so wide that it contains multiple
     *      possible vat rates. If the looked up vat rate is one of them, we use
     *      it.
     *   2. 0 price: With free products we cannot calculate a vat rate, so we
     *      have to rely on lookup. However, if reversed vat or another 0-vat
     *      vat type is possible, we cannot blindly choose the lookup rate and
     *      should rely on the strategy phase.
     *
     * @param Line[] $lines
     *   The invoice lines to correct using lookup data.
     */
    protected function addVatRateUsingLookupData(array $lines): void
    {
        foreach ($lines as $line) {
            if ($line->metadataGet(Meta::VatRateSource) === VatRateSource::Completor) {
                // Do we have lookup data and not the exception for situation 2?
                // Required data is not guaranteed to be available at this
                // stage, so use the price that is available: both will be zero
                // or both will be not zero.
                $price = $line->unitPrice ?? $line->metadataGet(Meta::UnitPriceInc);
                if (!empty($line->metadataGet(Meta::VatRateLookup))
                    && (!Number::isZero($price) || !$this->completor->is0VatVatTypePossible())
                ) {
                    // Filter lookup rate(s) by the rates of the possible vat types.
                    $line->metadataSet(
                        Meta::VatRateLookupMatches,
                        $this->filterVatRateInfosByVatRates($line->metadataGet(Meta::VatRateLookup))
                    );
                    $vatRateSource = VatRateSource::Completor_Lookup;

                    // Try to reduce the set by intersecting with the vat rate
                    // range matches.
                    if (!$this->getUniqueVatRate($line->metadataGet(Meta::VatRateLookupMatches))
                        && !empty($line->metadataGet(Meta::VatRateRangeMatches))
                    ) {
                        $line->metadataSet(
                            Meta::VatRateLookupMatches,
                            $this->filterVatRateInfosByVatRates(
                                $line->metadataGet(Meta::VatRateRangeMatches),
                                $line->metadataGet(Meta::VatRateLookupMatches)
                            )
                        );
                        $vatRateSource = VatRateSource::Completor_Range_Lookup;
                    }

                    if ($this->getUniqueVatRate($line->metadataGet(Meta::VatRateLookupMatches))) {
                        // Only a single vat rate remains: take that one.
                        $vatRateInfo = current($line->metadataGet(Meta::VatRateLookupMatches));
                        $line->vatRate = !is_scalar($vatRateInfo) ? $vatRateInfo[Fld::VatRate] : $vatRateInfo;
                        $line->metadataSet(Meta::VatRateSource, $vatRateSource);
                    }
                }

                if ($line->metadataGet(Meta::VatRateSource) === VatRateSource::Completor) {
                    // We either do not have lookup data, the looked up vat rate
                    // is not possible, or we have a 0-price line with multiple
                    // vat rates possible: give the strategy phase a chance to
                    // resolve.
                    //
                    // Note: if this is not a 0-price line we may still have a
                    // chance by using the vat range tactics on the line totals
                    // (as that can be more precise with small prices and large
                    // quantities). However, for now, I am not going to use the
                    // line totals as they are hardly available.
                    $line->metadataSet(Meta::VatRateSource, VatRateSource::Strategy);
                }
            }

            // Recursively complete lines using lookup data.
            if ($line->hasChildren()) {
                $this->addVatRateUsingLookupData($line->getChildren());
            }
        }
    }

    /**
     * Completes fields that are required by the rest of this completor phase.
     *
     * The creator filled in the fields that are directly available from the
     * shops' data store. This method completes (if not filled in):
     * - 'unitPrice'
     * - 'vatAmount'
     * - 'unitPriceInc'
     *
     * @param Line[] $lines
     *   The invoice lines to complete with required data.
     * @param Line|null $parent
     *   The parent line for this set of lines or null if this is the set of
     *   lines at the top level.
     *
     * @noinspection PhpFunctionCyclomaticComplexityInspection
     */
    protected function completeLineRequiredData(array $lines, ?Line $parent = null): void
    {
        foreach ($lines as $line) {
            $fieldsCalculated = [];
            // Easy gains first. Known usages: Magento.
            if (!$line->metadataExists(Meta::VatAmount) && $line->metadataExists(Meta::LineVatAmount)) {
                // Known usages: Magento.
                $line->metadataSet(Meta::VatAmount, $line->metadataGet(Meta::LineVatAmount) / $line->quantity);
                $fieldsCalculated[] = Meta::VatAmount . ' (from ' . Meta::LineVatAmount . ')';
            }
            if (!$line->metadataExists(Meta::SubType) && $parent !== null) {
                // Known usages: WooCommerce TM Extra Product Options that adds
                // child lines.
                $line->metadataSet(Meta::SubType, $parent->metadataGet(Meta::SubType));
            }

            if (!isset($line->unitPrice)) {
                // With margin scheme, the unit price should be known but may
                // have ended up in the unit price inc.
                if (isset($line->costPrice)) {
                    if ($line->metadataExists(Meta::UnitPriceInc)) {
                        $line->unitPrice = $line->metadataGet(Meta::UnitPriceInc);
                    }
                } elseif ($line->metadataExists(Meta::UnitPriceInc)) {
                    if (Number::isZero($line->metadataGet(Meta::UnitPriceInc))) {
                        // Free products are free with and without VAT.
                        $line->unitPrice = 0;
                    } elseif (isset($line->vatRate) && Completor::isCorrectVatRate($line->metadataGet(Meta::VatRateSource))) {
                        $line->unitPrice = $this->completor->isNoVat($line->vatRate)
                            ? $line->metadataGet(Meta::UnitPriceInc)
                            : $line->metadataGet(Meta::UnitPriceInc) / (100.0 + $line->vatRate) * 100.0;
                    } elseif ($line->metadataExists(Meta::VatAmount)) {
                        $line->unitPrice = $line->metadataGet(Meta::UnitPriceInc) - $line->metadataGet(Meta::VatAmount);
                    } // else {
                    //     We cannot fill in unit price reliably, so better to
                    //     leave it empty and fail clearly.
                    // }
                    $fieldsCalculated[] = Fld::UnitPrice;
                }
            }

            if (!$line->metadataExists(Meta::UnitPriceInc)) {
                // With margin scheme, the unit price inc equals unit price.
                if (isset($line->costPrice)) {
                    if (isset($line->unitPrice)) {
                        $line->metadataSet(Meta::UnitPriceInc, $line->unitPrice);
                    }
                } elseif (isset($line->unitPrice)) {
                    if (Number::isZero($line->unitPrice)) {
                        // Free products are free with and without VAT.
                        $line->metadataSet(Meta::UnitPriceInc, 0);
                    } elseif (isset($line->vatRate) && Completor::isCorrectVatRate($line->metadataGet(Meta::VatRateSource))) {
                        $line->metadataSet(
                            Meta::UnitPriceInc,
                            $this->completor->isNoVat($line->vatRate)
                                ? $line->unitPrice
                                : $line->unitPrice * (100.0 + $line->vatRate) / 100.0
                        );
                    } elseif ($line->metadataExists(Meta::VatAmount)) {
                        $line->metadataSet(Meta::UnitPriceInc, $line->unitPrice + $line->metadataGet(Meta::VatAmount));
                    } // else {
                    //     We cannot fill in unit price inc reliably, so we
                    //     leave it empty as it is metadata after all.
                    // }
                    $fieldsCalculated[] = Meta::UnitPriceInc;
                }
            }

            if (!isset($line->vatRate)) {
                // Can we copy it from the parent?
                if ($line->metadataGet(Meta::VatRateSource) === VatRateSource::Parent && $parent !== null) {
                    if (Completor::isCorrectVatRate($parent->metadataGet(Meta::VatRateSource))) {
                        $line->vatRate = $parent->vatRate;
                        $line->metadataSet(Meta::VatRateSource, VatRateSource::Copied_From_Parent);
                    } else {
                        // Allow strategy phase to also add a vat rate to the child lines.
                        $line->metadataSet(Meta::VatRateSource, VatRateSource::Strategy);
                    }
                } elseif ($line->metadataExists(Meta::VatAmount) && isset($line->unitPrice)) {
                    // This may use the easy gain. Known usages: Magento.
                    // Set (overwrite the tag vatrate-source) 'vatrate' and
                    // accompanying tags.
                    $precision = 0.01;
                    // If the amounts are the sum of amounts taken from
                    // children products, the precision may be lower.
                    if ($line->hasChildren()) {
                        $precision *= count($line->getChildren());
                    }
                    $line->metadataSet(Meta::PrecisionUnitPrice, $precision);
                    $line->metadataSet(Meta::PrecisionVatAmount, $precision);
                    $this->completor->getCompletorTask('Line', 'VatRange')->complete($line);
                    $fieldsCalculated[] = Fld::VatRate;
                }
            }

            if (count($fieldsCalculated) > 0) {
                $line->metadataSet(Meta::FieldsCalculated, $fieldsCalculated);
            }

            // Recursively complete the required data.
            if ($line->hasChildren()) {
                $this->completeLineRequiredData($line->getChildren(), $line);
            }
        }
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
    protected function getUniqueVatRate(array $vatRateInfos): float|bool|null
    {
        return array_reduce($vatRateInfos, static function ($carry, $matchedVatRate) {
            if ($carry === null) {
                // 1st item: return its vat rate.
                return (float) $matchedVatRate[Fld::VatRate];
            } elseif ($carry !== false && Number::floatsAreEqual($carry, $matchedVatRate[Fld::VatRate])) {
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
     * Adds the nature tag to the non-item lines.
     *
     * The nature tag indicates the nature of the order line: product or
     * service. However, for accompanying services like shipping or payment
     * fees, the nature should follow the major part of the "real" order items.
     *
     * @param Line[] $lines
     */
    protected function addNatureToNonItemLines(array $lines): void
    {
        $nature = $this->getMaxAppearingNature($lines);
        if (!empty($nature)) {
            foreach ($lines as $line) {
                if ($line->metadataExists(Meta::SubType) && $line->metadataGet(Meta::SubType) !== LineType::Item && !isset($line->nature)) {
                    $line->nature = $nature;
                }
            }
        }
    }

    /**
     * Returns the nature that forms the major part of the invoice amount.
     *
     * Notes:
     * - We take the abs value to correctly cover credit invoices. This won't
     *   disturb discount lines, see the following note.
     * - If discounts appear on separate lines, they won't have a nature field.
     *   If such a discount was meant for certain lines only, it should get the
     *   nature of these lines (and subsequently be used to calculate the major
     *   part). However, we do not know for which lines it was meant, so we
     *   treat them like the other extra lines.
     *
     * @param Line[] $lines
     *   The invoice lines to search.
     *
     * @return string
     *   The nature that forms the major part of the amount of all order item
     *   lines (hoofdbestanddeel). Can be the empty string to indicate that no
     *   nature is known for the major part.
     */
    protected function getMaxAppearingNature(array $lines): string
    {
        $amountPerNature = ['' => 0.0, Api::Nature_Product => 0.0, Api::Nature_Service => 0.0];
        foreach ($lines as $line) {
            if ($line->metadataExists(Meta::SubType) && $line->metadataGet(Meta::SubType) === LineType::Item) {
                $nature = !empty($line->nature) ? $line->nature : '';
                $amount = abs($line->quantity * $line->unitPrice);
                $amountPerNature[$nature] += $amount;
            }
        }
        arsort($amountPerNature, SORT_NUMERIC);
        return array_key_first($amountPerNature);
    }

    /**
     * Completes lines with free items (price = 0) by giving them the maximum
     * tax rate that appears in the other lines.
     *
     * These lines already have gone through the addVatRateUsingLookupData()
     * method, but either no lookup vat data is available or the looked up vat
     * rate is not a possible vat rate.
     *
     * @param Line[] $lines
     *   The invoice lines to correct by adding a vat rate to 0 amounts.
     */
    protected function addVatRateTo0PriceLines(array $lines): void
    {
        // Get the highest appearing vat rate. We could get the most often
        // appearing vat rate, but IMO the highest vat rate will be more likely
        // to be correct.
        $maxVatRate = self::getMaxAppearingVatRate($lines);

        foreach ($lines as $line) {
            $price = $line->unitPrice ?? $line->metadataGet(Meta::UnitPriceInc);
            if ($line->metadataGet(Meta::VatRateSource) === VatRateSource::Completor && Number::isZero($price)) {
                if ($maxVatRate !== null) {
                    $line->vatRate = $maxVatRate;
                    $line->metadataSet(Meta::VatRateSource, VatRateSource::Completor_Max_Appearing);
                } else {
                    $line->metadataSet(Meta::VatRateSource, VatRateSource::Strategy);
                }
            }
        }
    }

    /**
     * Returns the maximum vat rate that appears in the given set of lines.
     *
     * @param Line[] $lines
     *   The invoice lines to search.
     * @param ?int $index
     *   If passed, the index of the max vat rate is returned via this parameter.
     *
     * @return float|null
     *   The maximum vat rate in the given set of lines or null if no vat rates
     *   could be found.
     */
    public static function getMaxAppearingVatRate(array $lines, ?int &$index = null): ?float
    {
        $index = null;
        $maxVatRate = -1.0;
        foreach ($lines as $key => $line) {
            if (isset($line->vatRate) && $line->vatRate > $maxVatRate) {
                /** @noinspection CallableParameterUseCaseInTypeContextInspection will always be an int */
                $index = $key;
                $maxVatRate = $line->vatRate;
            }
        }
        return $index !== null ? $maxVatRate : null;
    }

    /**
     * Returns the set of possible vat rates that fall in the given vat range.
     *
     * @param array|null $vatRateInfos
     *   The set of vat rate infos to filter. If not given, the property
     *   $this->possibleVatRates is used.
     *
     * @return array[]
     *   The, possibly empty, set of vat rate infos that have a vat rate that
     *   falls within the given vat range.
     */
    protected function filterVatRateInfosByRange(float $min, float $max, ?array $vatRateInfos = null): array
    {
        if ($vatRateInfos === null) {
            $vatRateInfos = $this->possibleVatRates;
        }

        $result = [];
        foreach ($vatRateInfos as $vatRateInfo) {
            if (is_scalar($vatRateInfo)) {
                $vatRateInfo = (float) $vatRateInfo;
                $vatRate = $vatRateInfo;
            } else {
                $vatRateInfo[Fld::VatRate] = (float) $vatRateInfo[Fld::VatRate];
                $vatRate = $vatRateInfo[Fld::VatRate];
            }
            if ($min <= $vatRate && $vatRate <= $max) {
                $result[] = $vatRateInfo;
            }
        }
        return $result;
    }

    /**
     * Returns the subset of the vat rate infos that have a vat rate that
     * appears within the given set of vat rates.
     *
     * @param float|array|array[]|float[] $vatRates
     *   The vat rate(s) or vat rate info(s) to filter against.
     * @param array|null $vatRateInfos
     *   The set of vat rate infos to filter. If not given, the property
     *   $this->possibleVatRates is used.
     *
     * @return array[]
     *   The, possibly empty, set of $vatRateInfos that have a vat rate that
     *   appears within the set of $vatRates.
     */
    protected function filterVatRateInfosByVatRates(float|array $vatRates, ?array $vatRateInfos = null): array
    {
        $vatRates = (array) $vatRates;
        if ($vatRateInfos === null) {
            $vatRateInfos = $this->possibleVatRates;
        }

        $result = [];
        foreach ($vatRateInfos as $vatRateInfo) {
            $vatRate = $vatRateInfo[Fld::VatRate];
            foreach ($vatRates as $vatRateInfo2) {
                $vatRate2 = !is_scalar($vatRateInfo2) ? $vatRateInfo2[Fld::VatRate] : $vatRateInfo2;
                if (Number::floatsAreEqual($vatRate, $vatRate2)) {
                    $vatRateInfo[Fld::VatRate] = (float) $vatRateInfo[Fld::VatRate];
                    $result[] = $vatRateInfo;
                }
            }
        }
        return $result;
    }

    /**
     * Recalculates the 'unitPrice(Inc)' for lines that indicate so.
     *
     * PRE: All non strategy invoice lines have 'unitPrice' and 'vatrate' filled
     * in and should by now have correct(ed) VAT rates. In some shops the
     * 'unitPrice' or 'unitPriceInc' is imprecise because they are returned
     * rounded to the cent.
     *
     * To prevent differences between the Acumulus and shop invoice (or between
     * the invoice and line totals) we recompute the 'unitPrice' if:
     * - Vat rate is correct.
     * - 'meta-recalculate-price' is set to Fld::UnitPrice. Shops should set this if
     *   prices are entered inc vat and the price ex vat as obtained by this plugin is
     *   known to have a precision worse than 0.0001.
     * - Unit price inc is available.
     *
     * We recompute the unit price inc if:
     * - Vat rate is correct.
     * - 'meta-recalculate-price' is set to Meta:UnitPriceInc. Shops should set this if
     *   prices are entered ex vat and the price inc vat as obtained by this plugin is
     *   known to have a precision worse than 0.0001.
     * - Unit price is available.
     *
     * @param Line[] $lines
     *   The invoice lines to recalculate.
     */
    protected function recalculateLineData(array $lines): void
    {
        foreach ($lines as $line) {
            if (!empty($line->metadataGet(Meta::RecalculatePrice))
                && Completor::isCorrectVatRate($line->metadataGet(Meta::VatRateSource))
                && $line->metadataExists(Meta::UnitPriceInc)
            ) {
                if ($line->metadataGet(Meta::RecalculatePrice) === Fld::UnitPrice) {
                    $line->metadataSet(Meta::RecalculateOldPrice, $line->unitPrice);
                    $line->unitPrice = $line->metadataGet(Meta::UnitPriceInc) / (100 + $line->vatRate) * 100;
                } else {
                    // $line->metadataGet(Meta::RecalculateUnitPrice) === Meta::UnitPriceInc
                    $line->metadataSet(Meta::RecalculateOldPrice, $line->metadataGet(Meta::UnitPriceInc));
                    $line->metadataSet(Meta::UnitPriceInc, (1 + $line->vatRate / 100) * $line->unitPrice);
                }
                $line->metadataSet(Meta::RecalculatedPrice, true);
            }
        }
    }

    /**
     * Completes each (non-strategy) invoice line with missing (meta) info.
     *
     * All non strategy invoice lines have 'unitPrice' and 'vatrate' filled in
     * and should by now have correct(ed) VAT rates. In some shops these non
     * strategy invoice lines may have a 'meta-line-discount-vatamount' or
     * 'meta-line-discount-amountinc' field, that can be used with the
     * SplitKnownDiscountLine strategy.
     *
     * Complete (if missing):
     * - 'unitPriceInc'
     * - 'vatamount'
     * - Meta::LineDiscountAmountInc (if 'meta-line-discount-vatamount' is
     *   available).
     *
     * For strategy invoice lines that may be split with the SplitNonMatching
     * line strategy, we need to know the line totals.
     *
     * Complete (if missing):
     * - Meta::LineAmount
     * - Meta::LineAmountInc
     *
     * @param Line[] $lines
     *   The invoice lines to complete with metadata.
     */
    protected function completeLineMetaData(array $lines): void
    {
        foreach ($lines as $line) {
            $fieldsCalculated = [];
            if (Completor::isCorrectVatRate($line->metadataGet(Meta::VatRateSource))) {
                if (!$line->metadataExists(Meta::UnitPriceInc)) {
                    $line->metadataSet(
                        Meta::UnitPriceInc,
                        $this->completor->isNoVat($line->vatRate)
                            ? $line->unitPrice
                            : $line->unitPrice * (100.0 + $line->vatRate) / 100.0
                    );
                    $fieldsCalculated[] = Meta::UnitPriceInc;
                }
                if (!$line->metadataExists(Meta::VatAmount)) {
                    $line->metadataSet(
                        Meta::VatAmount,
                        $this->completor->isNoVat($line->vatRate)
                            ? 0.0
                            : $line->vatRate / 100.0 * $line->unitPrice
                    );
                    $fieldsCalculated[] = Meta::VatAmount . ' (from ' . Fld::VatRate . ')';
                }
                if ($line->metadataExists(Meta::LineDiscountAmount) && !$line->metadataExists(Meta::LineDiscountAmountInc)) {
                    $line->metadataSet(
                        Meta::LineDiscountAmountInc,
                        $this->completor->isNoVat($line->vatRate)
                            ? $line->metadataGet(Meta::LineDiscountAmount)
                            : $line->metadataGet(Meta::LineDiscountAmount) * (100.0 + $line->vatRate) / 100.0
                    );
                    $fieldsCalculated[] = Meta::LineDiscountAmountInc;
                } elseif ($line->metadataExists(Meta::LineDiscountVatAmount) && !$line->metadataExists(Meta::LineDiscountAmountInc)) {
                    $line->metadataSet(
                        Meta::LineDiscountAmountInc,
                        $line->metadataGet(Meta::LineDiscountVatAmount)
                        / $line->vatRate * (100 + $line->vatRate)
                    );
                    $fieldsCalculated[] = Meta::LineDiscountAmountInc;
                }
            } elseif ($line->metadataGet(Meta::VatRateSource) == VatRateSource::Strategy && !empty(
                $line->metadataGet(
                    Meta::StrategySplit
                )
                )) {
                if (isset($line->unitPrice) && $line->metadataExists(Meta::UnitPriceInc)) {
                    if (!$line->metadataExists(Meta::LineAmount)) {
                        $line->metadataSet(Meta::LineAmount, $line->unitPrice * $line->quantity);
                    }
                    if (!$line->metadataExists(Meta::LineAmountInc)) {
                        $line->metadataSet(Meta::LineAmountInc, $line->metadataGet(Meta::UnitPriceInc) * $line->quantity);
                    }
                }
            }
            if (count($fieldsCalculated) > 0) {
                $line->metadataSet(Meta::FieldsCalculated, $fieldsCalculated);
            }
        }
    }
}
