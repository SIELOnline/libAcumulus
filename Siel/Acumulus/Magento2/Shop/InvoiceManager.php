<?php
namespace Siel\Acumulus\Magento2\Shop;

use DateTime;
use Siel\Acumulus\Invoice\Source as Source;
use Siel\Acumulus\Magento2\Helpers\Registry;
use Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;

class InvoiceManager extends BaseInvoiceManager
{
    /**
     * Returns a Magento model for the given source type.
     *
     * @param $invoiceSourceType
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
    public function getInvoiceSourcesByIdRange(
        $invoiceSourceType,
        $InvoiceSourceIdFrom,
        $InvoiceSourceIdTo
    ) {
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
    public function getInvoiceSourcesByReferenceRange(
        $invoiceSourceType,
        $InvoiceSourceReferenceFrom,
        $InvoiceSourceReferenceTo
    ) {
        $field = 'increment_id';
        $condition = array(
            'from' => $InvoiceSourceReferenceFrom,
            'to' => $InvoiceSourceReferenceTo,
        );
        return $this->getByCondition($invoiceSourceType, $field, $condition);
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByDateRange(
        $invoiceSourceType,
        DateTime $dateFrom,
        DateTime $dateTo
    ) {
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
     * @return \Siel\Acumulus\Magento\Invoice\Source[]
     *   A non keyed array with invoice Sources.
     */
    protected function getByCondition($invoiceSourceType, $field, $condition)
    {
        /** @var \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection $collection */
        $collection = $this
            ->getInvoiceSourceTypeModel($invoiceSourceType)
            ->getResourceCollection();
        $items = $collection
            ->addFieldToFilter($field, $condition)
            ->getItems();

        // @todo: replace with getSourcesByIds
        return $this->getSourcesByIdsOrSources($invoiceSourceType, $items);
    }

    /**
     * {@inheritdoc}
     *
     * This Magento override dispatches the 'acumulus_invoice_created' event.
     */
    protected function triggerInvoiceCreated(
        array &$invoice,
        Source $invoiceSource
    ) {
        $this->dispatchEvent('acumulus_invoice_created', array(
                'invoice' => &$invoice,
                'source' => $invoiceSource,
            )
        );
    }

    /**
     * {@inheritdoc}
     *
     * This Magento override dispatches the 'acumulus_invoice_completed' event.
     */
    protected function triggerInvoiceCompleted(
        array &$invoice,
        Source $invoiceSource
    ) {
        $this->dispatchEvent('acumulus_invoice_completed', array(
            'invoice' => &$invoice,
            'source' => $invoiceSource,
        ));
    }

    /**
     * {@inheritdoc}
     *
     * This Magento override dispatches the 'acumulus_invoice_sent' event.
     */
    protected function triggerInvoiceSent(
        array $invoice,
        Source $invoiceSource,
        array $result
    ) {
        $this->dispatchEvent('acumulus_invoice_sent', array(
            'invoice' => $invoice,
            'source' => $invoiceSource,
            'result' => $result,
        ));
    }

    /**
     * Dispatches an event.
     *
     * @param string $name
     * @param array $parameters
     *
     * @return void
     */
    protected function dispatchEvent($name, array $parameters)
    {
        /** @var \Magento\Framework\Event\ManagerInterface $dispatcher */
        $dispatcher = Registry::getInstance()->get('Magento\Framework\Event\ManagerInterface');
        $dispatcher->dispatch($name, $parameters);
    }
}
