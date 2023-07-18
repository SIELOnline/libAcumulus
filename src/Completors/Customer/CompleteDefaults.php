<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors\Customer;

use Siel\Acumulus\Completors\BaseCompletorTask;
use Siel\Acumulus\Data\AcumulusObject;

/**
 * CompleteDefaults fills in default values for some empty fields.
 */
class CompleteDefaults extends BaseCompletorTask
{
    /**
     * Adds some values if a field is empty.
     *
     * A default is added to the following fields:
     * - countryCode: NL if empty.
     *
     * @param \Siel\Acumulus\Data\Customer $acumulusObject
     */
    public function complete(AcumulusObject $acumulusObject, ...$args): void
    {
        if ($acumulusObject->getInvoiceAddress() !== null && empty($acumulusObject->getInvoiceAddress()->countryCode)) {
            $acumulusObject->getInvoiceAddress()->countryCode = 'NL';
        }
        if ($acumulusObject->getShippingAddress() !== null && empty($acumulusObject->getShippingAddress()->countryCode)) {
            $acumulusObject->getShippingAddress()->countryCode = 'NL';
        }
    }
}
