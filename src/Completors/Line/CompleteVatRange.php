<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors\Line;

use Siel\Acumulus\Completors\BaseCompletorTask;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Meta;

/**
 * CompleteVatRange adds VAT range tags to a line.
 *
 * VAT range:
 * - Many shops only store a unit price (inc and/or ex) and a vat amount.
 * - However, these numbers are often stored rounded to a certain precision.
 * - So, just dividing these numbers is bound to give an approximation at best.
 * - However, knowing the precision of both numbers, we can calculate a range in which the
 *   actual vat rate will be.
 *
 * If a web shop does not store the vat rates used in the order, we must
 *  calculate them using a (product) price and the vat on it. But as web
 *  shops often store these numbers rounded to cents, the vat rate
 *  calculation becomes imprecise. Therefore, we compute the range in which
 *  it will lie and will let the Completor do a comparison with the actual
 *  vat rates that an order can have.
 *  - If $denominator = 0 (free product), the vat rate will be set to null
 *    and the Completor will try to get this line listed under the correct
 *    vat rate.
 *  - If $numerator = 0, the vat rate will be set to 0 and be treated as if it
 *    is an exact vat rate, not a vat range.
 * This completor computes that range and adds it as a min and max vat rate tag.
 *
 * Note that this is:
 * - Only necessary if no vat rate has been collected.
 * - Only possible if at least 2 out of the 3 values (and their precision) "unit price",
 *   "unit price inc" and "vat amount" are known.
 */
class CompleteVatRange extends BaseCompletorTask
{
    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     */
    public function complete(AcumulusObject $acumulusObject, ...$args): void
    {
        $this->completeVatRange($acumulusObject);
    }

    /**
     * Check the conditions and adds a vat range if necessary and possible.
     */
    protected function completeVatRange(Line $line): void
    {
        if (isset($line->vatRate)) {
            return;
        }

        if ((!isset($line->unitPrice) || !$line->metadataExists(Meta::PrecisionUnitPrice))
            && $line->metadataExists(Meta::UnitPriceInc) && $line->metadataExists(Meta::PrecisionUnitPriceInc)
            && $line->metadataExists(Meta::VatAmount) && $line->metadataExists(Meta::PrecisionVatAmount)
        ) {
            $line->unitPrice = $line->metadataGet(Meta::UnitPriceInc) - $line->metadataGet(Meta::VatAmount);
            $line->metadataSet(
                Meta::PrecisionUnitPrice,
                $line->metadataGet(Meta::PrecisionUnitPriceInc) + $line->metadataGet(Meta::PrecisionVatAmount)
            );
            $line->metadataAdd(Meta::FieldsCalculated, Fld::UnitPrice);
        }
        if (!isset($line->unitPrice) || !$line->metadataExists(Meta::PrecisionUnitPrice)) {
            return;
        }

        if ((!$line->metadataExists(Meta::VatAmount) || !$line->metadataExists(Meta::PrecisionVatAmount))
            && $line->metadataExists(Meta::UnitPriceInc) && $line->metadataExists(Meta::PrecisionUnitPriceInc)
        ) {
            $line->metadataSet(Meta::VatAmount, $line->metadataGet(Meta::UnitPriceInc) - $line->unitPrice);
            $line->metadataSet(
                Meta::PrecisionVatAmount,
                $line->metadataGet(Meta::PrecisionUnitPriceInc) + $line->metadataGet(Meta::PrecisionUnitPrice)
            );
            $line->metadataAdd(Meta::FieldsCalculated, Meta::VatAmount);
        }
        if (!$line->metadataExists(Meta::VatAmount) || !$line->metadataExists(Meta::PrecisionVatAmount)) {
            return;
        }

        static::addVatRangeTags($line);
    }

    /**
     * Adds information about the range in which the vat rate will lie.
     *
     *  The following fields/metadata will be set:
     *  - vatRate
     *  - Meta::VatRateMin
     *  - Meta::VatRateMax
     *  - Meta::VatRateSource
     */
    public static function addVatRangeTags(Line $line): void
    {
        $numerator = $line->metadataGet(Meta::VatAmount);
        $numeratorPrecision = $line->metadataGet(Meta::PrecisionVatAmount);
        $denominator = $line->unitPrice;
        $denominatorPrecision = $line->metadataGet(Meta::PrecisionUnitPrice);

        if (Number::isZero($denominator, 0.0001)) {
            // zero amounts (and I hope zero VAT): we cannot determine the range.
            $line->vatRate = null;
            $line->metadataSet(Meta::VatRateSource, VatRateSource::Completor);
        } elseif (Number::isZero($numerator, 0.0001)) {
            // zero VAT with non-zero amount: rate = 0
            $line->vatRate = 0;
            $line->metadataSet(Meta::VatRateSource, VatRateSource::Exact0);
        } else {
            $range = Number::getDivisionRange($numerator, $denominator, $numeratorPrecision, $denominatorPrecision);
            // @todo: Is this needed for some code further on in the process: comment out and see what happens.
//            $line->vatRate = 100.0 * $range['calculated'];
            $line->metadataSet(Meta::VatRateMin, 100.0 * $range['min']);
            $line->metadataSet(Meta::VatRateMax, 100.0 * $range['max']);
            $line->metadataSet(Meta::VatRateSource, VatRateSource::Calculated);
        }
    }
}
