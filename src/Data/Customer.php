<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use Siel\Acumulus\Api;

/**
 * Represents an Acumulus API customer object.
 *
 * The definition of the fields is based on the
 * {@link https://www.siel.nl/acumulus/API/Invoicing/Add_Invoice/ Data Add API call},
 * NOT the
 * {@link https://www.siel.nl/acumulus/API/Contacts/Manage_Contact/ Manage Contact call}.
 * However, there are some notable changes with the API structure:
 * - A Customer is part of the {@see Invoice} instead of the other way in the
 *   API.
 * - We have 2 separate {@see Address} objects, an invoice and billing address.
 *   In the API all address fields are part of the customer itself, the fields
 *   of the 2nd address being prefixed with 'alt'. In decoupling this in the
 *   collector phase, we allow users to relate the 1st and 2 nd address to the
 *   invoice or shipping address as they like.
 * - Field names are copied from the API, though capitals are introduced for
 *   readability and to prevent PhpStorm typo inspections.
 *
 * Metadata can be added via the {@see MetadataCollection} methods.
 *
 *  @property ?string $contactId
 *  @property ?int $type
 *  @property ?int $vatTypeId
 *  @property ?string $contactYourId
 *  @property ?int $contactStatus
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
 */
class Customer extends AcumulusObject
{
    protected static array $propertyDefinitions = [
        ['name' => 'contactId', 'type' =>'string'],
        ['name' => 'type', 'type' =>'int', 'allowedValues' => [Api::CustomerType_Debtor, Api::CustomerType_Creditor, Api::CustomerType_Both]],
        ['name' => 'vatTypeId', 'type' =>'int', 'allowedValues' => [Api::VatTypeId_Private, Api::VatTypeId_Business]],
        ['name' => 'contactYourId', 'type' =>'string'],
        ['name' => 'contactStatus', 'type' =>'bool', 'allowedValues' => [Api::ContactStatus_Disabled, Api::ContactStatus_Active]],

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

    protected ?Address $billingAddress = null;
    protected ?Address $shippingAddress = null;

    public function getBillingAddress(): ?Address
    {
        return $this->billingAddress;
    }

    /**
     * @return $this
     */
    public function setBillingAddress(?Address $billingAddress): self
    {
        $this->billingAddress = $billingAddress;
        return $this;
    }

    public function getShippingAddress(): ?Address
    {
        return $this->shippingAddress;
    }

    /**
     * @return $this
     */
    public function setShippingAddress(?Address $shippingAddress): self
    {
        $this->shippingAddress = $shippingAddress;
        return $this;
    }
}
