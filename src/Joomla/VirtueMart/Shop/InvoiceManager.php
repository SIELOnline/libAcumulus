<?php
/**
 * @noinspection SqlNoDataSourceInspection
 * @noinspection SqlDialectInspection
 */

namespace Siel\Acumulus\Joomla\VirtueMart\Shop;

use DateTime;
use Siel\Acumulus\Invoice\Source as Source;
use Siel\Acumulus\Joomla\Shop\InvoiceManager as BaseInvoiceManager;

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
    /**
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByIdRange($invoiceSourceType, $InvoiceSourceIdFrom, $InvoiceSourceIdTo): array
    {
        if ($invoiceSourceType === Source::Order) {
            $query = sprintf(
                "select virtuemart_order_id from #__virtuemart_orders where virtuemart_order_id between %d and %d",
                $InvoiceSourceIdFrom,
                $InvoiceSourceIdTo
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
    public function getInvoiceSourcesByReferenceRange($invoiceSourceType, $invoiceSourceReferenceFrom, $invoiceSourceReferenceTo): array
    {
        if ($invoiceSourceType === Source::Order) {
            if (ctype_digit($invoiceSourceReferenceFrom) && ctype_digit($invoiceSourceReferenceTo)) {
                $from = sprintf('%d', $invoiceSourceReferenceFrom);
                $to = sprintf('%d', $invoiceSourceReferenceTo);
            } else {
                $from = sprintf("'%s'", $this->getDb()->escape($invoiceSourceReferenceFrom));
                $to = sprintf("'%s'", $this->getDb()->escape($invoiceSourceReferenceTo));
            }
            $query = sprintf(
                'select virtuemart_order_id from #__virtuemart_orders where order_number between %s and %s',
                $from,
                $to
            );
            return $this->getSourcesByQuery($invoiceSourceType, $query);
        }
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByDateRange($invoiceSourceType, DateTime $dateFrom, DateTime $dateTo): array
    {
        if ($invoiceSourceType === Source::Order) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $query = sprintf(
                "select virtuemart_order_id from #__virtuemart_orders where modified_on between '%s' and '%s'",
                $this->toSql($this->getSqlDate($dateFrom)),
                $this->toSql($this->getSqlDate($dateTo))
            );
            return $this->getSourcesByQuery($invoiceSourceType, $query);
        }
        return [];
    }
}
