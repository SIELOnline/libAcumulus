<?php
/**
 * @noinspection PhpPrivateFieldCanBeLocalVariableInspection  In the future,
 *   $invoice may be made a local variable, but probably we will need it as a
 *   property.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Completors;

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Helpers\Container;

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
     * Completes an {@see \Siel\Acumulus\Data\Invoice}.
     *
     * This phase is executed after the collecting phase.
     *
     * @param \Siel\Acumulus\Data\Invoice $acumulusObject
     */
    public function complete(AcumulusObject $acumulusObject): void
    {
        $this->invoice = $acumulusObject;

        $this->completeCustomer();
        $this->getCompletorTask('Invoice', 'InvoiceNumber')->complete($this->invoice);
        $this->getCompletorTask('Invoice', 'IssueDate')->complete($this->invoice);
        $this->getCompletorTask('Invoice', 'AccountingInfo')->complete($this->invoice);
        $this->getCompletorTask('Invoice', 'MultiLineProperties')->complete($this->invoice);
        $this->getCompletorTask('Invoice', 'Template')->complete($this->invoice);

        // As last!
        $this->getCompletorTask('Invoice', 'Concept')->complete($this->invoice);
    }

    /**
     * Completes the {@see \Siel\Acumulus\Data\Customer} part of the
     * {@see \Siel\Acumulus\Data\Invoice}.
     */
    protected function completeCustomer(): void
    {
        $this->getContainer()->getCompletor('Customer')->complete($this->invoice->getCustomer());
    }
}
