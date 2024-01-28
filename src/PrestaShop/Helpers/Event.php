<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Helpers;

use Hook;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Helpers\Event as EventInterface;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Source;

/**
 * Event implements the Event interface for PrestaShop.
 */
class Event implements EventInterface
{
    public function triggerInvoiceCreateBefore(Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        Hook::exec('actionAcumulusInvoiceCreateBefore', ['source' => $invoiceSource, 'localResult' => $localResult]);
    }

    public function triggerInvoiceCollectAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        Hook::exec('actionAcumulusInvoiceCollectAfter', ['invoice' => $invoice, 'source' => $invoiceSource, 'localResult' => $localResult]);
    }

    public function triggerInvoiceSendBefore(Invoice $invoice, InvoiceAddResult $localResult): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        Hook::exec('actionAcumulusInvoiceSendBefore', ['invoice' => $invoice, 'localResult' => $localResult]);
    }

    public function triggerInvoiceSendAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $result): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        Hook::exec('actionAcumulusInvoiceSendAfter', ['invoice' => $invoice, 'source' => $invoiceSource, 'localResult' => $result]);
    }
}
