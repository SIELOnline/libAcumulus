<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\PropertySet;

/**
 * Collects address data from the shop.
 *
 * properties that are mapped:
 * - string $companyName1
 * - string $companyName2
 * - string $fullName
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
    /**
     * @param \Siel\Acumulus\Data\Address $acumulusObject
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        // Not needed for: MA, WC, HS, VM (does its own lookup); Needed for: -
        if ($acumulusObject->countryCode === null) {
            /** @var \Siel\Acumulus\Invoice\Source $invoiceSource */
            $invoiceSource = $this->propertySources['source'];
            // Set 'nl' as default country code, but overwrite with the real country
            // code, if not empty.
            $acumulusObject->setCountryCode('nl');
            $acumulusObject->setCountryCode($invoiceSource->getCountryCode(), PropertySet::NotEmpty);
        }
    }
}
