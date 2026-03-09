<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Helpers;

use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Helpers\Event as EventInterface;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Source;

/**
 * Event implements the {@see \Siel\Acumulus\Helpers\Event} interface for WHMCS.
 */
class Event implements EventInterface
{
    public function triggerInvoiceCreateBefore(Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        $this->triggerEvent('AcumulusInvoiceCreateBefore', compact('invoiceSource', 'localResult'));
    }

    public function triggerLineCollectBefore(Line $line, PropertySources $propertySources): void
    {
        $this->triggerEvent('AcumulusLineCollectBefore', compact('line', 'propertySources'));
    }

    public function triggerLineCollectAfter(Line $line, PropertySources $propertySources): void
    {
        $this->triggerEvent('AcumulusLineCollectAfter', compact('line', 'propertySources'));
    }

    public function triggerInvoiceCollectAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        $this->triggerEvent('AcumulusInvoiceCollectAfter', compact('invoice', 'invoiceSource', 'localResult'));
    }

    public function triggerInvoiceCreateAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        $this->triggerEvent('AcumulusInvoiceCreateAfter', compact('invoice', 'invoiceSource', 'localResult'));
    }

    public function triggerInvoiceSendBefore(Invoice $invoice, InvoiceAddResult $localResult): void
    {
        $this->triggerEvent('AcumulusInvoiceSendBefore', compact('invoice', 'localResult'));
    }

    public function triggerInvoiceSendAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $result): void
    {
        $this->triggerEvent('AcumulusInvoiceSendBefore', compact('invoice', 'invoiceSource', 'result'));
    }

    private function triggerEvent(string $eventName, array $params): void
    {
        run_hook($eventName, $params);
    }
}
