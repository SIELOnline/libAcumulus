<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors\Invoice;

use Siel\Acumulus\Completors\BaseCompletorTask;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Meta;

use function assert;

/**
 * CompleteConvertTotalsToEuro converts the amounts in the 'totals' metadata field to
 * euros if they are expressed in a foreign currency.
 */
class CompleteConvertTotalsToEuro extends BaseCompletorTask
{
    /**
     * Completes the 'totals' metadata field of the {@see \Siel\Acumulus\Data\Invoice}.
     *
     * @param \Siel\Acumulus\Data\Invoice $acumulusObject
     * @param int ...$args
     *   Additional parameters: none.
     */
    public function complete(AcumulusObject $acumulusObject, ...$args): void
    {
        assert($acumulusObject instanceof Invoice);
        if ($acumulusObject->metadataExists(Meta::Currency)) {
            /** @var \Siel\Acumulus\Invoice\Currency $currency */
            $currency = $acumulusObject->metadataGet(Meta::Currency);
            if ($currency->shouldConvert()) {
                /** @var \Siel\Acumulus\Invoice\Totals $totals */
                $totals = $acumulusObject->metadataGet(Meta::Totals);
                if ($totals !== null) {
                    $totals->amountEx = $currency->convertAmount($totals->amountEx);
                    $totals->amountVat = $currency->convertAmount($totals->amountVat);
                    $totals->amountInc = $currency->convertAmount($totals->amountInc);
                }
            }
        }
    }
}
