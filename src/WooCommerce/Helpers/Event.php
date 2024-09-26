<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Helpers;

use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Helpers\Event as EventInterface;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Source;

/**
 * Event implements the {@see \Siel\Acumulus\Helpers\Event} interface for WooCommerce.
 */
class Event implements EventInterface
{
    public function triggerInvoiceCreateBefore(Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        do_action('acumulus_invoice_create_before', $invoiceSource, $localResult);
    }

    public function triggerLineCollectBefore(Line $line, PropertySources $propertySources): void
    {
        do_action('acumulus_line_collect_before', $line, $propertySources);
    }

    public function triggerLineCollectAfter(Line $line, PropertySources $propertySources): void
    {
        do_action('acumulus_line_collect_after', $line, $propertySources);
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
