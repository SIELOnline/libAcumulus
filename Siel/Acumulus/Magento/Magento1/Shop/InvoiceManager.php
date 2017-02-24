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
     * Dispatches an event.
     *
     * @param string $name
     * @param array $parameters
     * @param array? $transportObjects
     *
     * @return void
     */
    protected function dispatchEvent($name, array $parameters, array $transportObjects = null)
    {
        if (!empty($transportObjects)) {
            $parameters['transport_object'] = new \Varien_Object($transportObjects);
        }
        \Mage::dispatchEvent('acumulus_invoice_sent', $parameters);
    }
}
