<?php
namespace Siel\Acumulus\Magento\Magento2\Shop;

use Siel\Acumulus\Invoice\Source as Source;
use Siel\Acumulus\Magento\Magento2\Helpers\Registry;
use Siel\Acumulus\Magento\Shop\InvoiceManager as BaseInvoiceManager;

class InvoiceManager extends BaseInvoiceManager
{
    /**
     * {@inheritdoc}
     *
     * @param string $invoiceSourceType
     *
     * @return \Magento\Sales\Model\AbstractModel
     */
    protected function getInvoiceSourceTypeModel($invoiceSourceType)
    {
        return Registry::getInstance()->get($invoiceSourceType == Source::Order
            ? 'Magento\Sales\Model\Order'
            : 'Magento\Sales\Model\Order\Creditmemo');
    }

    /**
     * {@inheritdoc}
     */
    protected function dispatchEvent($name, array $parameters, array $refParameters = array())
    {
        foreach ($refParameters as $name => $parameter) {
            $refParameters[$name] += $parameter;
        }
        /** @var \Magento\Framework\Event\ManagerInterface $dispatcher */
        $dispatcher = Registry::getInstance()->get('Magento\Framework\Event\ManagerInterface');
        $dispatcher->dispatch($name, $refParameters);
    }
}
