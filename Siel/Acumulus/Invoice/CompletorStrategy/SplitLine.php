<?php
namespace Siel\Acumulus\Invoice\CompletorStrategy;

use Siel\Acumulus\Invoice\CompletorStrategyBase;
use Siel\Acumulus\Invoice\Creator;

/**
 * Class SplitLine implements a vat completor strategy by recognizing that a
 * (discount) line can have any vat rate between the minimum and maximum vat
 * rate. This because the discount can be divided over multiple products that
 * have different vat rates.
 *
 * Preconditions:
 * - lines2Complete contains at least 1 line that may be split.
 * - Exactly 2 vat rates that appear in the invoice, otherwise we can't compute
 *   1 division.
 * - This strategy should be executed after the tryAllVatRatePermutations, so we
 *   may assume that we *have to* split to arrive at a solution.
 *
 * Strategy:
 * Other non completed lines, typically shipping and other fees, are all given
 * the same vat rate and subsequently the then remaining vat to divide is split
 * ove the slit lines.
 *
 * If there are multiple split lines, we cannot arrive at a correct division
 * for all theses line separately, so we combine them into 1 discount line and
 * split that line in 2.
 *
 * As this strategy has a lot of freedom it will probably succeed with the first
 * try. Therefore we should start with the "most correct" vat rate for the fee
 * lines, being the key component (hoofdbestanddeel). But as no known shop
 * implements this, we start with the maximum rate (this is used by most shops)
 * followed by the minimum rate but only if it is the key component.
 *
 * Possible improvements:
 * - Instead of restricting us to vat rates that already appear on the invoice,
 *   we could also look at the possible vat rates that are part of the vat type
 *   for the invoice (given that the vat type has 2 vat rates).
 *   Example: all low vat products, but high vat shipping (because that is set
 *   as such, even if it would not be necessary in this case) and a discount
 *   over the whole order amount, thus products + shipping, and both the
 *   shipping and discount line are strategy lines, where the discount line is
 *   the split line.
 * - Instead of restricting us to 1 vat type, we could check for all still
 *   possible vat types that have only 2 vat rates.
 * - Instead of restricting us to appearing or possible vat rates we could try
 *   to assume that 1 line is a prepaid voucher and therefore vat free, and
 *   split just the other line(s).
 *
 * Current known usages:
 * - ???
 * @todo: deprecated or are there situations where other fee lines also do not
 *  have a vatrate.
 * In the current versions, all lines that may be split will have a
 * meta-strategy-split entry, so SplitNonMatchingLine or SplitKnownDiscountLine
 * should do this trick.
 */
class SplitLine extends CompletorStrategyBase
{
    /**
     * @var int
     *   This strategy should be tried last before the fail strategy as there
     *   are chances of returning a wrong true result.
     */
    static public $tryOrder = 100;

    /** @var array[] */
    protected $splitLines;

    /** @var float */
    protected $splitLinesAmount;

    /** @var array[] */
    protected $otherLines;

    /** @var float */
    protected $otherLinesAmount;

    /** @var float */
    protected $nonStrategyAmount;

    /** @var array */
    protected $minVatRate;

    /** @var array */
    protected $maxVatRate;

    /** @var array */
    protected $keyComponent;

    /**
     * {@inheritdoc}
     */
    protected function init()
    {
        $this->splitLines = array();
        $this->otherLines = array();
        $this->otherLinesAmount = 0.0;
        foreach ($this->lines2Complete as $line2Complete) {
            if (!empty($line2Complete['meta-strategy-split'])) {
                $this->splitLines[] = $line2Complete;
            } else {
                $this->otherLines[] = $line2Complete;
                $this->otherLinesAmount += $line2Complete['unitprice'] * $line2Complete['quantity'];
            }
        }

        $this->nonStrategyAmount = 0.0;
        foreach ($this->invoice['customer']['invoice']['line'] as $line2Complete) {
            if ($line2Complete['meta-vatrate-source'] !== Creator::VatRateSource_Strategy) {
                $this->nonStrategyAmount += $line2Complete['unitprice'] * $line2Complete['quantity'];
            }
        }
        $this->splitLinesAmount = $this->invoiceAmount - $this->nonStrategyAmount - $this->otherLinesAmount;

        $this->minVatRate = $this->getVatBreakDownMinRate();
        $this->maxVatRate = $this->getVatBreakDownMaxRate();
        $this->keyComponent = $this->getVatBreakDownMaxAmount();
    }

    /**
     * {@inheritdoc}
     */
    protected function checkPreconditions()
    {
        return count($this->vatBreakdown) === 2 && count($this->splitLines) >= 1;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        if ($this->tryVatRate($this->maxVatRate['vatrate'])) {
            return true;
        }
        if ($this->maxVatRate !== $this->keyComponent) {
            if ($this->tryVatRate($this->keyComponent['vatrate'])) {
                return true;
            }
        }

        // Try a rate of 0% for all other lines.
        //return $this->tryVatRate(0, $minRate, $maxRate);
        return false;
    }

    /**
     *
     *
     * @param float $vatRateForOtherLines
     *
     * @return bool
     *
     */
    protected function tryVatRate($vatRateForOtherLines)
    {
        $this->description = "SplitLine($vatRateForOtherLines, {$this->minVatRate['vatrate']}, {$this->maxVatRate['vatrate']})";
        $this->replacingLines = array();
        $otherVatAmount = 0.0;
        foreach ($this->otherLines as $otherLine2Complete) {
            $otherVatAmount += $this->completeLine($otherLine2Complete, $vatRateForOtherLines);
        }
        return $this->divideAmountOver2VatRates($this->vat2Divide - $otherVatAmount,
            (float) $this->minVatRate['vatrate'] / 100.0,
            (float) $this->maxVatRate['vatrate'] / 100.0);
    }

    /**
     * Tries to split $this->splitLinesAmount over 2 lines with $lowVatRate and
     * $highVatRate such that the vat amount for those 2 lines equals the
     * Given an amount and a vat over that amount, split that amount over 2 given
     * vat rates such that the total vat amount remains equal.
     *
     * Example €15,- with €2.40 vat and vat rates of 21% and 6% results in €10,-
     * at 21% vat and €5,- at 6% vat.
     *
     * The math:
     * 1) highAmount + LowAmount = Amount
     * 2) highRate * highAmount + lowRate * lowAmount = VatAmount
     *
     * This results in:
     * 1) highAmount = (vatAmount - Amount * lowRate) / (highRate - lowRate)
     * 2) lowAmount = Amount - highAmount
     *
     * This may be considered successful if the sign of all 3 amounts is the same
     * and both low and high amount are not 0 (this is a split strategy, not
     * splitting but using 1 vat rate is tried by another strategy).
     *
     * @param float $splitVatAmount
     * @param float $lowVatRate
     *   number between 0 and $highRate.
     * @param float $highVatRate
     *   number between $lowRate and 1.
     *
     * @return bool
     *   Success.
     */
    protected function divideAmountOver2VatRates($splitVatAmount, $lowVatRate, $highVatRate)
    {
        // Divide the amount over the 2 vat rates, such that the sum of the divided
        // amounts and the sum of the vat amounts equals the total amount and vat.
        list($lowAmount, $highAmount) = $this->splitAmountOver2VatRates($this->splitLinesAmount, $splitVatAmount, $lowVatRate, $highVatRate);

        // Dividing was possible if both amounts have the same sign.
        if (($highAmount < -0.005 && $lowAmount < -0.005 && $this->splitLinesAmount < -0.005)
            || ($highAmount > 0.005 && $lowAmount > 0.005 && $this->splitLinesAmount > 0.005)
        ) {
            // We split all lines by the same percentage.
            $highPercentage = $highAmount / $this->splitLinesAmount;
            $lowPercentage = $lowAmount / $this->splitLinesAmount;
            foreach ($this->splitLines as $line) {
                $splitLine = $line;
                $splitLine['product'] .= ' ' . $highVatRate . '% ' . $this->t('vat');
                if (isset($splitLine['unitprice'])) {
                    $splitLine['unitprice'] = $highPercentage * $splitLine['unitprice'];
                }
                if (isset($splitLine['unitpriceinc'])) {
                    $splitLine['unitpriceinc'] = $highPercentage * $splitLine['unitpriceinc'];
                }
                $this->completeLine($splitLine, $highVatRate);

                $splitLine = $line;
                $splitLine['product'] .= ' ' . $lowVatRate . '% ' . $this->t('vat');
                if (isset($splitLine['unitprice'])) {
                    $splitLine['unitprice'] = $lowPercentage * $splitLine['unitprice'];
                }
                if (isset($splitLine['unitpriceinc'])) {
                    $splitLine['unitpriceinc'] = $lowPercentage * $splitLine['unitpriceinc'];
                }
                $this->completeLine($splitLine, $lowVatRate);
            }
            return true;
        }

        return false;
    }
}
