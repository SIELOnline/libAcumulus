<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Helpers;

use Siel\Acumulus\Collectors\CollectorManager;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Item;
use Siel\Acumulus\Invoice\Source;

/**
 * Event does foo.
 */
class Event implements \Siel\Acumulus\Helpers\Event
{
    /**
     * @inheritDoc
     */
    public function triggerInvoiceCreateBefore(Source $invoiceSource, InvoiceAddResult $localResult): void
    {
    }

    public function triggerItemLineCollectBefore(Item $item, CollectorManager $collectorManager): void
    {
    }

    public function triggerItemLineCollectAfter(Line $line, Item $item, CollectorManager $collectorManager): void
    {
    }

    /**
     * @inheritDoc
     */
    public function triggerInvoiceCollectAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
    }

    /**
     * @inheritDoc
     */
    public function triggerInvoiceSendBefore(Invoice $invoice, InvoiceAddResult $localResult): void
    {
    }

    /**
     * @inheritDoc
     */
    public function triggerInvoiceSendAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $result): void
    {
    }
}
