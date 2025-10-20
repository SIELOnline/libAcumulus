<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Helpers;

use Hook;
use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Helpers\Event as EventInterface;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Source;

/**
 * Event implements the {@see \Siel\Acumulus\Helpers\Event} interface for PrestaShop.
 */
class Event implements EventInterface
{
    public function triggerInvoiceCreateBefore(Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        Hook::exec('actionAcumulusInvoiceCreateBefore', compact('invoiceSource', 'localResult'));
    }

    public function triggerLineCollectBefore(Line $line, PropertySources $propertySources): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        Hook::exec('actionAcumulusLineCollectBefore', compact('line', 'propertySources'));
    }

    public function triggerLineCollectAfter(Line $line, PropertySources $propertySources): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        Hook::exec('actionAcumulusLineCollectAfter', compact('line', 'propertySources'));
    }

    public function triggerInvoiceCollectAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        Hook::exec('actionAcumulusInvoiceCollectAfter', compact('invoice', 'invoiceSource', 'localResult'));
    }

    public function triggerInvoiceCreateAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        Hook::exec('actionAcumulusInvoiceCreateAfter', compact('invoice', 'invoiceSource', 'localResult'));
    }

    public function triggerInvoiceSendBefore(Invoice $invoice, InvoiceAddResult $localResult): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        Hook::exec('actionAcumulusInvoiceSendBefore', compact('invoice', 'localResult'));
    }

    public function triggerInvoiceSendAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $result): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        Hook::exec('actionAcumulusInvoiceSendAfter', compact('invoice', 'invoiceSource', 'result'));
    }
}
