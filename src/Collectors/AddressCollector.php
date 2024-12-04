<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

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
 *
 * @method \Siel\Acumulus\Data\Address collect(PropertySources $propertySources, ?\ArrayObject $fieldSpecifications = null)
 */
class AddressCollector extends SubTypedCollector
{
}
