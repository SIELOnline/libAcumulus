<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors\Customer;

use Siel\Acumulus\Api;
use Siel\Acumulus\Completors\BaseCompletorTask;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\Customer;
use Siel\Acumulus\Data\DataType;

use function assert;

/**
 * CompleteAddresses completes the {@see \Siel\Acumulus\Data\Address} parts of a
 * {@see \Siel\Acumulus\Data\Customer}.
 *
 * A Customer object has fields for 2 addresses, the main address and the alt address.
 * Webshops often have an invoice and a shipping address. The mapping phase has created
 * these 2 addresses (invoice and shipping) as well as mete data that determines which
 * address is to be used as the main address and which one as the alt address.
 *
 * This completor ensures that:
 * - If the address that will be used as main address is empty, the other address is
 *   copied to the to be main address.
 * - If the addresses are equal the address that will be used as alt address is unset.
 * - If the address that will be used as alt address is empty, it is unset.
 */
class CompleteAddresses extends BaseCompletorTask
{
    /**
     * Completes address data.
     *
     * @param \Siel\Acumulus\Data\Customer $acumulusObject
     * @param int ...$args
     *   Additional parameters: none
     */
    public function complete(AcumulusObject $acumulusObject, ...$args): void
    {
        assert($acumulusObject instanceof Customer);
        if ($acumulusObject->getMainAddressType() === AddressType::Invoice) {
            if ($acumulusObject->getInvoiceAddress()?->isEmpty() && !$acumulusObject->getShippingAddress()?->isEmpty()) {
                $acumulusObject->setInvoiceAddress($acumulusObject->getShippingAddress());
            }
            if ($acumulusObject->getShippingAddress()?->isEmpty()) {
                $acumulusObject->setShippingAddress(null);
            }
            if ($acumulusObject->getShippingAddress()?->equals($acumulusObject->getInvoiceAddress())) {
                $acumulusObject->setShippingAddress(null);
            }
        } else {
            if ($acumulusObject->getShippingAddress()?->isEmpty() && !$acumulusObject->getInvoiceAddress()?->isEmpty()) {
                $acumulusObject->setShippingAddress($acumulusObject->getInvoiceAddress());
            }
            if ($acumulusObject->getInvoiceAddress()?->isEmpty()) {
                $acumulusObject->setInvoiceAddress(null);
            }
            if ($acumulusObject->getInvoiceAddress()?->equals($acumulusObject->getShippingAddress())) {
                $acumulusObject->setInvoiceAddress(null);
            }
        }
    }
}
