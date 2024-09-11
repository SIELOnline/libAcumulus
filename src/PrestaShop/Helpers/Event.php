<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Helpers;

use Hook;
use Siel\Acumulus\Collectors\CollectorManager;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Helpers\Event as EventInterface;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Item;
use Siel\Acumulus\Invoice\Source;

/**
 * Event implements the Event interface for PrestaShop.
 */
class Event implements EventInterface
{
    public function triggerInvoiceCreateBefore(Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        Hook::exec('actionAcumulusInvoiceCreateBefore', compact('invoiceSource', 'localResult'));
    }

    public function triggerItemLineCollectBefore(Item $item, CollectorManager $collectorManager): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        Hook::exec('actionAcumulusItemLineCollectBefore', compact('item', 'collectorManager'));
    }

    public function triggerItemLineCollectAfter(Line $line, Item $item, CollectorManager $collectorManager): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        Hook::exec('actionAcumulusItemLineCollectAfter', compact('line', 'item', 'collectorManager'));
    }

    public function triggerInvoiceCollectAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        Hook::exec('actionAcumulusInvoiceCollectAfter', compact('invoice', 'invoiceSource', 'localResult'));
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
