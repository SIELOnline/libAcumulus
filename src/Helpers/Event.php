<?php

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Source;

/**
 * Event defines the events that our library triggers.
 *
 * Implementing classes should convert these events into shop-specific event triggers
 */
interface Event
{
    /**
     * Triggers an event that an invoice for Acumulus is to be created and sent.
     *
     * This event allows you to:
     * - Prevent the invoice from being created and sent at all. To do so,
     *   change the send-status using {@see InvoiceAddResult::setSendStatus()}
     *   on the $localResult parameter.
     * - Inject custom behaviour before the invoice is created (collected and
     *   completed) and sent.
     *
     * @param Source $invoiceSource
     *   The source object (order, credit note) for which the invoice was created.
     * @param \Siel\Acumulus\Invoice\InvoiceAddResult $localResult
     *   Contains any earlier generated messages and the initial send-status.
     *   You can add your own messages and/or change the send-status.
     */
    public function triggerInvoiceCreateBefore(Source $invoiceSource, InvoiceAddResult $localResult): void;

    /**
     * Triggers an event that a line is to be "collected".
     *
     * This event allows you to:
     * - Add property sources to the $collectorManager.
     * - directly set some values or metadata on the line.
     * - Inject custom behaviour before an item line is collected, but before it is
     *   completed and sent.
     * - Prevent a line being collected and added to the invoice by setting the metadata
     *   value {@see \Siel\Acumulus\Meta::DoNotAdd} to true.
     *
     * @param \Siel\Acumulus\Data\Line $line
     *   The line to store the collected values in.
     * @param \Siel\Acumulus\Collectors\PropertySources $propertySources
     *   The set of "objects" that can be used to collect data from. Available will be:
     *   - 'invoice': The {@see \Siel\Acumulus\Data\Invoice} being collected. The invoice,
     *     customer, both addresses, and emailAsPdf parts will already have been
     *     collected.
     *   - 'source': The {@see \Siel\Acumulus\Invoice\Source} for which the invoice is
     *     being collected.
     *   - '{lineType}LineInfo': the main "object" that results in 1 line. E.g. a product
     *     order item record. Each record should result in 1 line, whereas the above
     *     "objects" (invoice and source) are the same for every line being collected.
     *     {lineType} is the {@see \lcfirst()} value of the
     *     {@see \Siel\Acumulus\Data\LineType} constant name (not its value).
     *   - 'key': The key with which the above "lineInfo" object was passed to the
     *     collector. Most of the time this is not needed, but there are cases where it
     *     contains valuable information (OC: shipping tax lines).
     */
    public function triggerLineCollectBefore(Line $line, PropertySources $propertySources): void;

    /**
     * Triggers an event that an item line has been "collected".
     *
     * This event allows you to:
     * - Remove property sources added during the {@see triggerLineCollectBefore}
     *   event.
     * - Inject custom behaviour after an item line has been collected, but before it is
     *   completed and sent.
     *
     * @param \Siel\Acumulus\Data\Line $line
     *    The collected item line.
     *   The item (line) for which this item line has been collected.The product sold (or
     *   refunded) on the item (line) can be retrieved with $item->getProduct().
     * @param \Siel\Acumulus\Collectors\PropertySources $propertySources
     *   The set of "objects" that can be used to collect data from.
     */
    public function triggerLineCollectAfter(Line $line, PropertySources $propertySources): void;

    /**
     * Triggers an event that an invoice for Acumulus has been "collected" and
     * is ready to be completed and sent.
     *
     * This event allows you to:
     * - Change the invoice by changing the collected data. This is the place to do so if
     *   you need access to the data from the shop environment this library is running in.
     * - Prevent the invoice from being completed and sent. To do so, change the
     *   send-status using {@see InvoiceAddResult::setSendStatus()} on the
     *   $localResult parameter.
     * - Inject custom behaviour after the invoice has been created (collected),
     *   but before it is completed and sent.
     *
     * @param \Siel\Acumulus\Data\Invoice $invoice
     *   The invoice that has been collected.
     * @param Source $invoiceSource
     *   The source object (order, credit note) for which the invoice is created.
     * @param \Siel\Acumulus\Invoice\InvoiceAddResult $localResult
     *   Contains any earlier generated messages and the initial send-status.
     *   You can add your own messages and/or change the send-status.
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
     * - Prevent the invoice from being sent. To do so, change the send-status
     *   using {@see InvoiceAddResult::setSendStatus()} on the $result
     *   parameter.
     * - Inject custom behaviour just before sending.
     *
     * @param \Siel\Acumulus\Data\Invoice $invoice
     *   The invoice that has been created.
     * @param \Siel\Acumulus\Invoice\InvoiceAddResult $localResult
     *   Contains any earlier generated messages and the initial send-status.
     *   You can add your own messages and/or change the send-status.
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
