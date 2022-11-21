<?php

namespace Siel\Acumulus\Data;

use Siel\Acumulus\Api;/**
 * Represents an Acumulus API customer object.
 *
 *  @property ?string $contactId
 *  @property ?int $type
 *  @property ?int $vatTypeId
 *  @property ?string $contactYourId
 *  @property ?int $contactStatus
 *
 *  @property ?string $companyName1
 *  @property ?string $companyName2
 *  @property ?string $fullName
 *  @property ?string $salutation
 *  @property ?string $address1
 *  @property ?string $address2
 *  @property ?string $postalCode
 *  @property ?string $city
 *  @property ?string $country
 *  @property ?string $countryCode
 *  @property ?int $countryAutoName
 *  @property ?string $countryAutoNameLang
 *
 *  @property ?string $altCompanyName1
 *  @property ?string $altCompanyName2
 *  @property ?string $altFullName
 *  @property ?string $altAddress1
 *  @property ?string $altAddress2
 *  @property ?string $altPostalCode
 *  @property ?string $altCity
 *  @property ?string $altCountry
 *  @property ?int $altCountryCode
 *  @property ?int $altCountryAutoName
 *  @property ?string $altCountryAutoNameLang
 *
 *  @property ?string $website
 *  @property ?string $vatNumber
 *  @property ?string $telephone
 *  @property ?string $telephone2
 *  @property ?string $fax
 *  @property ?string $email
 *  @property ?int $overwriteIfExists
 *  @property ?string $bankAccountNumber
 *  @property ?string $mark
 *  @property ?int $disableDuplicates
 *
 *  @method bool setContactId(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setType(?int $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setVatTypeId(?int $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setContactYourId(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setContactStatus(?int $value, int $mode = AcumulusProperty::Set_Always)
 *
 *  @method bool setCompanyName1(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setCompanyName2(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setFullName(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setSalutation(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setAddress1(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setAddress2(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setPostalCode(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setCity(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setCountry(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setCountryCode(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setCountryAutoName(?int $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setCountryAutoNameLang(?string $value, int $mode = AcumulusProperty::Set_Always)
 *
 *  @method bool setAltCompanyName1(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setAltCompanyName2(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setAltFullName(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setAltAddress1(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setAltAddress2(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setAltPostalCode(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setAltCity(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setAltCountry(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setAltCountryCode(?int $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setAltCountryAutoName(?int $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setAltCountryAutoNameLang(?string $value, int $mode = AcumulusProperty::Set_Always)
 *
 *  @method bool setWebsite(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setVatNumber(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setTelephone(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setTelephone2(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setFax(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setEmail(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setOverwriteIfExists(?int $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setBankAccountNumber(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setMark(?string $value, int $mode = AcumulusProperty::Set_Always)
 *  @method bool setDisableDuplicates(?int $value, int $mode = AcumulusProperty::Set_Always)
 *
 * The definition of the fields is based on the
 * {@link https://www.siel.nl/acumulus/API/Invoicing/Add_Invoice/ Data Add API call},
 * NOT the
 * {@link https://www.siel.nl/acumulus/API/Contacts/Manage_Contact/ Manage Contact call}.
 * However, whereas in the API structures, the {@see Invoice} object is part of
 * this Customer object, in our object model, a Customer is part of the
 * {@see Invoice}.
 *
 * Field names are copied from the API, though capitals are introduced for
 * readability (and to prevent PhpStorm typo inspections).
 *
 * Metadata can be added via the {@see \Siel\Acumulus\Data\MetadataCollection}
 * interface.
 */
class Customer extends AcumulusObject
{
    protected static array $propertyDefinitions = [
        ['name' => 'contactId', 'type' =>'string'],
        ['name' => 'type', 'type' =>'int', 'allowedValues' => [Api::CustomerType_Debtor, Api::CustomerType_Creditor, Api::CustomerType_Both]],
        ['name' => 'vatTypeId', 'type' =>'int', 'allowedValues' => [Api::VatTypeId_Private, Api::VatTypeId_Business]],
        ['name' => 'contactYourId', 'type' =>'string'],
        ['name' => 'contactStatus', 'type' =>'bool', 'allowedValues' => [Api::ContactStatus_Disabled, Api::ContactStatus_Active]],

        ['name' => 'companyName1', 'type' =>'string'],
        ['name' => 'companyName2', 'type' =>'string'],
        ['name' => 'fullName', 'type' =>'string'],
        ['name' => 'salutation', 'type' =>'string'],
        ['name' => 'address1', 'type' =>'string'],
        ['name' => 'address2', 'type' =>'string'],
        ['name' => 'postalCode', 'type' =>'string'],
        ['name' => 'city', 'type' =>'string'],
        ['name' => 'country', 'type' =>'string'],
        ['name' => 'countryCode', 'type' =>'string'],
        ['name' => 'countryAutoName', 'type' =>'int', 'allowedValues' => [Api::AutoName_No, Api::AutoName_OnlyForeign, Api::AutoName_Yes]],
        ['name' => 'countryAutoNameLang', 'type' =>'string'],

        ['name' => 'altCompanyName1', 'type' =>'string'],
        ['name' => 'altCompanyName2', 'type' =>'string'],
        ['name' => 'altFullName', 'type' =>'string'],
        ['name' => 'altAddress1', 'type' =>'string'],
        ['name' => 'altAddress2', 'type' =>'string'],
        ['name' => 'altPostalCode', 'type' =>'string'],
        ['name' => 'altCity', 'type' =>'string'],
        ['name' => 'altCountry', 'type' =>'string'],
        ['name' => 'altCountryCode', 'type' =>'int'],
        ['name' => 'altCountryAutoName', 'type' =>'int', 'allowedValues' => [Api::AutoName_No, Api::AutoName_OnlyForeign, Api::AutoName_Yes]],
        ['name' => 'altCountryAutoNameLang', 'type' =>'string'],

        ['name' => 'website', 'type' =>'string'],
        ['name' => 'vatNumber', 'type' =>'string'],
        ['name' => 'telephone', 'type' =>'string'],
        ['name' => 'telephone2', 'type' =>'string'],
        ['name' => 'fax', 'type' =>'string'],
        ['name' => 'email', 'type' =>'string'],
        ['name' => 'overwriteIfExists', 'type' =>'bool', 'allowedValues' => [Api::OverwriteIfExists_No, Api::OverwriteIfExists_Yes]],
        ['name' => 'bankAccountNumber', 'type' =>'string'],
        ['name' => 'mark', 'type' =>'string'],
        ['name' => 'disableDuplicates', 'type' =>'bool', 'allowedValues' => [Api::DisableDuplicates_No, Api::DisableDuplicates_Yes]],
    ];
}
