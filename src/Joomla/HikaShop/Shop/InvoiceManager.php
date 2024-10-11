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

use function count;

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
    public function getInvoiceSourcesByIdRange(string $sourceType, int $idFrom, int $idTo): array
    {
        if ($sourceType === Source::Order) {
            $query = sprintf(
                'select order_id from #__hikashop_order where order_id between %d and %d',
                $idFrom,
                $idTo
            );
            return $this->getSourcesByQuery($sourceType, $query);
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
     * @noinspection PhpUndefinedMethodInspection {@See \Joomla\Database\DatabaseInterface::escape()}
     */
    public function getInvoiceSourcesByReferenceRange(
        string $sourceType,
        string $referenceFrom,
        string $referenceTo,
        bool $fallbackToId
    ): array {
        $result = [];
        if ($sourceType === Source::Order) {
            $query = sprintf(
                "select order_id from #__hikashop_order where order_number between '%s' and '%s'",
                $this->getDb()->escape($referenceFrom),
                $this->getDb()->escape($referenceTo)
            );
            $result = $this->getSourcesByQuery($sourceType, $query);
        }
        return count($result) > 0 ? $result : parent::getInvoiceSourcesByReferenceRange(
            $sourceType,
            $referenceFrom,
            $referenceTo,
            $fallbackToId
        );
    }

    public function getInvoiceSourcesByDateRange(string $sourceType, DateTimeInterface $dateFrom, DateTimeInterface $dateTo): array
    {
        if ($sourceType === Source::Order) {
            $query = sprintf(
                'select order_id from #__hikashop_order where order_modified between %u and %u',
                $dateFrom->getTimestamp(),
                $dateTo->getTimestamp()
            );
            return $this->getSourcesByQuery($sourceType, $query);
        }
        return [];
    }
}
