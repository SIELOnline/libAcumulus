<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use RuntimeException;
use Siel\Acumulus\Api;
use Siel\Acumulus\Fld;

use Siel\Acumulus\Meta;

use function in_array;
use function is_string;

/**
 * Represents a set of address fields of an Acumulus API Customer object.
 *
 * A customer has 2 separate {@see Address} objects: an invoice and billing
 * address. In the API, all address fields are part of the customer itself, the
 * fields of the 2nd address being prefixed with 'alt'. In decoupling this in
 * the collector phase, we allow users to relate the 1st and 2nd address to the
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

    public function set(string $name, mixed $value, int $mode = PropertySet::Always): bool
    {
        if (($this->getPropertyName($name) === Fld::CountryCode) && is_string($value)) {
            $value = strtoupper($value);
        }
        return parent::set($name, $value, $mode);
    }

    public function setType(?string $addressType): void
    {
        if (!in_array($addressType, [null, AddressType::Invoice, AddressType::Shipping, AddressType::Store], true)) {
            throw new RuntimeException('Invalid address type');
        }
        $this->metadataSet(Meta::SubType, $addressType);
    }

    public function getType(): ?string
    {
        return $this->metadataGet(Meta::SubType);
    }

    /**
     * Returns whether 2 addresses are equal.
     *
     * To determine equalness we do a strict comparison of all the address parts, thus not
     * on the properties countryAutoName, countryAutoNameLang, and addressType. For the
     * country part we only look at the countryCode property, thus not the country
     * property.
     *
     * Using strict comparison and not e.g. comparing sanitised parts (lower cased, excess
     * spaces removed) is OK here as we mainly want to catch alt addresses that came from
     * the same shop data because the shop only stores 1 address or the client chose to
     * use the same address for shipping and invoicing.
     */
    public function equals(?Address $address): bool
    {
        return $address !== null
            && $this->companyName1 === $address->companyName1
            && $this->companyName2 === $address->companyName2
            && $this->fullName === $address->fullName
            && $this->address1 === $address->address1
            && $this->address2 === $address->address2
            && $this->postalCode === $address->postalCode
            && $this->city === $address->city
            && $this->countryCode === $address->countryCode;
    }

    /**
     * Returns whether the address is being considered empty.
     *
     * A minimal fiscal or invoice address should have at least:
     * - A name part: company name 1 or full name should be set
     * - A location part: postal code or city should be set
     * - The country should be set, but as this defaults to NL this will always be the case.
     *
     * A minimal shipping address constitutes:
     * - A location part: postal code or city should be set
     * - Premise/House Number: so address1 should be set
     * - The country should be set, but as this defaults to NL this will always be the case.
     *
     * Address types and where it can be used for:
     * - null: invoice, fiscal and shipping.
     * - {@see AddressType::Invoice}: invoice, fiscal
     * - {@see AddressType::Shipping}: shipping
     * - {@see AddressType::Store}: invoice, fiscal, shipping
     *
     * This method can be used to prevent adding an alt address when only some country
     * related fields are set (due to completion).
     */
    public function isEmpty(): bool
    {
        if (in_array($this->getType(), [null, AddressType::Invoice, AddressType::Store], true)) {
            if ((empty($this->fullName) && empty($this->companyName1)) || (empty($this->postalCode) && empty($this->city))) {
                return true;
            }
        }
        if (in_array($this->getType(), [null, AddressType::Shipping, AddressType::Store], true)) {
            if (empty($this->address1) || (empty($this->postalCode) && empty($this->city))) {
                return true;
            }
        }
        return false;
    }
}
