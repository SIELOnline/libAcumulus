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
 * - We have 2 separate {@see Address} objects, an invoice and shipping address.
 *   In the API all address fields are part of the customer itself, the fields
 *   of the 2nd address being prefixed with 'alt'. In decoupling this in the
 *   collector phase, we allow users to relate the 1st and 2nd address to the
 *   invoice or shipping address as they like.
 * - Field names are copied from the API, though capitals are introduced for
 *   readability and to prevent PhpStorm typo inspections.
 *
 * Metadata can be added via the {@see MetadataCollection} methods.
 *
 *  @property ?string $contactId  // @todo: add to mappings.
 *  @property ?int $type // @todo: rename setting.
 *  @property ?int $vatTypeId
 *  @property ?string $contactYourId
 *  @property ?bool $contactStatus
 *
 *  @property ?string $website // @todo: add to mappings.
 *  @property ?string $vatNumber
 *  @property ?string $telephone
 *  @property ?string $telephone2 // @todo: add to mappings.
 *  @property ?string $fax
 *  @property ?string $email
 *  @property ?bool $overwriteIfExists
 *  @property ?string $bankAccountNumber // @todo: add to mappings.
 *  @property ?string $mark
 *  @property ?bool $disableDuplicates // @todo: add to mappings.
 *
 *  @method bool setContactId(?string $value, int $mode = PropertySet::Always)
 *  @method bool setType(?int $value, int $mode = PropertySet::Always)
 *  @method bool setVatTypeId(?int $value, int $mode = PropertySet::Always)
 *  @method bool setContactYourId(?string $value, int $mode = PropertySet::Always)
 *  @method bool setContactStatus(?bool $value, int $mode = PropertySet::Always)
 *
 *  @method bool setWebsite(?string $value, int $mode = PropertySet::Always)
 *  @method bool setVatNumber(?string $value, int $mode = PropertySet::Always)
 *  @method bool setTelephone(?string $value, int $mode = PropertySet::Always)
 *  @method bool setTelephone2(?string $value, int $mode = PropertySet::Always)
 *  @method bool setFax(?string $value, int $mode = PropertySet::Always)
 *  @method bool setEmail(?string $value, int $mode = PropertySet::Always)
 *  @method bool setOverwriteIfExists(?bool $value, int $mode = PropertySet::Always)
 *  @method bool setBankAccountNumber(?string $value, int $mode = PropertySet::Always)
 *  @method bool setMark(?string $value, int $mode = PropertySet::Always)
 *  @method bool setDisableDuplicates(?bool $value, int $mode = PropertySet::Always)
 */
class Customer extends AcumulusObject
{
    protected function getPropertyDefinitions(): array
    {
        return [
            ['name' => 'contactId', 'type' => 'string'],
            [
                'name' => 'type',
                'type' => 'int',
                'allowedValues' => [Api::CustomerType_Debtor, Api::CustomerType_Creditor, Api::CustomerType_Both]
            ],
            ['name' => 'vatTypeId', 'type' => 'int', 'allowedValues' => [Api::VatTypeId_Private, Api::VatTypeId_Business]],
            ['name' => 'contactYourId', 'type' => 'string'],
            ['name' => 'contactStatus', 'type' => 'bool', 'allowedValues' => [Api::ContactStatus_Disabled, Api::ContactStatus_Active]],

            ['name' => 'website', 'type' => 'string'],
            ['name' => 'vatNumber', 'type' => 'string'],
            ['name' => 'telephone', 'type' => 'string'],
            ['name' => 'telephone2', 'type' => 'string'],
            ['name' => 'fax', 'type' => 'string'],
            ['name' => 'email', 'type' => 'string'],
            ['name' => 'overwriteIfExists', 'type' => 'bool', 'allowedValues' => [Api::OverwriteIfExists_No, Api::OverwriteIfExists_Yes]],
            ['name' => 'bankAccountNumber', 'type' => 'string'],
            ['name' => 'mark', 'type' => 'string'],
            ['name' => 'disableDuplicates', 'type' => 'bool', 'allowedValues' => [Api::DisableDuplicates_No, Api::DisableDuplicates_Yes]],
        ];
    }

    protected ?Address $invoiceAddress = null;
    protected ?Address $shippingAddress = null;

    public function getInvoiceAddress(): ?Address
    {
        return $this->invoiceAddress;
    }

    public function setInvoiceAddress(?Address $invoiceAddress): void
    {
        $this->invoiceAddress = $invoiceAddress;
    }

    public function getShippingAddress(): ?Address
    {
        return $this->shippingAddress;
    }

    public function setShippingAddress(?Address $shippingAddress): void
    {
        $this->shippingAddress = $shippingAddress;
    }
}
