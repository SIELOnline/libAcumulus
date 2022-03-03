<?php
namespace Siel\Acumulus\Magento\Shop;

use Siel\Acumulus\Invoice\Result;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Magento\Helpers\Registry;
use Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;

/**
 * Implements the Magento specific invoice manager.
 *
 * SECURITY REMARKS
 * ----------------
 * In Magento saving and querying orders or credit memos is done via the Magento
 * DB API which takes care of sanitizing.
 */
class InvoiceManager extends BaseInvoiceManager
{
    /**
     * @var \Magento\Framework\DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByIdRange($invoiceSourceType, $InvoiceSourceIdFrom, $InvoiceSourceIdTo)
    {
        $field = 'entity_id';
        $condition = [
            'from' => $InvoiceSourceIdFrom,
            'to' => $InvoiceSourceIdTo,
        ];
        return $this->getByCondition($invoiceSourceType, $field, $condition);
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByReferenceRange($invoiceSourceType, $invoiceSourceReferenceFrom, $invoiceSourceReferenceTo)
    {
        $field = 'increment_id';
        $condition = [
            'from' => $invoiceSourceReferenceFrom,
            'to' => $invoiceSourceReferenceTo,
        ];
        return $this->getByCondition($invoiceSourceType, $field, $condition);
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByDateRange($invoiceSourceType, \DateTime $dateFrom, \DateTime $dateTo)
    {
        $dateFrom = $this->getSqlDate($dateFrom);
        $dateTo = $this->getSqlDate($dateTo);
        $field = 'updated_at';
        $condition = ['from' => $dateFrom, 'to' => $dateTo];
        return $this->getByCondition($invoiceSourceType, $field, $condition);
    }

    /**
     * Helper method that executes a query to retrieve a list of invoice source
     * ids and returns a list of invoice sources for these ids.
     *
     * @param string $invoiceSourceType
     * @param string|string[] $field
     * @param int|string|array $condition
     *
     * @return \Siel\Acumulus\Invoice\Source[]
     *   A non keyed array with invoice Sources.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getByCondition($invoiceSourceType, $field, $condition)
    {
        /** @var  \Varien_Object[]\Magento\Framework\DataObject[] $items */
        $items = $this
            ->getInvoiceSourceTypeModel($invoiceSourceType)
            ->getResourceCollection()
            ->addFieldToFilter($field, $condition)
            ->getItems();

        return $this->getSourcesByIdsOrSources($invoiceSourceType, $items);
    }

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
     *
     * This Magento override dispatches the 'acumulus_invoice_created' event.
     */
    protected function triggerInvoiceCreated(array &$invoice, Source $invoiceSource, Result $localResult)
    {
        $this->dispatchEvent('acumulus_invoice_created', ['invoice' => &$invoice, 'source' => $invoiceSource, 'localResult' => $localResult]);
    }

    /**
     * {@inheritdoc}
     *
     * This Magento override dispatches the 'acumulus_invoice_completed' event.
     */
    protected function triggerInvoiceSendBefore(array &$invoice, Source $invoiceSource, Result $localResult)
    {
        $this->dispatchEvent('acumulus_invoice_send_before', ['invoice' => &$invoice, 'source' => $invoiceSource, 'localResult' => $localResult]);
    }

    /**
     * {@inheritdoc}
     *
     * This Magento override dispatches the 'acumulus_invoice_sent' event.
     */
    protected function triggerInvoiceSendAfter(array $invoice, Source $invoiceSource, Result $result)
    {
        $this->dispatchEvent('acumulus_invoice_send_after', ['invoice' => $invoice, 'source' => $invoiceSource, 'result' => $result]);
    }

    /**
     * @return \Magento\Framework\DataObjectFactory
     */
    protected function getDataObjectFactory()
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
