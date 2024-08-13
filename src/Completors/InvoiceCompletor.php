<?php
/**
 * @noinspection PhpPrivateFieldCanBeLocalVariableInspection  In the future,
 *   $invoice may be made a local variable, but probably we will need it as a
 *   property.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Completors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Helpers\MessageCollection;
use Siel\Acumulus\Invoice\Source;

/**
 * InvoiceCompletor completes an {@see \Siel\Acumulus\Data\Invoice}.
 *
 * After an invoice has been collected, the shop specific part, it needs to be
 * completed. think of things like:
 * - Getting vat rates when we have a vat amount and an amount (inc. or ex.).
 * - Determining the cost center, account number and template based on settings
 *   and payment method and status.
 * - Deriving the vat type.
 */
class InvoiceCompletor extends BaseCompletor
{
    private Invoice $invoice;
    /**
     * @var \Siel\Acumulus\Invoice\Source
     *
     * @legacy: The old Completor parts still need the Source.
     */
    private Source $source;

    /**
     * @legacy: The old Completor parts still need the Source.
     */
    protected function getSource(): Source
    {
        return $this->source;
    }

    /**
     * @return $this
     *
     * @legacy: The old Completor parts still need the Source.
     */
    public function setSource(Source $source): InvoiceCompletor
    {
        $this->source = $source;
        return $this;
    }

    /**
     * Completes an {@see \Siel\Acumulus\Data\Invoice}.
     *
     * This phase is executed after the collecting phase.
     *
     * @param \Siel\Acumulus\Data\Invoice $acumulusObject
     * @param \Siel\Acumulus\Invoice\InvoiceAddResult $result
     */
    public function complete(AcumulusObject $acumulusObject, MessageCollection $result): void
    {
        $this->invoice = $acumulusObject;

        $this->completeCustomer($result);
        $this->getCompletorTask('Invoice', 'InvoiceNumber')->complete($this->invoice);
        $this->getCompletorTask('Invoice', 'IssueDate')->complete($this->invoice);
        $this->getCompletorTask('Invoice', 'AccountingInfo')->complete($this->invoice);
        $this->getCompletorTask('Invoice', 'MultiLineProperties')->complete($this->invoice);
        $this->getCompletorTask('Invoice', 'Template')->complete($this->invoice);
        $this->getCompletorTask('Invoice', 'AddEmailAsPdfSection')->complete($this->invoice);
        $this->completeEmailAsPdf($result);

        // @legacy: Not all Completing tasks are already converted, certainly not those that complete Lines.
        /** @var \Siel\Acumulus\Invoice\Completor $completor */
        $completor = $this->getContainer()->getCompletor();
        $completor->complete($this->invoice, $this->getSource(), $result);
        // end of @legacy: Not all Completing tasks are already converted, certainly not those that complete Lines.

        // As last!
        $this->getCompletorTask('Invoice', 'Concept')->complete($this->invoice);
    }

    /**
     * Completes the {@see \Siel\Acumulus\Data\Customer} part of the
     * {@see \Siel\Acumulus\Data\Invoice}.
     */
    protected function completeCustomer(MessageCollection $result): void
    {
        $this->getContainer()->getCompletor('Customer')->complete($this->invoice->getCustomer(), $result);
    }

    /**
     * Completes the {@see \Siel\Acumulus\Data\EmailInvoiceAsPdf} part of the
     * {@see \Siel\Acumulus\Data\Invoice}.
     */
    protected function completeEmailAsPdf(MessageCollection $result): void
    {
        $this->getContainer()->getCompletor('EmailInvoiceAsPdf')->complete($this->invoice->getEmailAsPdf(), $result);
    }
}
