<?php
namespace Siel\Acumulus\Magento\Shop;

use Exception;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Magento\Helpers\Registry;
use Siel\Acumulus\Shop\AcumulusEntryManager as BaseAcumulusEntryManager;
use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;
use Siel\AcumulusMa2\Model\Entry;
use Siel\AcumulusMa2\Model\ResourceModel\Entry\Collection;

/**
 * Implements the Magento specific acumulus entry model class.
 *
 * This class is a bridge between the Acumulus library and the way that Magento
 * models are modelled.
 *
 * SECURITY REMARKS
 * ----------------
 * In Magento saving and querying acumulus entries is done via the Magento DB
 * API which takes care of sanitizing.
 */
class AcumulusEntryManager extends BaseAcumulusEntryManager
{
    /** @var \Siel\AcumulusMa2\Model\Entry */
    protected $model;

    /** @var \Siel\AcumulusMa2\Model\ResourceModel\Entry */
    protected $resourceModel;

    /** @var \Siel\AcumulusMa2\Model\ResourceModel\Entry\Collection */
    protected $resourceCollection;

    /**
     * {@inheritdoc}
     */
    public function __construct(Container $container, Log $log)
    {
        parent::__construct($container, $log);
        $this->model = Registry::getInstance()->get(Entry::class);
        $this->resourceModel = Registry::getInstance()->get(\Siel\AcumulusMa2\Model\ResourceModel\Entry::class);
        $this->resourceCollection = Registry::getInstance()->get(Collection::class);
    }

    protected function getModel(): Entry
    {
        return $this->model;
    }

    protected function getResourceModel(): \Siel\AcumulusMa2\Model\ResourceModel\Entry
    {
        return $this->resourceModel;
    }

    public function getResourceCollection(): Collection
    {
        return $this->resourceCollection;
    }

    /**
     * {@inheritdoc}
     */
    public function getByEntryId($entryId)
    {
        /** @var \Siel\AcumulusMa2\Model\Entry[] $result */
        $result = $this->getResourceCollection()
           ->addFieldToFilter('entry_id', $entryId)
           ->getItems();
        return $this->convertDbResultToAcumulusEntries($result);
    }

    /**
     * {@inheritdoc}
     */
    public function getByInvoiceSource(Source $invoiceSource, $ignoreLock = true)
    {
        /** @var \Siel\AcumulusMa2\Model\Entry $result */
        $result = $this->getResourceCollection()
            ->addFieldToFilter('source_type', $invoiceSource->getType())
            ->addFieldToFilter('source_id', $invoiceSource->getId())
            ->getItems();
        return $this->convertDbResultToAcumulusEntries($result, $ignoreLock);
    }

    /**
     * {@inheritdoc}
     */
    protected function insert(Source $invoiceSource, $entryId, $token, $created)
    {
        $record = $this->getModel()
            ->setEntryId($entryId)
            ->setToken($token)
            ->setSourceType($invoiceSource->getType())
            ->setSourceId($invoiceSource->getId())
            ->setUpdated($created);
        /** @noinspection PhpUnhandledExceptionInspection */
        return $this->getResourceModel()->save($record);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    protected function update(BaseAcumulusEntry $entry, $entryId, $token, $updated)
    {
        /** @var \Siel\AcumulusMa2\Model\Entry $record */
        $record = $entry
            ->getRecord()
            ->setEntryId($entryId)
            ->setToken($token)
            ->setUpdated($updated);
        return $this->getResourceModel()->save($record);
    }

    /**
     * @inheritDoc
     */
    public function delete(BaseAcumulusEntry $entry): bool
    {
        $result = true;
        /** @var \Siel\AcumulusMa2\Model\Entry $record */
        $record = $entry->getRecord();
        try {
            $this->getResourceModel()->delete($record);
        } catch (Exception $e) {
            $result = false;
        }

        return $result;
    }


    /**
     * {@inheritdoc}
     */
    protected function sqlNow(): int
    {
        return time();
    }

    /**
     * {@inheritdoc}
     *
     * Magento has separate installation scripts, so nothing has to be done
     * here.
     */
    public function install(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * Magento has separate uninstallation scripts, so nothing has to be done
     * here.
     */
    public function uninstall(): bool
    {
        return true;
    }
}
