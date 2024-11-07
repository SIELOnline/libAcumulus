<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use DateTimeInterface;
use Error;
use Siel\Acumulus\Api;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Meta;

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
 *
 * @property ?bool $concept
 * @property ?string $conceptType
 * @property ?int $number
 * @property ?int $vatType
 * @property ?DateTimeInterface $issueDate
 * @property ?int $costCenter
 * @property ?int $accountNumber
 * @property ?int $paymentStatus
 * @property ?DateTimeInterface $paymentDate
 * @property ?string $description
 * @property ?string $descriptionText
 * @property ?int $template
 * @property ?string $invoiceNotes
 *
 * @method bool setConcept(bool|int|null $value, int $mode = PropertySet::Always)
 * @method bool setConceptType(?string $value, int $mode = PropertySet::Always)
 * @method bool setNumber(?int $value, int $mode = PropertySet::Always)
 * @method bool setVatType(?int $value, int $mode = PropertySet::Always)
 * @method bool setIssueDate(?DateTimeInterface $value, int $mode = PropertySet::Always)
 * @method bool setCostCenter(?int $value, int $mode = PropertySet::Always)
 * @method bool setAccountNumber(?int $value, int $mode = PropertySet::Always)
 * @method bool setPaymentStatus(?int $value, int $mode = PropertySet::Always)
 * @method bool setPaymentDate(?DateTimeInterface $value, int $mode = PropertySet::Always)
 * @method bool setDescription(?string $value, int $mode = PropertySet::Always)
 * @method bool setDescriptionText(?string $value, int $mode = PropertySet::Always)
 * @method bool setTemplate(?int $value, int $mode = PropertySet::Always)
 * @method bool setInvoiceNotes(?string $value, int $mode = PropertySet::Always)
 *
 * @noinspection PhpLackOfCohesionInspection  Data objects have little cohesion.
 */
class Invoice extends AcumulusObject
{
    use InvoiceArrayAccessTrait;

    protected ?Customer $customer = null;
    /** @var Line[] */
    protected array $lines = [];
    protected ?EmailInvoiceAsPdf $emailAsPdf = null;

    protected function getPropertyDefinitions(): array
    {
        return [
            ['name' => Fld::Concept, 'type' => 'bool', 'allowedValues' => [Api::Concept_No, Api::Concept_Yes]],
            ['name' => Fld::ConceptType, 'type' => 'string'],
            ['name' => Fld::Number, 'type' => 'int'],
            [
                'name' => Fld::VatType,
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
            ['name' => Fld::IssueDate, 'type' => 'date'],
            ['name' => Fld::CostCenter, 'type' => 'int'],
            ['name' => Fld::AccountNumber, 'type' => 'int'],
            [
                'name' => Fld::PaymentStatus,
                'type' => 'int',
                'allowedValues' => [Api::PaymentStatus_Due, Api::PaymentStatus_Paid]
            ],
            ['name' => Fld::PaymentDate, 'type' => 'date'],
            ['name' => Fld::Description, 'type' => 'string'],
            ['name' => Fld::DescriptionText, 'type' => 'string'],
            ['name' => Fld::Template, 'type' => 'int'],
            ['name' => Fld::InvoiceNotes, 'type' => 'string'],
        ];
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): void
    {
        $this->customer = $customer;
        // @legacy: needed to support ArrayAccess.
        $this->customer->setInvoice($this);
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

    public function removeLines(): void
    {
        $this->lines = [];
    }

    public function getEmailAsPdf(): ?EmailAsPdf
    {
        return $this->emailAsPdf;
    }

    public function setEmailAsPdf(?EmailAsPdf $emailAsPdf): void
    {
        $this->emailAsPdf = $emailAsPdf;
    }

    public function hasWarning(): bool
    {
        $hasWarning = parent::hasWarning() || (bool) $this->getCustomer()?->hasWarning() || (bool) $this->getEmailAsPdf()?->hasWarning();
        if (!$hasWarning) {
            foreach ($this->getLines() as $line) {
                if ($line->hasWarning()) {
                    $hasWarning  = true;
                    break;
                }
            }
        }
        return $hasWarning;
    }

    /**
     * @throws Error
     *   customer or emailAsPdf not (yet) set.
     */
    public function toArray(): array
    {
        $invoice = $this->propertiesToArray();
        $lines = [];
        foreach ($this->getLines() as $line) {
            $lines[] = $line->toArray();
        }
        $invoice[Fld::Line] = $lines;

        if ($this->metadataGet(Meta::AddEmailAsPdfSection)) {
            /** @noinspection NullPointerExceptionInspection  should throw on null */
            $invoice[Fld::EmailAsPdf] = $this->getEmailAsPdf()->toArray();
        }
        $invoice += $this->metadataToArray();

        /** @noinspection NullPointerExceptionInspection  should throw on null */
        $customer = $this->getCustomer()->toArray();
        $customer[Fld::Invoice] = $invoice;
        return [Fld::Customer => $customer];
    }

    /**
     * Returns whether the invoice is empty (free products only).
     *
     * @return bool
     *   True if the invoice amount (inc. VAT) is €0,-.
     */
    public function isZeroAmount(): bool
    {
        /** @var \Siel\Acumulus\Invoice\Totals|null $totals */
        $totals = $this->metadataGet(Meta::Totals);
        return $totals !== null && Number::isZero($totals->amountInc);
    }
}
