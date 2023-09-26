<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Helpers;

use Magento\Framework\Event\ManagerInterface;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Helpers\Event as EventInterface;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Source;

/**
 * Event implements the Event interface for Magento.
 */
class Event implements EventInterface
{
    public function triggerInvoiceCreateBefore(Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        $this->getEventManager()->dispatch(
            'acumulus_invoice_create_before',
            ['source' => $invoiceSource, 'localResult' => $localResult]
        );
    }

    public function triggerInvoiceCollectAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        $this->getEventManager()->dispatch(
            'acumulus_invoice_collect_after',
            ['invoice' => $invoice, 'source' => $invoiceSource, 'localResult' => $localResult]
        );
    }

    public function triggerInvoiceSendBefore(Invoice $invoice, InvoiceAddResult $localResult): void
    {
        $this->getEventManager()->dispatch(
            'acumulus_invoice_send_before',
            ['invoice' => $invoice, 'localResult' => $localResult]
        );
    }

    public function triggerInvoiceSendAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $result): void
    {
        $this->getEventManager()->dispatch(
            'acumulus_invoice_send_after',
            ['invoice' => $invoice, 'source' => $invoiceSource, 'result' => $result]
        );
    }

    private function getEventManager(): ManagerInterface
    {
        return Registry::getInstance()->get(ManagerInterface::class);
    }
}
