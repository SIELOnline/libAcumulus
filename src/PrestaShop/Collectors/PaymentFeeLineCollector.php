<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Collectors;

use MolOrderPaymentFee;
use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Fld;
use Siel\Acumulus\PrestaShop\Invoice\Source;
use Siel\Acumulus\Meta;

/**
 * PaymentFeeLineCollector contains PrestaShop specific {@see LineType::PaymentFee}
 * collecting logic.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
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
        $paymentFeeInfo = $propertySources->get('paymentFeeLineInfo');
        if ($paymentFeeInfo instanceof Source) {
            $this->collectPaymentFeeLineForPayPalWithAFee($acumulusObject, $propertySources->get('invoice'), $paymentFeeInfo);
        }
        if ($paymentFeeInfo instanceof MolOrderPaymentFee) {
            $this->collectPaymentFeeLineForMollie($acumulusObject, $propertySources->get('invoice'), $propertySources->get('source'), $paymentFeeInfo);
        }
    }

    /**
     * Collects an optional payment fee line if the PayPal with a Fee module is used
     *
     * @param \Siel\Acumulus\Data\Line $line
     *   A payment fee line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectPaymentFeeLineForPayPalWithAFee(Line $line, Invoice $invoice, Source $source): void
    {
        /** @noinspection PhpUndefinedFieldInspection These fields are set by the PayPal with a fee module. */
        $paymentInc = $source->getSign() * $source->getShopObject()->payment_fee;
        /** @noinspection PhpUndefinedFieldInspection */
        $paymentVatRate = (float) $source->getShopObject()->payment_fee_rate;
        $paymentEx = $paymentInc / (100.0 + $paymentVatRate) * 100;
        $paymentVat = $paymentInc - $paymentEx;
        $line->product = $this->t('payment_costs');
        $line->quantity = 1;
        $line->unitPrice = $paymentEx;
        $line->metadataSet(Meta::UnitPriceInc, $paymentInc);
        $line->vatRate = $paymentVatRate;
        $line->metadataSet(Meta::VatRateSource, VatRateSource::Exact);
        $line->metadataSet(Meta::VatAmount, $paymentVat);
        $line->metadataAdd(Meta::FieldsCalculated, Fld::UnitPrice);
        $line->metadataAdd(Meta::FieldsCalculated, Meta::VatAmount);

        /** @var \Siel\Acumulus\Invoice\Totals $totals */
        $totals = $invoice->metadataGet(Meta::Totals);
        $totals->add($paymentInc, null, $paymentEx);
    }


    /**
     * Collects an optional payment fee line if the Mollie module is used.
     *
     * @param \Siel\Acumulus\Data\Line $line
     *   A payment fee line with the mapped fields filled in.
     */
    protected function collectPaymentFeeLineForMollie(Line $line, Invoice $invoice, Source $source, MolOrderPaymentFee $molOrderPaymentFee): void
    {
        $sign = $source->getSign();
        $paymentEx = $sign * $molOrderPaymentFee->fee_tax_excl;
        $paymentInc = $sign * $molOrderPaymentFee->fee_tax_incl;
        $line->product = $this->t('payment_costs');
        $line->quantity = 1;
        $line->unitPrice = $paymentEx;
        $line->metadataSet(Meta::PrecisionUnitPrice, 0.01);
        $line->metadataSet(Meta::UnitPriceInc, $paymentInc);
        $line->metadataSet(Meta::PrecisionUnitPriceInc, 0.01);
        $line->metadataSet(Meta::VatRateSource, VatRateSource::Calculated);

        /** @var \Siel\Acumulus\Invoice\Totals $totals */
        $totals = $invoice->metadataGet(Meta::Totals);
        $totals->add($paymentInc, null, $paymentEx);
    }
}

