<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Collectors;

use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Invoice\Totals;
use Siel\Acumulus\Meta;

/**
 * PaymentFeeLineCollector contains PrestaShop specific {@see LineType::PaymentFee}
 * collecting logic.
 */
class PaymentFeeLineCollector extends LineCollector
{
    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   A payment fee line with the mapped fields filled in.
     *
     * @throws \Exception
     *
     * @noinspection PhpMissingParentCallCommonInspection Empty base method.
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
        $paymentFeeLineInfo = $propertySources->get('paymentFeeLineInfo');
        $line = $acumulusObject;

        $totals = new Totals(
            $paymentFeeLineInfo->metadataGet(Meta::UnitPriceInc),
            $paymentFeeLineInfo->metadataGet(Meta::VatAmount),
            $paymentFeeLineInfo->unitPrice,
            $paymentFeeLineInfo->vatRate
        );

        $line->product = $this->t('payment_costs');
        $line->quantity = 1;
        $line->unitPrice = $totals->amountEx;
        if ($totals->isAmountExCalculated()) {
            // PayPal with a fee: inc and vat rate given, ex and vat amount calculated.
            $line->vatRate = $paymentFeeLineInfo->vatRate;
            $line->metadataSet(Meta::VatRateSource, VatRateSource::Exact);
            $line->metadataSet(Meta::UnitPriceInc, $totals->amountInc);
            $line->metadataSet(Meta::VatAmount, $totals->amountVat);
            $line->metadataAdd(Meta::FieldsCalculated, [Fld::UnitPrice, Meta::VatAmount]);
        } else {
            // Mollie line: ex and inc given: vat amount and rate (to be) calculated.
            $line->metadataSet(Meta::PrecisionUnitPrice, 0.01);
            $line->metadataSet(Meta::UnitPriceInc, $totals->amountInc);
            $line->metadataSet(Meta::PrecisionUnitPriceInc, 0.01);
            $line->metadataSet(Meta::VatRateSource, VatRateSource::Calculated);
        }
    }
}

