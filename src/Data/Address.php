<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use Siel\Acumulus\Api;
use Siel\Acumulus\Fld;

use function is_string;

/**
 * Represents a set of address fields of an Acumulus API Customer object.
 *
 * A customer has 2 separate {@see Address} objects: an invoice and billing
 * address. In the API, all address fields are part of the customer itself, the
 * fields of the 2nd address being prefixed with 'alt'. In decoupling this in
 * the collector phase, we allow users to relate the 1st and 2 nd address to the
 * invoice or shipping address as they like.
 *
 * @property ?string $companyName1
 * @property ?string $companyName2
 * @property ?string $fullName
 * @property ?string $address1
 * @property ?string $address2
 * @property ?string $postalCode
 * @property ?string $city
 * @property ?string $countryCode
 * @property ?int $countryAutoName
 * @property ?string $countryAutoNameLang
 * @property ?string $country
 *
 * @method bool setCompanyName1(?string $value, int $mode = PropertySet::Always)
 * @method bool setCompanyName2(?string $value, int $mode = PropertySet::Always)
 * @method bool setFullName(?string $value, int $mode = PropertySet::Always)
 * @method bool setAddress1(?string $value, int $mode = PropertySet::Always)
 * @method bool setAddress2(?string $value, int $mode = PropertySet::Always)
 * @method bool setPostalCode(?string $value, int $mode = PropertySet::Always)
 * @method bool setCity(?string $value, int $mode = PropertySet::Always)
 * @method bool setCountryCode(?string $value, int $mode = PropertySet::Always)
 * @method bool setCountryAutoName(?int $value, int $mode = PropertySet::Always)
 * @method bool setCountryAutoNameLang(?string $value, int $mode = PropertySet::Always)
 * @method bool setCountry(?string $value, int $mode = PropertySet::Always)
 *
 * @noinspection PhpLackOfCohesionInspection  Data objects have little cohesion.
 */
class Address extends AcumulusObject
{
    protected function getPropertyDefinitions(): array
    {
        return [
            ['name' => Fld::CompanyName1, 'type' => 'string'],
            ['name' => Fld::CompanyName2, 'type' => 'string'],
            ['name' => Fld::FullName, 'type' => 'string'],
            ['name' => Fld::Address1, 'type' => 'string'],
            ['name' => Fld::Address2, 'type' => 'string'],
            ['name' => Fld::PostalCode, 'type' => 'string'],
            ['name' => Fld::City, 'type' => 'string'],
            ['name' => Fld::CountryCode, 'type' => 'string'],
            [
                'name' => Fld::CountryAutoName,
                'type' => 'int',
                'allowedValues' => [Api::CountryAutoName_No, Api::CountryAutoName_OnlyForeign, Api::CountryAutoName_Yes],
            ],
            ['name' => Fld::CountryAutoNameLang, 'type' => 'string'],
            ['name' => Fld::Country, 'type' => 'string'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function set(string $name, mixed $value, int $mode = PropertySet::Always): bool
    {
        if (($this->getPropertyName($name) === Fld::CountryCode) && is_string($value)) {
            $value = strtoupper($value);
        }
        return parent::set($name, $value, $mode);
    }
}
