<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors\Customer;

use Siel\Acumulus\Api;
use Siel\Acumulus\Completors\BaseCompletorTask;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Customer;
use Siel\Acumulus\Data\DataType;

use function assert;

/**
 * CompleteAddresses completes the {@see \Siel\Acumulus\Data\Address} parts of a
 * {@see \Siel\Acumulus\Data\Customer}.
 *
 * A Customer object has fields for 2 addresses, the main address and the alt address.
 * The mapping phase has mapped the invoice and/or shipping address from the shop's
 * customer data to these addresses but these may be empty or equal.
 *
 * - Empty addresses are removed. If the main address is empty, the alt address is set as
 *   main address.
 * - If the addresses are equal the alt address is removed.
 */
class CompleteAddresses extends BaseCompletorTask
{
    /**
     * Removes customer data (if set so).
     *
     * If you have lots of one-time customers, you may not want to add the
     * customer data to your administration but instead link all those orders to
     * one fictitious client.
     *
     * @param \Siel\Acumulus\Data\Customer $acumulusObject
     * @param int ...$args
     *   Additional parameters: none
     */
    public function complete(AcumulusObject $acumulusObject, ...$args): void
    {
        assert($acumulusObject instanceof Customer);
        if ($acumulusObject->getInvoiceAddress()?->isEmpty()) {
            $acumulusObject->setInvoiceAddress(null);
        }
        if ($acumulusObject->getShippingAddress()?->isEmpty()) {
            $acumulusObject->setShippingAddress(null);
        }
        if ($acumulusObject->getInvoiceAddress()?->equals($acumulusObject->getShippingAddress())) {
            $acumulusObject->setShippingAddress(null);
        }
    }
}
