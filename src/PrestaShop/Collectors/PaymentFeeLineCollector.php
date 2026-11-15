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
        $line = $acumulusObject;
        if (!empty($line->vatRate)) {
            // PayPal with a fee: inc and vat rate are given, ex to be calculated.
            $line->metadataSet(Meta::VatRateSource, VatRateSource::Exact);
        } else {
            // Mollie line: ex and inc are given: vat rate to be calculated.
            $line->metadataSet(Meta::PrecisionUnitPrice, 0.01);
            $line->metadataSet(Meta::PrecisionUnitPriceInc, 0.01);
        }
    }
}

