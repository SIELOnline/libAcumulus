<?php

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Collectors\CollectorManager;
use Siel\Acumulus\Completors\InvoiceCompletor;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Event;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Source;

/**
 * Create creates an {@see Invoice}.
 *
 * Creating consists of the tasks of collecting and completing an invoice.
 */
class InvoiceCreate
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    protected function getContainer(): Container
    {
        return $this->container;
    }

    protected function getEvent(): Event
    {
        return $this->getContainer()->getEvent();
    }

    protected function getCollectorManager(): CollectorManager
    {
        return $this->getContainer()->getCollectorManager();
    }

    protected function getInvoiceCompletor(): InvoiceCompletor
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getContainer()->getCompletor(DataType::Invoice);
    }

    /**
     * Creates the invoice, i.e. Collect and Complete it.
     *
     * - Trigger event: InvoiceCreateBefore.
     * - Create the invoice:
     *     - Collect the invoice.
     *     - Trigger event: InvoiceCreateAfter.
     *     - Complete the invoice.
     *
     * Note that if:
     * - {@see \Siel\Acumulus\Shop\InvoiceSend::setBasicSendStatus()} results in that the
     *    invoice should not be sent, we trigger the InvoiceCreateBefore event anyway to
     *    allow custom code to change that decision.
     * - We encounter local errors, we do not set the
     *   {@see InvoiceAddResult::getSendStatus()} to
     *   {@see Result::NotSent_LocalErrors}. The {@see Invoice} will be passed
     *   to {@see \Siel\Acumulus\Shop\InvoiceCreate} anyway, which will first trigger the
     *    InvoiceSendBefore event, to allow custom code to solve errors and continue the
     *    sending. Only after that event further sending may be prevented.
     */
    public function create(Source $invoiceSource, InvoiceAddResult $result): ?Invoice
    {
        $this->getEvent()->triggerInvoiceCreateBefore($invoiceSource, $result);
        if (!$result->isSendingPrevented()) {
            $invoice = $this->getCollectorManager()->collectInvoiceForSource($invoiceSource, $result);
            $result->setInvoice($invoice);
            $this->getEvent()->triggerInvoiceCollectAfter($invoice, $invoiceSource, $result);
            if (!$result->isSendingPrevented()) {
                $this->getInvoiceCompletor()->setSource($invoiceSource)->complete($invoice, $result);
            }
        }
        return $invoice ?? null;
    }
}
