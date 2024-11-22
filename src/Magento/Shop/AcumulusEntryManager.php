<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Shop;

use Exception;
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

    public function getByEntryId(?int $entryId): AcumulusEntry|array|null
    {
        /** @var \Siel\AcumulusMa2\Model\Entry[] $result */
        $result = $this->getResourceCollection()
            ->addFieldToFilter('entry_id', $entryId)
            ->getItems();
        return $this->convertDbResultToAcumulusEntries($result);
    }

    public function getByInvoiceSource(Source $invoiceSource, bool $ignoreLock = true): ?BaseAcumulusEntry
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
     *
     * @throws \Exception
     */
    protected function insert(Source $invoiceSource, ?int $entryId, ?string $token, int|string $created): bool
    {
        try {
            $record = $this->getModel()
                ->setEntryId($entryId)
                ->setToken($token)
                ->setSourceType($invoiceSource->getType())
                ->setSourceId($invoiceSource->getId())
                ->setUpdated($created);
            $this->getResourceModel()->save($record);
        } catch (Exception $e) {
            $this->log->error(__CLASS__ . '::' . __METHOD__ . ': '. $e->getMessage());
            throw $e;
        }
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    protected function update(BaseAcumulusEntry $entry, ?int $entryId, ?string $token, int|string $updated, ?Source $invoiceSource = null): bool
    {
        /** @var \Siel\AcumulusMa2\Model\Entry $record */
        try {
            $record = $entry
                ->getRecord()
                ->setEntryId($entryId)
                ->setToken($token)
                ->setUpdated($updated);
            $this->getResourceModel()->save($record);
        } catch (Exception $e) {
            $this->log->error(__CLASS__ . '::' . __METHOD__ . ': '. $e->getMessage());
            throw $e;
        }
        return true;
    }

    /**
     * @inheritDoc
     *
     * @noinspection BadExceptionsProcessingInspection
     */
    public function delete(BaseAcumulusEntry $entry, ?Source $invoiceSource = null): bool
    {
        $result = true;
        /** @var \Siel\AcumulusMa2\Model\Entry $record */
        $record = $entry->getRecord();
        try {
            $this->getResourceModel()->delete($record);
        } catch (Exception $e) {
            // @todo: log exception?
            $result = false;
        }

        return $result;
    }


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
