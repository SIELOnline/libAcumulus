<?php
/**
 * @noinspection SqlDialectInspection
 * @noinspection SqlNoDataSourceInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\HikaShop\Shop;

use DateTimeInterface;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Joomla\Shop\InvoiceManager as BaseInvoiceManager;

/**
 * This override provides HikaShop specific queries.
 *
 * SECURITY REMARKS
 * ----------------
 * HikaShop orders are queried via self constructed queries, so this class is
 * responsible for sanitising itself.
 * - Numbers are cast by using numeric formatters (like %u, %d, %f) with
 *   sprintf().
 * - Strings are escaped using the escape() method of the DB driver class.
 */
class InvoiceManager extends BaseInvoiceManager
{
    public function getInvoiceSourcesByIdRange(string $invoiceSourceType, int $invoiceSourceIdFrom, int $invoiceSourceIdTo): array
    {
        if ($invoiceSourceType === Source::Order) {
            $query = sprintf(
                'select order_id from #__hikashop_order where order_id between %d and %d',
                $invoiceSourceIdFrom,
                $invoiceSourceIdTo
            );
            return $this->getSourcesByQuery($invoiceSourceType, $query);
        }
        return [];
    }

    /**
     * {@inheritdoc}
     *
     * By default, HikaShop order numbers are non-sequential random strings.
     * So getting a range is not logical. However, extensions may exist that do
     * introduce sequential order numbers in which case this query should be
     * adapted.
     *
     * @noinspection NullPointerExceptionInspection
     */
    public function getInvoiceSourcesByReferenceRange(
        string $invoiceSourceType,
        string $invoiceSourceReferenceFrom,
        string $invoiceSourceReferenceTo
    ): array
    {
        if ($invoiceSourceType === Source::Order) {
            $query = sprintf(
                "select order_id from #__hikashop_order where order_number between '%s' and '%s'",
                $this->getDb()->escape($invoiceSourceReferenceFrom),
                $this->getDb()->escape($invoiceSourceReferenceTo)
            );
            return $this->getSourcesByQuery($invoiceSourceType, $query);
        }
        return [];
    }

    public function getInvoiceSourcesByDateRange(string $invoiceSourceType, DateTimeInterface $dateFrom, DateTimeInterface $dateTo): array
    {
        if ($invoiceSourceType === Source::Order) {
            $query = sprintf(
                'select order_id from #__hikashop_order where order_modified between %u and %u',
                $dateFrom->getTimestamp(),
                $dateTo->getTimestamp()
            );
            return $this->getSourcesByQuery($invoiceSourceType, $query);
        }
        return [];
    }
}
