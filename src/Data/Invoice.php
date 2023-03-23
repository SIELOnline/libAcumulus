<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use DateTime;
use Siel\Acumulus\Api;

/**
 * Represents an Acumulus API Invoice object.
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
 * @property ?int $concept
 * @property ?string $conceptType
 * @property ?int $number
 * @property ?int $vatType
 * @property ?\DateTime $issueDate
 * @property ?int $costCenter
 * @property ?int $accountNumber
 * @property ?int $paymentStatus
 * @property ?\DateTime $paymentDate
 * @property ?string $description
 * @property ?string $descriptionText
 * @property ?int $template
 * @property ?string $invoiceNotes
 *
 * @method bool setConcept(?int $value, int $mode = PropertySet::Always)
 * @method bool setConceptType(?string $value, int $mode = PropertySet::Always)
 * @method bool setNumber(?int $value, int $mode = PropertySet::Always)
 * @method bool setVatType(?int $value, int $mode = PropertySet::Always)
 * @method bool setIssueDate(?DateTime $value, int $mode = PropertySet::Always)
 * @method bool setCostCenter(?int $value, int $mode = PropertySet::Always)
 * @method bool setAccountNumber(?int $value, int $mode = PropertySet::Always)
 * @method bool setPaymentStatus(?int $value, int $mode = PropertySet::Always)
 * @method bool setPaymentDate(?DateTime $value, int $mode = PropertySet::Always)
 * @method bool setDescription(?string $value, int $mode = PropertySet::Always)
 * @method bool setDescriptionText(?string $value, int $mode = PropertySet::Always)
 * @method bool setTemplate(?int $value, int $mode = PropertySet::Always)
 * @method bool setInvoiceNotes(?string $value, int $mode = PropertySet::Always)
 *
 * @noinspection PhpLackOfCohesionInspection  Data objects have little cohesion.
 */
class Invoice extends AcumulusObject
{
    protected function getPropertyDefinitions(): array
    {
        return [
            ['name' => 'concept', 'type' => 'bool', 'required' => true, 'allowedValues' => [Api::Concept_No, Api::Concept_Yes]],
            ['name' => 'conceptType', 'type' => 'string'],
            ['name' => 'number', 'type' => 'int'],
            [
                'name' => 'vatType',
                'type' => 'int',
                'allowedValues' => [
                    Api::VatType_National,
                    Api::VatType_NationalReversed,
                    Api::VatType_EuReversed,
                    Api::VatType_RestOfWorld,
                    Api::VatType_MarginScheme,
                    Api::VatType_EuVat,
                    Api::VatType_OtherForeignVat,
                ],
            ],
            ['name' => 'issueDate', 'type' => 'date'],
            ['name' => 'costCenter', 'type' => 'int'],
            ['name' => 'accountNumber', 'type' => 'int'],
            [
                'name' => 'paymentStatus',
                'type' => 'int',
                'required' => true,
                'allowedValues' => [Api::PaymentStatus_Due, Api::PaymentStatus_Paid]
            ],
            ['name' => 'paymentDate', 'type' => 'date'],
            ['name' => 'description', 'type' => 'string'],
            ['name' => 'descriptionText', 'type' => 'string'],
            ['name' => 'template', 'type' => 'int'],
            ['name' => 'invoiceNotes', 'type' => 'string'],
        ];
    }

    protected ?Customer $customer = null;
    /** @var Line[] */
    protected array $lines = [];
    protected ?EmailAsPdf $emailAsPdf = null;

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): void
    {
        $this->customer = $customer;
    }

    /**
     * @return Line[]
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    public function addLine(?Line $line): void
    {
        if ($line !== null) {
            $this->lines[] = $line;
        }
    }

    public function getEmailAsPdf(): ?EmailAsPdf
    {
        return $this->emailAsPdf;
    }

    public function setEmailAsPdf(?EmailAsPdf $emailAsPdf): void
    {
        $this->emailAsPdf = $emailAsPdf;
    }
}
