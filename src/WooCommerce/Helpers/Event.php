<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Helpers;

use Siel\Acumulus\Collectors\CollectorManager;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Helpers\Event as EventInterface;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Item;
use Siel\Acumulus\Invoice\Source;

/**
 * Event implements the Event interface for WooCommerce.
 */
class Event implements EventInterface
{
    public function triggerInvoiceCreateBefore(Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        do_action('acumulus_invoice_create_before', $invoiceSource, $localResult);
    }

    public function triggerItemLineCollectBefore(Item $item, CollectorManager $collectorManager): void
    {
        do_action('acumulus_item_line_collect_before', $item, $collectorManager);
    }

    public function triggerItemLineCollectAfter(Line $line, Item $item, CollectorManager $collectorManager): void
    {
        do_action('acumulus_item_line_collect_after', $line, $item, $collectorManager);
    }

    public function triggerInvoiceCollectAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        do_action('acumulus_invoice_collect_after', $invoice, $invoiceSource, $localResult);
    }

    public function triggerInvoiceSendBefore(Invoice $invoice, InvoiceAddResult $localResult): void
    {
        do_action('acumulus_invoice_send_before', $invoice, $localResult);
    }

    public function triggerInvoiceSendAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $result): void
    {
        do_action('acumulus_invoice_send_after', $invoice, $invoiceSource, $result);
    }
}
