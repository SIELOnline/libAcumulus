<?php

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

use Siel\Acumulus\Collectors\CollectorManager;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Item;
use Siel\Acumulus\Invoice\Product;
use Siel\Acumulus\Invoice\Source;

/**
 * Event does foo.
 */
interface Event
{
    /**
     * Triggers an event that an invoice for Acumulus is to be created and sent.
     *
     * This event allows you to:
     * - Prevent the invoice from being created and sent at all. To do so,
     *   change the send status using {@see InvoiceAddResult::setSendStatus()}
     *   on the $localResult parameter.
     * - Inject custom behaviour before the invoice is created (collected and
     *   completed) and sent.
     *
     * @param Source $invoiceSource
     *   The source object (order, credit note) for which the invoice was created.
     * @param \Siel\Acumulus\Invoice\InvoiceAddResult $localResult
     *   Contains any earlier generated messages and the initial send status.
     *   You can add your own messages and/or change the send status.
     */
    public function triggerInvoiceCreateBefore(Source $invoiceSource, InvoiceAddResult $localResult): void;

    /**
     * Triggers an event that an item line is to be "collected".
     *
     * This event allows you to:
     * - Add property sources to the $collectorManager.
     * - Inject custom behaviour before an item line is collected, but before it is
     *   completed and sent.
     *
     * @param \Siel\Acumulus\Invoice\Item $item
     *   The item (line) for which this item line has been collected. The product sold (or
     *   refunded) on the item (line) can be retrieved with $item->getProduct().
     * @param \Siel\Acumulus\Collectors\CollectorManager $collectorManager
     *   The manager to add property sources to.
     */
    public function triggerItemLineCollectBefore(Item $item, CollectorManager $collectorManager): void;

    /**
     * Triggers an event that an item line has been "collected".
     *
     * This event allows you to:
     * - Remove property sources added during the {@see triggerItemLineCollectBefore}
     *   event.
     * - Inject custom behaviour after an item line has been collected, but before it is
     *   completed and sent.
     *
     * @param \Siel\Acumulus\Data\Line $line
     *    The collected item line.
     * @param \Siel\Acumulus\Invoice\Item $item
     *   The item (line) for which this item line has been collected.The product sold (or
     *   refunded) on the item (line) can be retrieved with $item->getProduct().
     * @param \Siel\Acumulus\Collectors\CollectorManager $collectorManager
     *   The manager to remove property sources from.
     */
    public function triggerItemLineCollectAfter(Line $line, Item $item, CollectorManager $collectorManager): void;

    /**
     * Triggers an event that an invoice for Acumulus has been "collected" and
     * is ready to be completed and sent.
     *
     * This event allows you to:
     * - Change the invoice by changing the collected data. This is the place to do so if
     *   you need access to the data from the shop environment this library is running in.
     * - Prevent the invoice from being completed and sent. To do so, change the
     *   send status using {@see InvoiceAddResult::setSendStatus()} on the
     *   $localResult parameter.
     * - Inject custom behaviour after the invoice has been created (collected),
     *   but before it is completed and sent.
     *
     * @param \Siel\Acumulus\Data\Invoice $invoice
     *   The invoice that has been collected.
     * @param Source $invoiceSource
     *   The source object (order, credit note) for which the invoice is created.
     * @param \Siel\Acumulus\Invoice\InvoiceAddResult $localResult
     *   Contains any earlier generated messages and the initial send status.
     *   You can add your own messages and/or change the send status.
     */
    public function triggerInvoiceCollectAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $localResult): void;

    /**
     * Triggers an event that an invoice for Acumulus is ready to be sent.
     *
     * This event allows you to:
     * - Change the invoice by adding or changing the collected data. This is
     *   the place to do so if you need access to the complete invoice itself
     *   just before sending. Note that no Shop order or credit note objects
     *   are passed to this event.
     * - Prevent the invoice from being sent. To do so, change the send status
     *   using {@see InvoiceAddResult::setSendStatus()} on the $result
     *   parameter.
     * - Inject custom behaviour just before sending.
     *
     * @param \Siel\Acumulus\Data\Invoice $invoice
     *   The invoice that has been created.
     * @param \Siel\Acumulus\Invoice\InvoiceAddResult $localResult
     *   Contains any earlier generated messages and the initial send status.
     *   You can add your own messages and/or change the send status.
     */
    public function triggerInvoiceSendBefore(Invoice $invoice, InvoiceAddResult $localResult): void;

    /**
     * Triggers an event after an invoice for Acumulus has been sent.
     *
     * This event will also be triggered when sending the invoice resulted in an error,
     * but not when sending was prevented locally due to e.g. no need to send, an earlier
     * event that prevented sending, or the dry-run modus.
     *
     * This event allows you to:
     * - Inject custom behavior to react to the result of sending the invoice.
     *
     * @param \Siel\Acumulus\Data\Invoice $invoice
     *   The invoice that has been sent.
     * @param Source $invoiceSource
     *   The source object (order, credit note) for which the invoice was sent.
     * @param \Siel\Acumulus\Invoice\InvoiceAddResult $result
     *   The result, response, status, and any messages, as sent back by
     *   Acumulus (or set earlier locally).
     */
    public function triggerInvoiceSendAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $result): void;
}
