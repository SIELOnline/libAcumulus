<?php
namespace Siel\Acumulus\Magento\Shop;

use Siel\Acumulus\Invoice\Result;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;

abstract class InvoiceManager extends BaseInvoiceManager
{
    /**
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByIdRange($invoiceSourceType, $InvoiceSourceIdFrom, $InvoiceSourceIdTo)
    {
        $field = 'entity_id';
        $condition = array(
            'from' => $InvoiceSourceIdFrom,
            'to' => $InvoiceSourceIdTo,
        );
        return $this->getByCondition($invoiceSourceType, $field, $condition);
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByReferenceRange($invoiceSourceType, $invoiceSourceReferenceFrom, $invoiceSourceReferenceTo)
    {
        $field = 'increment_id';
        $condition = array(
            'from' => $invoiceSourceReferenceFrom,
            'to' => $invoiceSourceReferenceTo,
        );
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
        $condition = array('from' => $dateFrom, 'to' => $dateTo);
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
     * Returns a Magento model for the given source type.
     *
     * @param $invoiceSourceType
     *
     * @return \Mage_Sales_Model_Abstract|\Magento\Sales\Model\AbstractModel
     */
    abstract protected function getInvoiceSourceTypeModel($invoiceSourceType);

    /**
     * {@inheritdoc}
     *
     * This Magento override dispatches the 'acumulus_invoice_created' event.
     */
    protected function triggerInvoiceCreated(array &$invoice, Source $invoiceSource, Result $localResult)
    {
        $this->dispatchEvent('acumulus_invoice_created', array('invoice' => &$invoice, 'source' => $invoiceSource, 'localResult' => $localResult));
    }

    /**
     * {@inheritdoc}
     *
     * This Magento override dispatches the 'acumulus_invoice_completed' event.
     */
    protected function triggerInvoiceSendBefore(array &$invoice, Source $invoiceSource, Result $localResult)
    {
        $this->dispatchEvent('acumulus_invoice_send_before', array('invoice' => &$invoice, 'source' => $invoiceSource, 'localResult' => $localResult));
    }

    /**
     * {@inheritdoc}
     *
     * This Magento override dispatches the 'acumulus_invoice_sent' event.
     */
    protected function triggerInvoiceSendAfter(array $invoice, Source $invoiceSource, Result $result)
    {
        $this->dispatchEvent('acumulus_invoice_send_after', array('invoice' => $invoice, 'source' => $invoiceSource, 'result' => $result));
    }

    /**
     * Dispatches an event.
     *
     * @param string $name
     *   The name of the event.
     * @param array $parameters
     *   The parameters to the event that cannot be changed.
     *
     * @return void
     */
    abstract protected function dispatchEvent($name, array $parameters);
}
