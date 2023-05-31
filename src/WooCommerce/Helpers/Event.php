<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Helpers;

use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Helpers\Event as EventInterface;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Source;

/**
 * Event implements the Event class for WooCommerce.
 */
class Event implements EventInterface
{

    /**
     * @inheritDoc
     */
    public function triggerInvoiceCreateBefore(Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        do_action('acumulus_invoice_create_before', $invoiceSource, $localResult);
    }

    /**
     * @inheritDoc
     */
    public function triggerInvoiceCollectAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        do_action('acumulus_invoice_collect_after', $invoice, $invoiceSource, $localResult);
    }

    /**
     * @inheritDoc
     */
    public function triggerInvoiceSendBefore(Invoice $invoice, InvoiceAddResult $localResult): void
    {
        do_action('acumulus_invoice_send_before', $invoice, $localResult);
    }

    /**
     * @inheritDoc
     */
    public function triggerInvoiceSendAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $result): void
    {
        do_action('acumulus_invoice_send_after', $invoice, $invoiceSource, $result);
    }
}
