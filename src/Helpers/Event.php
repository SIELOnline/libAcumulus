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
 * Implementing classes should convert these events into shop-specific event triggers.
 *
 * The order of the execution of these events is as follows:
 * - event InvoiceCreateBefore
 * - collect Invoice
 *     - event LineCollectBefore
 *     - collect item line 1 (including its possible children)
 *     - event LineCollectAfter
 *     - ... repeat for other item lines
 *     - event LineCollectBefore
 *     - collect shipping line 1 (including its possible children)
 *     - event LineCollectAfter
 *     - ... repeat for other special lines: shipping, gift wrapping, payment, discount,
 *           manual, voucher or any other line type your shop may support
 * - event InvoiceCollectAfter
 * - complete Invoice
 * - event InvoiceCreateAfter
 * // From here on the invoice source (object from your web shop) is no longer available
 * - event InvoiceSendBefore
 * - send to Acumulus
 * - event InvoiceSendAfter (only when the inmvoice has really been sent)
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
     *   <code>
     *       $localResult->setSendStatus(InvoiceAddResult::NotSent_LocalErrors);
     *       $localResult->addMessage(Message::create('My message', Severity::Error));
     *   </code>
     * - Inject custom behaviour before the invoice is created (collected and completed)
     *   and sent.
     *
     * @param Source $invoiceSource
     *   The source object (order, credit note) for which the invoice was created.
     * @param \Siel\Acumulus\Invoice\InvoiceAddResult $localResult
     *   Contains any earlier generated messages and the initial send-status.
     *   You can add your own messages and/or change the send-status.
     */
    public function triggerInvoiceCreateBefore(Source $invoiceSource, InvoiceAddResult $localResult): void;

    /**
     * Triggers an event that a main line and its possible children are to be "collected".
     *
     * This event allows you to:
     * - Add property sources to the $collectorManager.
     *   <code>$propertySources->add('bookingInfo', Bookings::getByOrderLine($item->getId());</code>
     * - Directly set some values or metadata on the line.
     *   <code>$line->itemNumber = $shippingLineInfo->moduleId;</code>
     * - Prevent a line from being collected and added to the invoice by setting the
     *   metadata value {@see \Siel\Acumulus\Meta::DoNotAdd} to true:
     *   <code>$line->metadataSet(Meta::DoNotAdd, true);</code>
     * - Inject custom behaviour before a line is collected.
     *
     * @param \Siel\Acumulus\Data\Line $line
     *   The line to store the collected values in.
     * @param \Siel\Acumulus\Collectors\PropertySources $propertySources
     *   The set of "objects" that can be used to collect data from. Available will be:
     *
     *   For all lines:
     *   - 'invoice': The {@see \Siel\Acumulus\Data\Invoice} being collected. The invoice,
     *     customer, both addresses, and emailAsPdf parts will already have been
     *     collected.
     *   - 'source': The {@see \Siel\Acumulus\Invoice\Source} for which the invoice is
     *     being collected.
     *   - 'key': The key with which the above "lineInfo" object is passed to the
     *     {@see \Siel\Acumulus\Collectors\Collector}. Normally, this is not needed, but
     *     there are cases where it contains valuable information: OC shipping tax lines.
     *
     *   For item lines:
     *   - 'item': An {@see \Siel\Acumulus\Invoice\Item} object, wrapping a shop-specific
     *     item Line.
     *   - 'product': A {@see \Siel\Acumulus\Product\Product} object, wrapping a
     *     shop-specific product.
     *
     *   For other line types:
     *   - '{lineType}LineInfo': the main "object" that results in 1 line. E.g. a shipping
     *     or payment fee record. Each "info" should result in 1 line. {lineType} is the
     *     {@see \lcfirst()} value of a {@see \Siel\Acumulus\Data\LineType} constant, e.g:
     *     'shippingLineInfo', 'giftWrappingFeeLineInfo', etc.
     */
    public function triggerLineCollectBefore(Line $line, PropertySources $propertySources): void;

    /**
     * Triggers an event that a main line and its possible children have been "collected".
     *
     * This event allows you to:
     * - Remove property sources added during the {@see triggerLineCollectBefore}
     *   event.
     *   <code>$propertySources->remove('bookingInfo');</code>
     * - Prevent a line, and its children, from being added to the invoice by setting the
     *   metadata value {@see \Siel\Acumulus\Meta::DoNotAdd} to true:
     *   <code>$line->metadataSet(Meta::DoNotAdd, true);</code>
     * - Inject custom behaviour after a line, and its children, have been collected.
     *
     * @param \Siel\Acumulus\Data\Line $line
     *   The collected line.
     * @param \Siel\Acumulus\Collectors\PropertySources $propertySources
     *   The set of "objects" that can be used to collect data from.
     *   See {@see triggerLineCollectBefore()} for which values will be available.
     */
    public function triggerLineCollectAfter(Line $line, PropertySources $propertySources): void;

    /**
     * Triggers an event that an invoice for Acumulus has been "collected" and is ready to
     * be completed and sent.
     *
     * This event allows you to:
     * - Change the invoice by changing the collected data.
     *   <code>
     *       if ($isPaypalPayment) {
     *           $invoice->accountNumber = $paypalAccountIdInAcumulus;
     *       }
     *   </code>
     * - Prevent the invoice from being completed and sent.
     *   See example code {@see Event::triggerInvoiceCreateBefore() above}.
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
     * Triggers an event that an invoice for Acumulus has been created, that is
     * "collected" and "completed", and is ready to be sent.
     *
     * This event allows you to:
     * - Change the invoice by changing the collected and completed data. This is the
     *   place to do so if you need access to the data from the shop environment this
     *   library is running in.
     * - Prevent the invoice from being completed and sent.
     *   See example code {@see Event::triggerInvoiceCreateBefore() above}.
     * - Inject custom behaviour after the invoice has been created,
     *   but before it is sent.
     *
     * @param \Siel\Acumulus\Data\Invoice $invoice
     *   The invoice that has been created.
     * @param Source $invoiceSource
     *   The source object (order, credit note) for which the invoice is created.
     * @param \Siel\Acumulus\Invoice\InvoiceAddResult $localResult
     *   Contains any earlier generated messages and the initial send-status.
     *   You can add your own messages and/or change the send-status.
     */
    public function triggerInvoiceCreateAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $localResult): void;

    /**
     * Triggers an event that an invoice for Acumulus is ready to be sent.
     *
     * This event allows you to:
     * - Change the invoice by adding or changing the collected data. This is the place to
     *   do so if you need access to the complete invoice itself just before sending.
     *   Note that no Shop order or credit note objects are passed to this event.
     * - Prevent the invoice from being completed and sent.
     *   See example code {@see Event::triggerInvoiceCreateBefore() above}.
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
     * but not when sending was prevented locally due to e.g. no need to send, a local
     * error that prevented sending, or the dry-run modus.
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
