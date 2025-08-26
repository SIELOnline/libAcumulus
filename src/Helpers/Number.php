<?php

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

use InvalidArgumentException;

use Stringable;

use function is_string;
use function sprintf;
use function strlen;

/**
 * Number contains features to work with (float) numbers, especially amounts.
 *
 * Comparing floats for equality is not done via a simple ===, but - because of small
 * possible errors in precision - by getting the difference and allows for a small
 * difference. when working with amounts, typically a difference of half a cent or (far)
 * less is used.
 *
 * Some web shops do not store the used vat percentage with orders but store the product
 * price ex-vat and the vat amount. As these amounts are often stored with limited
 * precision, typically 1 cent, the exact vat rate cannot be calculated. Instead, a range
 * within which the vat rate falls can be calculated. This library uses this range to
 * determine the actual vat rate later on after knowing which vat rates may apply (Dutch
 * vat rates, foreign vat rates).
 */
class Number
{
    /**
     * Tries to cast a numeric string value to an int or float.
     *
     * Note that we don't want to cast phone numbers that often start with a 0 or +. So we
     * check that the first digit is not a zero, and we only allow a minus sign before the
     * number, not a (redundant) plus sign.
     */
    public static function castNumericValue(mixed $value): mixed
    {
        if (is_string($value) && is_numeric($value)) {
            // Is it a float or an int? Check integers for phone number.
            if (strpbrk($value, '.eE') !== false) {
                $value = (float) $value;
            } elseif (strpbrk($value, '0+') !== $value || strlen($value) <= 4) {
                $value = (int) $value;
            }
        }
        return $value;
    }

    /**
     * Returns the range within which the result of a division should fall,
     * given the precision range for the 2 numbers to divide.
     *
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
    public static function getDivisionRange(
        float $numerator,
        float $denominator,
        float $numeratorPrecision,
        float $denominatorPrecision
    ): array {
        // The actual value can be half the precision lower or higher.
        // To err on the save side, we take 56% of it (instead of 50%).
        $numeratorHalfRange = 0.56 * $numeratorPrecision;
        $denominatorHalfRange = 0.56 * $denominatorPrecision;

        // The min values should be closer to 0 than the value.
        // The max values should be further from 0 than the value.
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

        return compact('min', 'calculated', 'max');
    }

    /**
     * Helper method to do a float comparison
     *
     * Comparison is based on a maximum delta, as exact bit-by-bit equality
     * for "equal" floats is often not the case.
     *
     * @param $f1
     *   A value that can be converted to a float.
     * @param $f2
     *   A value that can be converted to a float.
     */
    public static function floatsAreEqual($f1, $f2, float $delta = 0.0051): bool
    {
        $f1 = static::turnIntoFloat($f1);
        $f2 = static::turnIntoFloat($f2);
        return abs($f2 - $f1) < $delta;
    }

    /**
     * Indicates if a float is to be considered zero.
     *
     * This is a wrapper around floatsAreEqual() for the case where an amount is
     * checked for being 0.0.
     */
    public static function isZero($f1, float $maxDiff = 0.0011): bool
    {
        return static::floatsAreEqual($f1, 0.0, $maxDiff);
    }

    /**
     * Converts a value into a float.
     *
     * Values passed to {@see floatsAreEqual()} are not necessarily floats, but
     * may be any type that somehow can be converted to floats. Known examples:
     * - Magento: getBaseDiscountAmount(), and many other getters, may return
     *   null for 0 amounts.
     * - WooCommerce: A 0 amount may be stored as an empty string in the
     *   database. Non 0 amounts may be stored as their string representation.
     * - {@see SimpleXMLElement}s can contain numeric values, the conversion is
     *   done via a string as all XML values are strings.
     *
     * @param mixed $f
     *  A value to be converted into a float
     *
     * @throws \InvalidArgumentException
     */
    protected static function turnIntoFloat(mixed $f): float
    {
        if ($f instanceof Stringable) {
            $f = (string) $f;
        }
        if ($f !== null && $f !== '' && !is_numeric($f)) {
            /** @noinspection JsonEncodingApiUsageInspection */
            throw new InvalidArgumentException(sprintf(
                '%s is not considered a numeric value',
                json_encode($f)
            ));
        }
        return (float) $f;
    }

    /**
     * Returns whether a number can be considered a number being rounded to the
     * given precision.
     *
     * @param float|string $f
     *   The number may be passed as a string, and in many cases probably will
     *   indeed be passed as a string as it comes from the database.
     * @param int $precision
     *   The number of decimals to which it should have been rounded if it is a
     *   rounded number. Should be a non-negative integer.
     *
     * @return bool
     *   true if $f is a number that appears to be rounded to the given
     *   precision, false otherwise.
     */
    public static function isRounded(mixed $f, int $precision): bool
    {
        if ($f instanceof Stringable) {
            $f = (string) $f;
        }
        if (is_string($f)) {
            // If $f is a string, we look at the number of digits after the
            // decimal point (ignoring trailing 0's).
            $pos = strrpos($f, '.');
            return $pos === false || strlen(rtrim($f, '0')) - $pos - strlen('.') <= $precision;
        } else {
            // If $f is a float, we use the round() function and look at the
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
     *   The number of decimals to which they should have been rounded if they
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
