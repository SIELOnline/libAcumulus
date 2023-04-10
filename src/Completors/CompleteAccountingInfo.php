<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors;

use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Meta;

use function assert;
use function count;
use function is_array;
use function is_int;

/**
 * CompleteAccountingInfo completes the
 * {@see \Siel\Acumulus\Data\Invoice::$costCenter} and
 * {@see \Siel\Acumulus\Data\Invoice::$accountNumber} properties of an
 * {@see \Siel\Acumulus\Data\Invoice}.
 */
class CompleteAccountingInfo extends BaseCompletor
{
    protected function check(Invoice $invoice, ...$args): void
    {
        assert(count($args) >= 4);
        assert($args[0] === null  || is_int($args[0]));
        assert(is_array($args[1]));
        assert($args[2] === null || is_int($args[2]));
        assert(is_array($args[3]));
    }

    /**
     * Completes the
     * {@see \Siel\Acumulus\Data\Invoice::$costCenter} and
     * {@see \Siel\Acumulus\Data\Invoice::$accountNumber} properties of an
     * {@see \Siel\Acumulus\Data\Invoice}.
     *
     * Additional parameters:
     * - 0: int: default cost center
     * - 1: mapping array: payment method => cost center
     * - 2: int: default account number
     * - 3: mapping array: payment method => account number
     */
    protected function do(Invoice $invoice, ...$args): void
    {
        $this->check($invoice, ...$args);
        $paymentMethod = $invoice->metadataGet(Meta::PaymentMethod);
        [$costCenter, $costCenterPerPaymentMethod, $accountNumber, $accountNumberPerPaymentMethod] = $args;
        if (!empty($paymentMethod)) {
            if (!empty($costCenterPerPaymentMethod[$paymentMethod])) {
                $costCenter = $costCenterPerPaymentMethod[$paymentMethod];
            }
            if (!empty($accountNumberPerPaymentMethod[$paymentMethod])) {
                $accountNumber = $accountNumberPerPaymentMethod[$paymentMethod];
            }
        }
        $invoice->costCenter = $costCenter;
        $invoice->accountNumber = $accountNumber;
    }
}
