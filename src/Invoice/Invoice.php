<?php

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Api;

/**
 * @property int $concept
 * @property ?string $conceptType
 * @property ?int $number
 * @property ?int $vatType
 * @property ?\DateTime $issueDate
 * @property ?int $costCenter
 * @property ?int $accountNumber
 * @property int $paymentStatus
 * @property ?\DateTime $paymentDate
 * @property ?string $description
 * @property ?string $descriptionText
 * @property ?int $template
 * @property ?string $invoiceNotes
 */
class Invoice extends AcumulusObject
{
    protected static array $propertyDefinitions = [
        ['name' => 'concept', 'type' =>'int', 'required' => true, 'allowedValues' => [Api::Concept_No, Api::Concept_Yes]],
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

    /** @var Line[] $lines */
    protected array $lines = [];
    protected ?EmailAsPdf $emailAsPdf;
    protected ?Customer $customer;
}
