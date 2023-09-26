<?php

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Invoice\InvoiceAddResult;
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
     * Triggers an event that an invoice for Acumulus has been "collected" and
     * is ready to be completed and sent.
     *
     * This event allows you to:
     * - Change the invoice by adding or changing the collected data. This is
     *   the place to do so if you need access to the data from the environment
     *   this library is running in.
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
