<?php

namespace Siel\Acumulus\Magento\Magento2\Shop;

use Siel\Acumulus\Invoice\Source as Source;
use Siel\Acumulus\Magento\Magento2\Helpers\Registry;
use Siel\Acumulus\Magento\Shop\InvoiceManager as BaseInvoiceManager;

class InvoiceManager extends BaseInvoiceManager
{
    /**
     * @var \Magento\Framework\DataObjectFactory
     */
    private $dataObjectFactory;

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
     * @return \Magento\Framework\DataObjectFactory
     */
    public function getDataObjectFactory()
    {
        if ($this->dataObjectFactory === null) {
            $this->dataObjectFactory = Registry::getInstance()->get('\Magento\Framework\DataObjectFactory');
        }
        return $this->dataObjectFactory;
    }

    /**
     * {@inheritdoc}
     */
    protected function dispatchEvent($name, array $parameters)
    {
        /** @var \Magento\Framework\Event\ManagerInterface $dispatcher */
        $dispatcher = Registry::getInstance()->get('Magento\Framework\Event\ManagerInterface');
        $dispatcher->dispatch($name, $parameters);
    }
}
