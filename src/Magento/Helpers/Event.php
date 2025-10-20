<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Helpers;

use Magento\Framework\Event\ManagerInterface;
use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Helpers\Event as EventInterface;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Source;

/**
 * Event implements the {@see \Siel\Acumulus\Helpers\Event} interface for Magento.
 */
class Event implements EventInterface
{
    public function triggerInvoiceCreateBefore(Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        $this->getEventManager()->dispatch('acumulus_invoice_create_before', compact('invoiceSource', 'localResult'));
    }

    public function triggerLineCollectBefore(Line $line, PropertySources $propertySources): void
    {
        $this->getEventManager()->dispatch('acumulus_line_collect_before', compact('line', 'propertySources'));
    }

    public function triggerLineCollectAfter(Line $line, PropertySources $propertySources): void
    {
        $this->getEventManager()->dispatch('acumulus_line_collect_after', compact('line', 'line', 'propertySources'));
    }

    public function triggerInvoiceCollectAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        $this->getEventManager()->dispatch('acumulus_invoice_collect_after', compact('invoice', 'invoiceSource', 'localResult'));
    }

    public function triggerInvoiceCreateAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        $this->getEventManager()->dispatch('acumulus_invoice_create_after', compact('invoice', 'invoiceSource', 'localResult'));
    }

    public function triggerInvoiceSendBefore(Invoice $invoice, InvoiceAddResult $localResult): void
    {
        $this->getEventManager()->dispatch('acumulus_invoice_send_before', compact('invoice', 'localResult'));
    }

    public function triggerInvoiceSendAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $result): void
    {
        $this->getEventManager()->dispatch('acumulus_invoice_send_after', compact('invoice', 'invoiceSource', 'result'));
    }

    private function getEventManager(): ManagerInterface
    {
        return Registry::getInstance()->get(ManagerInterface::class);
    }
}
