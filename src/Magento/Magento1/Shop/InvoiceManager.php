<?php
namespace Siel\Acumulus\Magento\Magento1\Shop;

use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Magento\Shop\InvoiceManager as BaseInvoiceManager;

class InvoiceManager extends BaseInvoiceManager
{
    /**
     * {@inheritdoc}
     *
     * @param string $invoiceSourceType
     *
     * @return \Mage_Sales_Model_Abstract|false
     */
    protected function getInvoiceSourceTypeModel($invoiceSourceType)
    {
        return $invoiceSourceType == Source::Order ? \Mage::getModel('sales/order') : \Mage::getModel('sales/order_creditmemo');
    }

    /**
     * {@inheritdoc}
     */
    protected function dispatchEvent($name, array $parameters)
    {
        \Mage::dispatchEvent($name, $parameters);
    }
}
