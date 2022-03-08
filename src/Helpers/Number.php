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
 * Some web shops do not store the used vat percentage with orders but store
 * the product price ex vat and the vat amount. As these amounts are often
 * stored with limited precision, typically 1 cent, the exact vat rate cannot
 * be calculated. Instead, a range within which the vat rate falls can be
 * calculated. This library uses this range to determine the actual vat rate
 * later on after knowing which vat rates may apply (Dutch vat rates, foreign
 * vat rates).
 */
class Number
{
    /**
     * Returns the range within which the result of a division should fall,
     * given the precision range for the 2 numbers to divide.
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
     *   Array of floats with keys 'min', 'max' and 'calculated'.
     */
    public static function getDivisionRange(float $numerator, float $denominator, float $numeratorPrecision, float $denominatorPrecision): array
    {
        // The actual value can be half the precision lower or higher.
        // To err on the save side, we take 60% of it (instead of 50%).
        $numeratorHalfRange = 0.6 * $numeratorPrecision;
        $denominatorHalfRange = 0.6 * $denominatorPrecision;

        // The min values should be closer to 0 then the value.
        // The max values should be further from 0 then the value.
        if ($numerator < 0.0) {
            $numeratorHalfRange = -$numeratorHalfRange;
        }
        $minNumerator = $numerator - $numeratorHalfRange;
        $maxNumerator = $numerator + $numeratorHalfRange;

        if ($denominator < 0.0) {
            $denominatorHalfRange = -$denominatorHalfRange;
        }
        $minDenominator = $denominator - $denominatorHalfRange;
        $maxDenominator = $denominator + $denominatorHalfRange;

        // We get the min value of the division by dividing the minimum
        // numerator by the maximum denominator and vice versa.
        $min = $minNumerator / $maxDenominator;
        $max = $maxNumerator / $minDenominator;
        $calculated = $numerator / $denominator;

        return ['min' => $min, 'calculated' => $calculated, 'max' => $max];
    }

    /**
     * Helper method to do a float comparison
     *
     * Comparison is based on a maximum difference, as exact bit by bit equality
     * for "equal" floats is often not the case.
     */
    public static function floatsAreEqual(float $f1, float $f2, float $maxDiff = 0.0051): bool
    {
        return abs($f2 - $f1) < $maxDiff;
    }

    /**
     * Indicates if a float is to be considered zero.
     *
     * This is a wrapper around floatsAreEqual() for the often used case where
     * an amount is checked for being 0.0.
     */
    public static function isZero(float $f1, float $maxDiff = 0.0011): bool
    {
        return static::floatsAreEqual($f1, 0.0, $maxDiff);
    }

    /**
     * Returns whether a number can be considered a number being rounded to the
     * given precision.
     *
     * @param float|string $f
     *   The number may be passed as a string, and in many cases probably will
     *   indeed be passed as a string as it comes from the database.
     * @param int $precision
     *   The number of decimals to which it should have been rounded, if it is a
     *   rounded number. Should be a non-negative integer.
     *
     * @return bool
     *   true if $f is a number that appears to be rounded to the given
     *   precision, false otherwise.
     */
    public static function isRounded($f, int $precision): bool
    {
        if (is_string($f)) {
            // If $f is a string, we look at the number of digits after the
            // decimal point (ignoring trailing 0's).
            $pos = strrpos($f, '.');
            return $pos === false || strlen(rtrim($f, '0')) - $pos - strlen('.') <= $precision;
        } else {
            // f $f is a float, we use the round() function and look at the
            // difference (testing floats for equality within a given margin).
            return static::floatsAreEqual($f, round($f, $precision), (10 ** -($precision + 2)) / 2.0);
        }
    }

    /**
     * Returns whether a list of numbers can be considered numbers being rounded
     * to the given precision.
     *
     * @param float[] $fs
     *   The numbers may be passed as a string, and in many cases probably will
     *   indeed be passed as a string as it comes from the database.
     * @param int $precision
     *   The number of decimals to which they should have been rounded, if they
     *   are rounded numbers. Should be a non-negative integer.
     *
     * @return bool
     *   true if all numbers in $fs appear to be rounded to the given
     *   precision, false otherwise.
     */
    public static function areRounded(array $fs, int $precision): bool
    {
        foreach ($fs as $f) {
            if (!static::isRounded($f, $precision)) {
                return false;
            }
        }
        return true;
    }
}
