<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Api;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Address;
use Siel\Acumulus\Data\PropertySet;
use Siel\Acumulus\Fld;

/**
 * Collects address data from the shop.
 *
 * properties that are mapped:
 * - string $companyName1
 * - string $companyName2
 * - string $fullName
 * - string $salutation
 * - string $address1
 * - string $address2
 * - string $postalCode
 * - string $city
 * - string $countryCode (optional, if it can be mapped)
 *
 * Properties that are computed using logic:
 * - string $countryCode (optional, if it cannot be mapped)
 * - string $countryAutoNameLang (if the user wants to use the shop spelling)
 * - string $country (if the user wants to use the shop spelling)
 *
 * Properties that are based on configuration and thus are not set here:
 * - int $countryAutoName
 *
 * Properties that are not set:
 * - string $countryAutoNameLang
 * - string $country
 */
class AddressCollector extends Collector
{
    protected function getAcumulusObjectType(): string
    {
        return 'Address';
    }

    /**
     * @param \Siel\Acumulus\Data\Address $acumulusObject
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        if ($acumulusObject->countryCode === null) {
            /** @var \Siel\Acumulus\Invoice\Source $invoiceSource */
            $invoiceSource = $this->propertySources['invoiceSource'];
            // Set 'nl' as default country code, but overwrite with the real country
            // code, if not empty.
            $acumulusObject->setCountryCode('nl');
            $acumulusObject->setCountryCode($invoiceSource->getCountryCode(), PropertySet::NotEmpty);
        }
    }
}
