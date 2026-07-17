<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Shop;

use DateTimeInterface;
use Exception;
use Siel\Acumulus\Api;
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
 * In Magento saving and querying acumulus entries is done via the Magento DB API,
 * which takes care of sanitising.
 */
class AcumulusEntryManager extends BaseAcumulusEntryManager
{
    protected function getModel(): Entry
    {
        return Registry::getInstance()->create(Entry::class);
    }

    protected function getResourceModel(): \Siel\AcumulusMa2\Model\ResourceModel\Entry
    {
        return Registry::getInstance()->get(\Siel\AcumulusMa2\Model\ResourceModel\Entry::class);
    }

    public function getResourceCollection(): Collection
    {
        return Registry::getInstance()->create(Collection::class);
    }

    public function getByEntryId(int $entryId): ?AcumulusEntry
    {
        /** @var \Siel\AcumulusMa2\Model\Entry $result */
        $result = $this->getResourceCollection()
            ->addFieldToFilter('entry_id', $entryId)
            ->getFirstItem();
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->convertDbResultToAcumulusEntry($result);
    }

    public function getByInvoiceSource(Source $invoiceSource, bool $ignoreLock = true): ?AcumulusEntry
    {
        /** @var \Siel\AcumulusMa2\Model\Entry $result */
        $result = $this->getResourceCollection()
            ->addFieldToFilter('source_type', $invoiceSource->getType())
            ->addFieldToFilter('source_id', $invoiceSource->getId())
            ->getFirstItem();
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->convertDbResultToAcumulusEntry($result, $ignoreLock);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    protected function insert(Source $invoiceSource, ?int $entryId, ?string $token, DateTimeInterface $created): bool
    {
        try {
            $timestamp = $created->format(Api::Format_TimeStamp);
            $record = $this->getModel()
                ->setEntryId($entryId)
                ->setToken($token)
                ->setSourceType($invoiceSource->getType())
                ->setSourceId($invoiceSource->getId())
                ->setCreated($timestamp)
                ->setUpdated($timestamp);
            $this->getResourceModel()->save($record);
        } catch (Exception $e) {
            $this->log->error(__METHOD__ . ': '. $e->getMessage());
            throw $e;
        }
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    protected function update(BaseAcumulusEntry $entry, ?int $entryId, ?string $token, DateTimeInterface $updated): bool
    {
        /** @var \Siel\AcumulusMa2\Model\Entry $record */
        try {
            $record = $entry
                ->getRecord()
                ->setEntryId($entryId)
                ->setToken($token)
                ->setUpdated($updated->format(Api::Format_TimeStamp));
            $this->getResourceModel()->save($record);
        } catch (Exception $e) {
            $this->log->error(__METHOD__ . ': '. $e->getMessage());
            throw $e;
        }
        return true;
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
            $this->log->error(__METHOD__ . ': '. $e->getMessage());
            $result = false;
        }
        return $result;
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
