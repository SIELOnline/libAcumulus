<?php
namespace Siel\Acumulus\Magento\Shop;

use DateTime;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo\Collection as CreditmemoCollection;
use Siel\Acumulus\Invoice\InvoiceAddResult;
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
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByIdRange(string $invoiceSourceType, string $InvoiceSourceIdFrom, string $InvoiceSourceIdTo): array
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
    public function getInvoiceSourcesByReferenceRange(string $invoiceSourceType, string $invoiceSourceReferenceFrom, string $invoiceSourceReferenceTo): array
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
    public function getInvoiceSourcesByDateRange(string $invoiceSourceType, DateTime $dateFrom, DateTime $dateTo): array
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
     */
    protected function getByCondition(string $invoiceSourceType, $field, $condition): array
    {
        $items = $this
            ->getInvoiceSourceTypeCollection($invoiceSourceType)
            ->addFieldToFilter($field, $condition)
            ->getItems();
        return $this->getSourcesByIdsOrSources($invoiceSourceType, $items);
    }

    /**
     * Returns a Collection cass that can return filtered lists of objects of
     * the type of the given invoice source (Orders or CreditMemos).
     *
     * @param string $invoiceSourceType
     *
     * @return \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
     */
    protected function getInvoiceSourceTypeCollection(string $invoiceSourceType): AbstractCollection
    {
        return Registry::getInstance()->get($invoiceSourceType == Source::Order
            ? OrderCollection::class
            : CreditmemoCollection::class);
    }

    /**
     * {@inheritdoc}
     *
     * This Magento override dispatches the 'acumulus_invoice_created' event.
     */
    protected function triggerInvoiceCreated(array &$invoice, Source $invoiceSource, InvoiceAddResult $localResult)
    {
        $this->dispatchEvent('acumulus_invoice_created', ['invoice' => &$invoice, 'source' => $invoiceSource, 'localResult' => $localResult]);
    }

    /**
     * {@inheritdoc}
     *
     * This Magento override dispatches the 'acumulus_invoice_completed' event.
     */
    protected function triggerInvoiceSendBefore(array &$invoice, Source $invoiceSource, InvoiceAddResult $localResult)
    {
        $this->dispatchEvent('acumulus_invoice_send_before', ['invoice' => &$invoice, 'source' => $invoiceSource, 'localResult' => $localResult]);
    }

    /**
     * {@inheritdoc}
     *
     * This Magento override dispatches the 'acumulus_invoice_sent' event.
     */
    protected function triggerInvoiceSendAfter(array $invoice, Source $invoiceSource, InvoiceAddResult $result)
    {
        $this->dispatchEvent('acumulus_invoice_send_after', ['invoice' => $invoice, 'source' => $invoiceSource, 'result' => $result]);
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
    protected function dispatchEvent(string $name, array $parameters)
    {
        /** @var \Magento\Framework\Event\ManagerInterface $dispatcher */
        $dispatcher = Registry::getInstance()->get('Magento\Framework\Event\ManagerInterface');
        $dispatcher->dispatch($name, $parameters);
    }
}
