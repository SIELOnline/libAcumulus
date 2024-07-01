<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use Error;
use Siel\Acumulus\Api;

use Siel\Acumulus\Fld;

use Siel\Acumulus\Meta;

use function assert;
use function in_array;

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
 * @property ?string $contactId
 * @property ?int $type
 * @property ?int $vatTypeId
 * @property ?string $contactYourId
 * @property ?bool $contactStatus
 * @property ?string $salutation
 * @property ?string $website
 * @property ?string $vatNumber
 * @property ?string $telephone
 * @property ?string $telephone2
 * @property ?string $fax
 * @property ?string $email
 * @property ?bool $overwriteIfExists
 * @property ?string $bankAccountNumber
 * @property ?string $mark
 * @property ?bool $disableDuplicates
 *
 * @method bool setContactId(?string $value, int $mode = PropertySet::Always)
 * @method bool setType(?int $value, int $mode = PropertySet::Always)
 * @method bool setVatTypeId(?int $value, int $mode = PropertySet::Always)
 * @method bool setContactYourId(?string $value, int $mode = PropertySet::Always)
 * @method bool setContactStatus(bool|int|null $value, int $mode = PropertySet::Always)
 * @method bool setSalutation(?string $value, int $mode = PropertySet::Always)
 * @method bool setWebsite(?string $value, int $mode = PropertySet::Always)
 * @method bool setVatNumber(?string $value, int $mode = PropertySet::Always)
 * @method bool setTelephone(?string $value, int $mode = PropertySet::Always)
 * @method bool setTelephone2(?string $value, int $mode = PropertySet::Always)
 * @method bool setFax(?string $value, int $mode = PropertySet::Always)
 * @method bool setEmail(?string $value, int $mode = PropertySet::Always)
 * @method bool setOverwriteIfExists(bool|int|null $value, int $mode = PropertySet::Always)
 * @method bool setBankAccountNumber(?string $value, int $mode = PropertySet::Always)
 * @method bool setMark(?string $value, int $mode = PropertySet::Always)
 * @method bool setDisableDuplicates(bool|int|null $value, int $mode = PropertySet::Always)
 *
 * @noinspection PhpLackOfCohesionInspection  Data objects have little cohesion.
 */
class Customer extends AcumulusObject
{
    use CustomerArrayAccessTrait;

    protected ?Address $invoiceAddress = null;
    protected ?Address $shippingAddress = null;
    // @legacy: needed to support fluent ArrayAccess.
    protected ?Invoice $invoice = null;

    protected function getPropertyDefinitions(): array
    {
        return [
            ['name' => Fld::ContactId, 'type' => 'string'],
            [
                'name' => Fld::Type,
                'type' => 'int',
                'allowedValues' => [Api::CustomerType_Debtor, Api::CustomerType_Creditor, Api::CustomerType_Relation],
            ],
            ['name' => Fld::VatTypeId, 'type' => 'int', 'allowedValues' => [Api::VatTypeId_Private, Api::VatTypeId_Business]],
            ['name' => Fld::ContactYourId, 'type' => 'string'],
            ['name' => Fld::ContactStatus, 'type' => 'bool', 'allowedValues' => [Api::ContactStatus_Disabled, Api::ContactStatus_Active]],
            ['name' => Fld::Salutation, 'type' => 'string'],
            ['name' => Fld::Website, 'type' => 'string'],
            ['name' => Fld::VatNumber, 'type' => 'string'],
            ['name' => Fld::Telephone, 'type' => 'string'],
            ['name' => Fld::Telephone2, 'type' => 'string'],
            ['name' => Fld::Fax, 'type' => 'string'],
            ['name' => Fld::Email, 'type' => 'string'],
            ['name' => Fld::OverwriteIfExists, 'type' => 'bool', 'allowedValues' => [Api::OverwriteIfExists_No, Api::OverwriteIfExists_Yes]],
            ['name' => Fld::BankAccountNumber, 'type' => 'string'],
            ['name' => Fld::Mark, 'type' => 'string'],
            ['name' => Fld::DisableDuplicates, 'type' => 'bool', 'allowedValues' => [Api::DisableDuplicates_No, Api::DisableDuplicates_Yes]],
        ];
    }

    // @legacy: needed to support ArrayAccess.
    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    // @legacy: needed to support ArrayAccess.
    public function setInvoice(?Invoice $invoice): void
    {
        $this->invoice = $invoice;
    }

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

    /**
     * Returns the type of the address used as main address.
     *
     * @return string
     *   Either AddressType::Invoice or AddressType::Shipping.
     */
    public function getMainAddressType(): string
    {
        return $this->metadataGet(Meta::MainAddressType) ?? AddressType::Invoice;
    }

    /**
     * @param string|null $vatAddressType
     *   Either AddressType::Invoice, AddressType::Shipping, or null.
     */
    public function setMainAddressType(?string $vatAddressType): void
    {
        assert(in_array($vatAddressType, [AddressType::Invoice, AddressType::Shipping, AddressType::Store, null], true));
        $this->metadataSet(Meta::MainAddressType, $vatAddressType);
    }

    /**
     * Returns the address used for tax calculations.
     *
     * @return Address|null
     *   Returns either the {@see getInvoiceAddress()} (default if
     *   {@see Meta::MainAddressType} is not set), or the {@see getShippingAddress()}.
     */
    public function getFiscalAddress(): ?Address
    {
        return $this->getMainAddressType() === AddressType::Shipping
            ? $this->getShippingAddress()
            : $this->getInvoiceAddress();
    }

    public function hasWarning(): bool
    {
        $hasWarning = parent::hasWarning();
        if (!$hasWarning && $this->getShippingAddress() !== null) {
            $hasWarning = $this->getShippingAddress()->hasWarning();
        }
        if (!$hasWarning && $this->getInvoiceAddress() !== null) {
            $hasWarning = $this->getInvoiceAddress()->hasWarning();
        }
        return $hasWarning;
    }

    /**
     * @throws Error
     *   No address is set.
     * @noinspection NullPointerExceptionInspection
     */
    public function toArray(): array
    {
        if ($this->getMainAddressType() === AddressType::Invoice) {
            $address = $this->getInvoiceAddress() ?? $this->getShippingAddress();
            $altAddress = $this->getShippingAddress() ?? $this->getInvoiceAddress();
        } else {
            $altAddress = $this->getInvoiceAddress() ?? $this->getShippingAddress();
            $address = $this->getShippingAddress() ?? $this->getInvoiceAddress();
        }
        $address = $address->toArray();
        $altAddress = $altAddress->toArray();
        $altAddressKeys = array_keys($altAddress);
        array_walk($altAddressKeys, static function (&$value) {
            $value = 'alt' . ucfirst($value);
        });
        $altAddress = array_combine($altAddressKeys, $altAddress);

        return $this->propertiesToArray() + $address + $altAddress + $this->metadataToArray();
    }

    /**
     * Returns whether to treat this Customer as a company.
     *
     * The return value is based on whether the company name of the fiscal
     * address is not empty.
     *
     * Note that this method does not imply vat liability, as not all companies
     * are vat liable.
     */
    public function isCompany(): bool
    {
        $address = $this->getFiscalAddress();
        return $address !== null && !empty($address->companyName1);
    }

    /**
     * Returns whether this Customer (probably) is vat liable.
     *
     * When available, the return value is based on the {@see vatTypeId}
     * property, otherwise it is based on the {@see Address::$companyName1}
     * property of the {@see getfiscalAddress()} and the {@see vatNumber}
     * property both not being empty.
     *
     * Note that in the absence of vat number checking against external web
     * services (VIES) this method does not return a certainty but a
     * possibility. (Which is fine, as we mostly use it to check if reversed vat
     * is possible, we do not decide to use it or not.)
     */
    public function isVatLiable(): bool
    {
        return !empty($this->vatTypeId)
            ? $this->vatTypeId === Api::VatTypeId_Business
            : $this->isCompany() && !empty($this->vatNumber);
    }
}
