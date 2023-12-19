<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Shop;

use DateTime;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Source;

/**
 * invoiceManager does foo.
 */
class invoiceManager extends \Siel\Acumulus\Shop\InvoiceManager
{
    public function getInvoiceSourcesByIdRange(string $invoiceSourceType, int $invoiceSourceIdFrom, int $invoiceSourceIdTo): array
    {
        return [];
    }

    public function getInvoiceSourcesByDateRange(string $invoiceSourceType, DateTime $dateFrom, DateTime $dateTo): array
    {
        return [];
    }

    protected function triggerInvoiceCreated(?array &$invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
    }

    protected function triggerInvoiceSendBefore(?array &$invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
    }

    protected function triggerInvoiceSendAfter(array $invoice, Source $invoiceSource, InvoiceAddResult $result): void
    {
    }
}
