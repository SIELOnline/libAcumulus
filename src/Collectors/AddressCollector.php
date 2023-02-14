<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Api;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Address;
use Siel\Acumulus\Data\PropertySet;
use Siel\Acumulus\Fld;

/**
 * Creates an {@see Address} object.
 *
 * The following properties are mapped:
 * - string $companyName1
 * - string $companyName2
 * - string $fullName
 * - string $salutation
 * - string $address1
 * - string $address2
 * - string $postalCode
 * - string $city
 *
 * And the following fields properties are computed using logic:
 * - string $countryCode
 *
 * These remaining properties are set in the completor phase as they are not
 * based on shop data, but on configuration:
 * - string $country
 * - int $countryAutoName
 * - string $countryAutoNameLang
 */
abstract class AddressCollector extends Collector
{
    protected function getAcumulusObjectType(): string
    {
        return 'Address';
    }

    /**
     * @param \Siel\Acumulus\Data\Address $acumulusObject
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): self
    {
        /** @var \Siel\Acumulus\Invoice\Source $invoiceSource */
        $invoiceSource = $this->propertySources['invoiceSource'];
        // Set 'nl' as default country code, but overwrite with the real country
        // code, if not empty.
        $acumulusObject->setCountryCode('nl');
        $acumulusObject->setCountryCode($invoiceSource->getCountryCode(), PropertySet::NotEmpty);
        return $this;
    }
}
