<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Customer;

/**
 * Creates a {@see Customer} object.
 *
 * The following properties are mapped:
 * - string $contactId
 * - string $contactYourId
 * - string $website
 * - string $vatNumber
 * - string $telephone
 * - string $telephone2
 * - string $fax
 * - string $email
 * - string $bankAccountNumber
 * - string $mark
 *
 * And the following fields properties are computed using logic:
 * - none
 *
 * These remaining properties are set in the completor phase as they are not
 * based on shop data, but on configuration:
 * - int $type
 * - int $vatTypeId
 * - int $contactStatus
 * - int $overwriteIfExists
 * - int $disableDuplicates
 */
class CustomerCollector extends Collector
{
    protected function getAcumulusObjectType(): string
    {
        return 'Customer';
    }

    /**
     * @param \Siel\Acumulus\Data\Customer $acumulusObject
     *
     * @noinspection PhpParamsInspection  Correctly typing Collectors would need
     *   templating.
     */
    protected function collectChildObjects(AcumulusObject $acumulusObject, array $fieldMappings): self
    {
        // Collect child objects: the addresses.
        $collector = $this->getContainer()->getCollector('InvoiceAddress');
        $acumulusObject->setInvoiceAddress($collector->collect($this->propertySources, $fieldMappings));
        $collector = $this->getContainer()->getCollector('ShippingAddress');
        $acumulusObject->setShippingAddress($collector->collect($this->propertySources, $fieldMappings));
        return $this;
    }
}
