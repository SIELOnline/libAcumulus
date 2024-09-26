<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Shop;

use DateTimeInterface;

/**
 * invoiceManager does foo.
 */
class invoiceManager extends \Siel\Acumulus\Shop\InvoiceManager
{
    public function getInvoiceSourcesByIdRange(string $invoiceSourceType, int $invoiceSourceIdFrom, int $invoiceSourceIdTo): array
    {
        return [];
    }

    public function getInvoiceSourcesByDateRange(string $invoiceSourceType, DateTimeInterface $dateFrom, DateTimeInterface $dateTo): array
    {
        return [];
    }
}
