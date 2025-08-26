<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Shop;

use DateTimeInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo\Collection as CreditmemoCollection;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Magento\Helpers\Registry;
use Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;

use function count;

/**
 * Implements the Magento specific invoice manager.
 *
 * SECURITY REMARKS
 * ----------------
 * In Magento saving and querying orders or credit memos is done via the Magento DB API,
 * which takes care of sanitizing.
 */
class InvoiceManager extends BaseInvoiceManager
{
    public function getInvoiceSourcesByIdRange(string $sourceType, int $idFrom, int $idTo): array
    {
        $field = 'entity_id';
        $condition = [
            'from' => $idFrom,
            'to' => $idTo,
        ];
        return $this->getByCondition($sourceType, $field, $condition);
    }

    public function getInvoiceSourcesByReferenceRange(string $sourceType, string $referenceFrom, string $referenceTo, bool $fallbackToId): array
    {
        $field = 'increment_id';
        $condition = [
            'from' => $referenceFrom,
            'to' => $referenceTo,
        ];
        $result = $this->getByCondition($sourceType, $field, $condition);
        return count($result) > 0 ? $result : parent::getInvoiceSourcesByReferenceRange($sourceType, $referenceFrom, $referenceTo, $fallbackToId);
    }

    public function getInvoiceSourcesByDateRange(string $sourceType, DateTimeInterface $dateFrom, DateTimeInterface $dateTo): array
    {
        $field = 'updated_at';
        $condition = [
            'from' => $this->getSqlDate($dateFrom),
            'to' => $this->getSqlDate($dateTo)
        ];
        return $this->getByCondition($sourceType, $field, $condition);
    }

    /**
     * Helper method that executes a query to retrieve a list of invoice source
     * ids and returns a list of invoice sources for these ids.
     *
     * @param string $invoiceSourceType
     * @param string|string[] $field
     * @param int|array|string $condition
     *
     * @return \Siel\Acumulus\Invoice\Source[]
     *   A non-keyed array with invoice Sources.
     */
    protected function getByCondition(string $invoiceSourceType, array|string $field, int|array|string $condition): array
    {
        $items = $this
            ->createInvoiceSourceTypeCollection($invoiceSourceType)
            ->addFieldToFilter($field, $condition)
            ->getItems();
        return $this->getSourcesByIdsOrSources($invoiceSourceType, $items);
    }

    /**
     * Returns a Collection cass that can return filtered lists of objects of
     * the type of the given invoice source (Orders or CreditMemos).
     */
    protected function createInvoiceSourceTypeCollection(string $invoiceSourceType): AbstractCollection
    {
        return Registry::getInstance()->create($invoiceSourceType === Source::Order
            ? OrderCollection::class
            : CreditmemoCollection::class);
    }

}
