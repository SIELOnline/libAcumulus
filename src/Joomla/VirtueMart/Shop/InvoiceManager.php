<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\VirtueMart\Shop;

use DateTimeInterface;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Joomla\Shop\InvoiceManager as BaseInvoiceManager;

use function count;
use function sprintf;

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
    public function getInvoiceSourcesByIdRange(string $sourceType, int $idFrom, int $idTo): array
    {
        if ($sourceType === Source::Order) {
            $query = sprintf(
                'select virtuemart_order_id from #__virtuemart_orders where virtuemart_order_id between %d and %d',
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
     * By default, VirtueMart order numbers are non-sequential random strings.
     * So getting a range is not logical. However, extensions exists that do
     * introduce sequential order numbers, E.g:
     * http://extensions.joomla.org/profile/extension/extension-specific/virtuemart-extensions/human-readable-order-numbers
     */
    public function getInvoiceSourcesByReferenceRange(string $sourceType, string $referenceFrom, string $referenceTo, bool $fallbackToId): array
    {
        $result = [];
        if ($sourceType === Source::Order) {
            if (ctype_digit($referenceFrom) && ctype_digit($referenceTo)) {
                $referenceFrom = sprintf('%d', $referenceFrom);
                $referenceTo = sprintf('%d', $referenceTo);
            } else {
                $referenceFrom = sprintf("'%s'", $this->getDb()->escape($referenceFrom));
                $referenceTo = sprintf("'%s'", $this->getDb()->escape($referenceTo));
            }
            $query = sprintf(
                'select virtuemart_order_id from #__virtuemart_orders where order_number between %s and %s',
                $referenceFrom,
                $referenceTo
            );
            $result = $this->getSourcesByQuery($sourceType, $query);
        }
        return count($result) > 0 ? $result : parent::getInvoiceSourcesByReferenceRange($sourceType, $referenceFrom, $referenceTo, $fallbackToId);
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
