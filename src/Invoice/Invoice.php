<?php

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Api;

/**
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
 * @method bool setConcept(?int $value, int $mode = AcumulusProperty::Set_Always)
 * @method bool setConceptType(?string $value, int $mode = AcumulusProperty::Set_Always)
 * @method bool setNumber(?int $value, int $mode = AcumulusProperty::Set_Always)
 * @method bool setVatType(?int $value, int $mode = AcumulusProperty::Set_Always)
 * @method bool setIssueDate(?\DateTime $value, int $mode = AcumulusProperty::Set_Always)
 * @method bool setCostCenter(?int $value, int $mode = AcumulusProperty::Set_Always)
 * @method bool setAccountNumber(?int $value, int $mode = AcumulusProperty::Set_Always)
 * @method bool setPaymentStatus(?int $value, int $mode = AcumulusProperty::Set_Always)
 * @method bool setPaymentDate(?\DateTime $value, int $mode = AcumulusProperty::Set_Always)
 * @method bool setDescription(?string $value, int $mode = AcumulusProperty::Set_Always)
 * @method bool setDescriptionText(?string $value, int $mode = AcumulusProperty::Set_Always)
 * @method bool setTemplate(?int $value, int $mode = AcumulusProperty::Set_Always)
 * @method bool setInvoiceNotes(?string $value, int $mode = AcumulusProperty::Set_Always)
 */
class Invoice extends AcumulusObject
{
    protected static array $propertyDefinitions = [
        ['name' => 'concept', 'type' =>'bool', 'required' => true, 'allowedValues' => [Api::Concept_No, Api::Concept_Yes]],
        ['name' => 'conceptType', 'type' =>'string'],
        ['name' => 'number', 'type' =>'int'],
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
        ['name' => 'costCenter', 'type' =>'int'],
        ['name' => 'accountNumber', 'type' =>'int'],
        ['name' => 'paymentStatus', 'type' =>'int', 'required' => true, 'allowedValues' => [Api::PaymentStatus_Due, Api::PaymentStatus_Paid]],
        ['name' => 'paymentDate', 'type' => 'date'],
        ['name' => 'description', 'type' =>'string'],
        ['name' => 'descriptionText', 'type' =>'string'],
        ['name' => 'template', 'type' =>'int'],
        ['name' => 'invoiceNotes', 'type' =>'string'],
    ];

    /** @var Line[] */
    protected array $lines = [];
    protected ?EmailAsPdf $emailAsPdf;
    protected ?Customer $customer;

    /**
     * @return \Siel\Acumulus\Invoice\Customer|null
     */
    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    /**
     * @param \Siel\Acumulus\Invoice\Customer $customer
     */
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

    /**
     * @param Line $line
     */
    public function addLine(Line $line): void
    {
        $this->lines[] = $line;
    }

    /**
     * @return \Siel\Acumulus\Invoice\EmailAsPdf|null
     */
    public function getEmailAsPdf(): ?EmailAsPdf
    {
        return $this->emailAsPdf;
    }

    /**
     * @param \Siel\Acumulus\Invoice\EmailAsPdf $emailAsPdf
     */
    public function setEmailAsPdf(EmailAsPdf $emailAsPdf): void
    {
        $this->emailAsPdf = $emailAsPdf;
    }
}
