<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors\Line;

use Siel\Acumulus\Completors\BaseCompletorTask;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Meta;

use function sprintf;

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
 * If a webshop does not store the vat rates used in the order, we must calculate them
 * using a (product) price and the vat on it. But as webshops often store these numbers
 * rounded to cents, the vat rate calculation becomes imprecise. Therefore, we compute the
 * range in which it will lie and will let another Completor do a comparison with the
 * possible vat rates that an order can have.
 * - If $denominator = 0 (free product), the vat rate will be set to null and the other
 *   Completor will try to get this line listed under the correct vat rate.
 * - If $numerator = 0, the vat rate will be set to 0 and be treated as if it is an exact
 *   vat rate, not a vat range.
 * This completor task computes that range and adds it as a min and max vat rate tag.
 *
 * Note that this is:
 * - Only necessary if no vat rate has been collected.
 * - Only possible if at least 2 out of the 3 values "unit price", "unit price inc" and
 *   "vat amount" (and their precision) are known.
 * - If 2 out of 3 are available, the 3rd will be set (as well as Meta::FieldsCalculated).
 * - If only 1 amount is available a warning will be added to the line.
 *   @todo: do strategy lines pass here? then a warning is not needed for those.
 */
class CompleteVatRange extends BaseCompletorTask
{
    protected bool $exSet;
    protected bool $incSet;
    protected bool $vatSet;

    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     */
    public function complete(AcumulusObject $acumulusObject, ...$args): void
    {
        if (!isset($acumulusObject->vatRate)) {
            $this->setWhatIsAvailable($acumulusObject);
            if ($this->fillMissingData($acumulusObject)) {
                $this->addVatRangeTags($acumulusObject);
            }
        }
    }

    protected function addVatRangeTags(Line $line): void
    {
        if ($this->vatSet && $this->exSet) {
            $numerator = $line->metadataGet(Meta::VatAmount);
            $numeratorPrecision = $line->metadataGet(Meta::PrecisionVatAmount);
            $denominator = $line->unitPrice;
            $denominatorPrecision = $line->metadataGet(Meta::PrecisionUnitPrice);
        } elseif ($this->vatSet && $this->incSet) {
            $numerator = $line->metadataGet(Meta::VatAmount);
            $numeratorPrecision = $line->metadataGet(Meta::PrecisionVatAmount);
            $denominator = $line->metadataGet(Meta::UnitPriceInc) - $line->metadataGet(Meta::VatAmount);
            $denominatorPrecision = $line->metadataGet(Meta::PrecisionUnitPriceInc) + $line->metadataGet(Meta::PrecisionVatAmount);
        } else /*if ($this->exSet && $this->incSet)*/ {
            $numerator = $line->metadataGet(Meta::UnitPriceInc) - $line->unitPrice;
            $numeratorPrecision = $line->metadataGet(Meta::PrecisionUnitPriceInc) + $line->metadataGet(Meta::PrecisionUnitPrice);
            $denominator = $line->unitPrice;
            $denominatorPrecision = $line->metadataGet(Meta::PrecisionUnitPrice);
        }

        if (Number::isZero($denominator, 0.0001)) {
            // zero amounts (and I hope zero VAT): we cannot determine the range.
            $line->vatRate = null;
            $line->metadataSet(Meta::VatRateSource, VatRateSource::Completor);
        } elseif (Number::isZero($numerator, 0.0001)) {
            // zero VAT with non-zero amount: rate = 0
            $line->vatRate = 0;
            $line->metadataSet(Meta::VatRateSource, VatRateSource::No_Vat_Detected);
        } else {
            $range = Number::getDivisionRange($numerator, $denominator, $numeratorPrecision, $denominatorPrecision);
            $line->metadataSet(Meta::VatRateMin, 100.0 * $range['min']);
            $line->metadataSet(Meta::VatRateMax, 100.0 * $range['max']);
            $line->metadataSet(Meta::VatRateSource, VatRateSource::Calculated);
        }
    }

    /**
     * Sets which of the 3 fields amount ex, amount inc, and vat amount are available,
     * including their precision.
     */
    private function setWhatIsAvailable(Line $line): void
    {
        $this->exSet = isset($line->unitPrice) && $line->metadataExists(Meta::PrecisionUnitPrice);
        $this->incSet = $line->metadataExists(Meta::UnitPriceInc) && $line->metadataExists(Meta::PrecisionUnitPriceInc);
        $this->vatSet = $line->metadataExists(Meta::VatAmount) && $line->metadataExists(Meta::PrecisionVatAmount);
    }

    /**
     * Sets the 3rd amount if exactly 2 are available. Adds a warning when only 1 is
     * available.
     *
     * @return bool
     *   whether all 3 amounts are now set (at least 2 of the 3 amounts were available to
     *   start with).
     */
    protected function fillMissingData(Line $line): bool
    {
        $countSet = (int) $this->exSet + (int) $this->incSet + (int) $this->vatSet;
        if ($countSet <= 1) {
            $line->addWarning(
                Message::create(
                    sprintf($this->t('message_warning_only_1_filled'), $this->t('amount_ex'), $this->t('amount_inc'), $this->t('amount_vat')),
                    Severity::Warning,
                    801
                )
            );
            return false;
        } elseif ($countSet === 2) {
            if ($this->vatSet && $this->exSet) {
                $line->metadataSet(Meta::UnitPriceInc, $line->unitPrice + $line->metadataGet(Meta::VatAmount));
                $line->metadataAdd(Meta::FieldsCalculated, Meta::UnitPriceInc);
            } elseif ($this->vatSet && $this->incSet) {
                $line->unitPrice = $line->metadataGet(Meta::UnitPriceInc) - $line->metadataGet(Meta::VatAmount);
                $line->metadataAdd(Meta::FieldsCalculated, Fld::UnitPrice);
            } elseif ($this->exSet && $this->incSet) {
                $line->metadataSet(Meta::VatAmount, $line->metadataGet(Meta::UnitPriceInc) - $line->unitPrice);
                $line->metadataAdd(Meta::FieldsCalculated, Meta::VatAmount);
            }
        }
        return $countSet >= 2;
    }
}
