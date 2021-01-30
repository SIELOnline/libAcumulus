<?php
namespace Siel\Acumulus\Joomla\HikaShop\Shop;

use DateTime;
use Siel\Acumulus\Invoice\Source as Source;
use Siel\Acumulus\Joomla\Shop\InvoiceManager as BaseInvoiceManager;

/**
 * {@inheritdoc}
 *
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
    /**
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByIdRange($invoiceSourceType, $InvoiceSourceIdFrom, $InvoiceSourceIdTo)
    {
        if ($invoiceSourceType === Source::Order) {
            $query = sprintf(
                "select order_id from #__hikashop_order where order_id between %d and %d",
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
     * By default, HikaShop order numbers are non sequential random strings.
     * So getting a range is not logical. However, extensions may exists that do
     * introduce sequential order numbers in which case this query should be
     * adapted.
     */
    public function getInvoiceSourcesByReferenceRange($invoiceSourceType, $invoiceSourceReferenceFrom, $invoiceSourceReferenceTo)
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

    /**
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByDateRange($invoiceSourceType, DateTime $dateFrom, DateTime $dateTo)
    {
        if ($invoiceSourceType === Source::Order) {
            $query = sprintf(
                "select order_id from #__hikashop_order where order_modified between %u and %u",
                $dateFrom->getTimestamp(),
                $dateTo->getTimestamp()
            );
            return $this->getSourcesByQuery($invoiceSourceType, $query);
        }
        return [];
    }
}
