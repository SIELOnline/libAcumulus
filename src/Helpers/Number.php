<?php
namespace Siel\Acumulus\Helpers;

/**
 * Number contains features to work with float numbers, especially amounts.
 *
 * Comparing floats for equality is not done via a simple ===, but - because of
 * small possible errors in precision - by getting the difference and allow for
 * a small difference. when working with amounts, typically a difference of
 * half a cent is used.
 *
 * Some webshops do not store the used vat percentage with orders but store
 * the product price ex vat and the vat amount. As these amounts are often
 * stored with limited precision, typically 1 cent, the exact vat rate cannot
 * be calculated. Instead a range within which the vat rate falls can be
 * calculated. This library uses this range to determine the actual vat rate
 * later on after knowing which vat rates may apply (Dutch vat rates, foreign
 * vat rates).
 */
class Number
{
    /**
     * Returns the range within which the result of the division should fall given
     * the precision range for the 2 numbers to divide.
     *
     * @param float $numerator
     * @param float $denominator
     * @param float $numeratorPrecision
     *   The precision used when rounding the number. This means that the
     *   original numerator will not differ more than half of this in any
     *   direction.
     * @param float $denominatorPrecision
     *   The precision used when rounding the number. This means that the
     *   original denominator will not differ more than half of this in any
     *   direction.
     *
     * @return array
     *   Array of floats with keys min, max and calculated.
     */
    static public function getDivisionRange($numerator, $denominator, $numeratorPrecision, $denominatorPrecision)
    {
        // The actual value can be half the precision lower or higher.
        // To err on the save side, we take 60% of it (instead of 50%).
        $numeratorHalfRange = 0.6 * (float) $numeratorPrecision;
        $denominatorHalfRange = 0.6 * (float) $denominatorPrecision;

        // The min values should be closer to 0 then the value.
        // The max values should be further from 0 then the value.
        if ($numerator < 0.0) {
            $numeratorHalfRange = -$numeratorHalfRange;
        }
        $minNumerator = (float) $numerator - $numeratorHalfRange;
        $maxNumerator = (float) $numerator + $numeratorHalfRange;

        if ($denominator < 0.0) {
            $denominatorHalfRange = -$denominatorHalfRange;
        }
        $minDenominator = (float) $denominator - $denominatorHalfRange;
        $maxDenominator = (float) $denominator + $denominatorHalfRange;

        // We get the min value of the division by dividing the minimum numerator by
        // the maximum denominator and vice versa.
        $min = $minNumerator / $maxDenominator;
        $max = $maxNumerator / $minDenominator;
        $calculated = $numerator / $denominator;

        return array('min' => $min, 'calculated' => $calculated, 'max' => $max);
    }

    /**
     * Helper method to do a float comparison.
     *
     * @param float $f1
     * @param float $f2
     * @param float $maxDiff
     *
     * @return bool
     *   True if the the floats are "equal", i.e. do not differ more than the
     *   specified maximum difference.
     */
    static public function floatsAreEqual($f1, $f2, $maxDiff = 0.005)
    {
        return abs((float) $f2 - (float) $f1) < $maxDiff;
    }

    /**
     * indicates if a float is to be considered zero.
     *
     * This is a wrapper around floatsAreEqual() for the often used case where
     * an amount is checked for being 0.0.
     *
     * @param float  $f1
     * @param float $maxDiff
     *
     * @return bool
     *   True if the float is (almost) zero, false otherwise.
     */
    static public function isZero($f1, $maxDiff = 0.001)
    {
        return static::floatsAreEqual($f1, 0.0, $maxDiff);
    }
}
