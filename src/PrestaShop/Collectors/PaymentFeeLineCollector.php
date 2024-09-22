<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\PrestaShop\Invoice\Source;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

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
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        $this->collectPaymentFeeLine($acumulusObject);
    }

    /**
     * Collects the payment fee line for the invoice.
     *
     * @param \Siel\Acumulus\Data\Line $line
     *   A payment fee line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectPaymentFeeLine(Line $line): void
    {
        /** @var Invoice $invoice */
        $invoice = $this->getPropertySource('source');
        /** @var Source $source */
        $source = $this->getPropertySource('source');
        $sign = $source->getSign();

        /** @noinspection PhpUndefinedFieldInspection These fields are set by the PayPal with a fee module. */
        $paymentInc = $sign * $source->getShopObject()->payment_fee;
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
        $line->metadataAdd(Meta::FieldsCalculated, Tag::UnitPrice);
        $line->metadataAdd(Meta::FieldsCalculated, Meta::VatAmount);

        /** @var \Siel\Acumulus\Invoice\Totals $totals */
        $totals = $invoice->metadataGet(Meta::Totals);
        $totals->add($paymentInc, null, $paymentEx);
    }
}

