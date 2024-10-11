<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\VirtueMart\Shop;

use DateTimeInterface;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Joomla\Shop\InvoiceManager as BaseInvoiceManager;

use function count;

/**
 * This override provides the VirtueMart specific queries.
 *
 * SECURITY REMARKS
 * ----------------
 * VirtueMart orders are queried via self constructed queries, so this class is
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
                'select virtuemart_order_id from #__virtuemart_orders where virtuemart_order_id between %d and %d',
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
     * By default, VirtueMart order numbers are non-sequential random strings.
     * So getting a range is not logical. However, extensions exists that do
     * introduce sequential order numbers, E.g:
     * http://extensions.joomla.org/profile/extension/extension-specific/virtuemart-extensions/human-readable-order-numbers
     */
    public function getInvoiceSourcesByReferenceRange(string $sourceType, string $from, string $to, bool $fallbackToId): array
    {
        $result = [];
        if ($sourceType === Source::Order) {
            if (ctype_digit($from) && ctype_digit($to)) {
                $from = sprintf('%d', $from);
                $to = sprintf('%d', $to);
            } else {
                $from = sprintf("'%s'", $this->getDb()->escape($from));
                $to = sprintf("'%s'", $this->getDb()->escape($to));
            }
            $query = sprintf(
                'select virtuemart_order_id from #__virtuemart_orders where order_number between %s and %s',
                $from,
                $to
            );
            $result = $this->getSourcesByQuery($sourceType, $query);
        }
        return count($result) > 0 ? $result : parent::getInvoiceSourcesByReferenceRange($sourceType, $from, $to, $fallbackToId);
    }

    public function getInvoiceSourcesByDateRange(string $sourceType, DateTimeInterface $dateFrom, DateTimeInterface $dateTo): array
    {
        if ($sourceType === Source::Order) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $query = sprintf(
                "select virtuemart_order_id from #__virtuemart_orders where modified_on between '%s' and '%s'",
                $this->toSql($this->getSqlDate($dateFrom)),
                $this->toSql($this->getSqlDate($dateTo))
            );
            return $this->getSourcesByQuery($sourceType, $query);
        }
        return [];
    }
}
