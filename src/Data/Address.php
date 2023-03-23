<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use Siel\Acumulus\Api;

/**
 * Represents a set of address fields of an Acumulus API Customer object.
 *
 * A customer has 2 separate {@see Address} objects: an invoice and billing
 * address. In the API, all address fields are part of the customer itself, the
 * fields of the 2nd address being prefixed with 'alt'. In decoupling this in
 * the collector phase, we allow users to relate the 1st and 2 nd address to the
 * invoice or shipping address as they like.
 *
 * Field names are copied from the API, though capitals are introduced for
 * readability and to prevent PhpStorm typo inspections.
 *
 * Metadata can be added via the {@see MetadataCollection} methods.
 *
 * @property ?string $companyName1
 * @property ?string $companyName2
 * @property ?string $fullName
 * @property ?string $salutation
 * @property ?string $address1
 * @property ?string $address2
 * @property ?string $postalCode
 * @property ?string $city
 * @property ?string $country
 * @property ?string $countryCode
 * @property ?int $countryAutoName
 * @property ?string $countryAutoNameLang
 *
 * @method bool setCompanyName1(?string $value, int $mode = PropertySet::Always)
 * @method bool setCompanyName2(?string $value, int $mode = PropertySet::Always)
 * @method bool setFullName(?string $value, int $mode = PropertySet::Always)
 * @method bool setSalutation(?string $value, int $mode = PropertySet::Always)
 * @method bool setAddress1(?string $value, int $mode = PropertySet::Always)
 * @method bool setAddress2(?string $value, int $mode = PropertySet::Always)
 * @method bool setPostalCode(?string $value, int $mode = PropertySet::Always)
 * @method bool setCity(?string $value, int $mode = PropertySet::Always)
 * @method bool setCountry(?string $value, int $mode = PropertySet::Always)
 * @method bool setCountryCode(?string $value, int $mode = PropertySet::Always)
 * @method bool setCountryAutoName(?int $value, int $mode = PropertySet::Always)
 * @method bool setCountryAutoNameLang(?string $value, int $mode = PropertySet::Always)
 *
 * @noinspection PhpLackOfCohesionInspection  Data objects have little cohesion.
 */
class Address extends AcumulusObject
{
    protected function getPropertyDefinitions(): array
    {
        return [
            ['name' => 'companyName1', 'type' => 'string'],
            ['name' => 'companyName2', 'type' => 'string'],
            ['name' => 'fullName', 'type' => 'string'],
            ['name' => 'salutation', 'type' => 'string'],
            ['name' => 'address1', 'type' => 'string'],
            ['name' => 'address2', 'type' => 'string'],
            ['name' => 'postalCode', 'type' => 'string'],
            ['name' => 'city', 'type' => 'string'],
            ['name' => 'country', 'type' => 'string'],
            ['name' => 'countryCode', 'type' => 'string'],
            [
                'name' => 'countryAutoName',
                'type' => 'int',
                'allowedValues' => [Api::AutoName_No, Api::AutoName_OnlyForeign, Api::AutoName_Yes],
            ],
            ['name' => 'countryAutoNameLang', 'type' => 'string'],
        ];
    }
}
