<?php
namespace Siel\Acumulus\Joomla\VirtueMart\Shop;

use DateTime;
use Siel\Acumulus\Invoice\Source as Source;
use Siel\Acumulus\Joomla\Shop\InvoiceManager as BaseInvoiceManager;

/**
 * {@inheritdoc}
 *
 * This override provides the VirtueMart specific queries.
 */
class InvoiceManager extends BaseInvoiceManager
{
    /**
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByIdRange($invoiceSourceType, $InvoiceSourceIdFrom, $InvoiceSourceIdTo)
    {
        if ($invoiceSourceType === Source::Order) {
            $query = sprintf("select virtuemart_order_id
			from #__virtuemart_orders
			where virtuemart_order_id between %d and %d",
                $InvoiceSourceIdFrom, $InvoiceSourceIdTo);
            return $this->getSourcesByQuery($invoiceSourceType, $query);
        }
        return array();
    }

    /**
     * {@inheritdoc}
     *
     * By default, VirtueMart order numbers are non sequential random strings.
     * So getting a range is not logical. However, extensions exists that do
     * introduce sequential order numbers, E.g:
     * http://extensions.joomla.org/profile/extension/extension-specific/virtuemart-extensions/human-readable-order-numbers
     */
    public function getInvoiceSourcesByReferenceRange($invoiceSourceType, $InvoiceSourceReferenceFrom, $InvoiceSourceReferenceTo)
    {
        if ($invoiceSourceType === Source::Order) {
            $query = sprintf("select virtuemart_order_id
			from #__virtuemart_orders
			where order_number between '%s' and '%s'",
                $this->getDb()->escape($InvoiceSourceReferenceFrom),
                $this->getDb()->escape($InvoiceSourceReferenceTo)
            );
            return $this->getSourcesByQuery($invoiceSourceType, $query);
        }
        return array();
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByDateRange($invoiceSourceType, DateTime $dateFrom, DateTime $dateTo)
    {
        if ($invoiceSourceType === Source::Order) {
            $dateFrom = $this->getSqlDate($dateFrom);
            $dateTo = $this->getSqlDate($dateTo);
            $query = sprintf("select virtuemart_order_id
			from #__virtuemart_orders
			where modified_on between '%s' and '%s'",
                $this->toSql($dateFrom), $this->toSql($dateTo));
            return $this->getSourcesByQuery($invoiceSourceType, $query);
        }
        return array();
    }
}
