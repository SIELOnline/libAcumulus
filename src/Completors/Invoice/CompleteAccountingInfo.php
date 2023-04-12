<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors\Invoice;

use Siel\Acumulus\Completors\BaseCompletorTask;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Meta;

use function assert;

/**
 * CompleteAccountingInfo completes the
 * {@see \Siel\Acumulus\Data\Invoice::$costCenter} and
 * {@see \Siel\Acumulus\Data\Invoice::$accountNumber} properties of an
 * {@see \Siel\Acumulus\Data\Invoice}.
 */
class CompleteAccountingInfo extends BaseCompletorTask
{
    /**
     * Completes the
     * {@see \Siel\Acumulus\Data\Invoice::$costCenter} and
     * {@see \Siel\Acumulus\Data\Invoice::$accountNumber} properties of an
     * {@see \Siel\Acumulus\Data\Invoice}.
     *
     * @param \Siel\Acumulus\Data\Invoice $acumulusObject
     * @param int|int[] ...$args
     *   Additional parameters: none
     */
    public function complete(AcumulusObject $acumulusObject, ...$args): void
    {
        assert($acumulusObject instanceof Invoice);
        // Will be 0 (not null) when config is missing.
        $costCenter = $this->configGet('defaultCostCenter');
        $costCenterPerPaymentMethod = $this->configGet('paymentMethodCostCenter');
        // Will be 0 (not null) when config is missing.
        $accountNumber = $this->configGet('defaultAccountNumber');
        $accountNumberPerPaymentMethod = $this->configGet('paymentMethodAccountNumber');
        $paymentMethod = $acumulusObject->metadataGet(Meta::PaymentMethod);
        if (!empty($paymentMethod)) {
            if (!empty($costCenterPerPaymentMethod[$paymentMethod])) {
                $costCenter = $costCenterPerPaymentMethod[$paymentMethod];
            }
            if (!empty($accountNumberPerPaymentMethod[$paymentMethod])) {
                $accountNumber = $accountNumberPerPaymentMethod[$paymentMethod];
            }
        }
        if (!empty($costCenter)) {
            $acumulusObject->costCenter = $costCenter;
        }
        if (!empty($accountNumber)) {
            $acumulusObject->accountNumber = $accountNumber;
        }
    }
}
