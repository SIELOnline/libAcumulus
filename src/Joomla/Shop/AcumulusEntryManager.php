<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\Shop;

use DateTimeInterface;
use DateTimeZone;
use Exception;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use RuntimeException;
use Siel\Acumulus\Api;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\AcumulusEntryManager as BaseAcumulusEntryManager;
use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;
use Siel\Joomla\Component\Acumulus\Administrator\Extension\AcumulusComponent;
use Siel\Joomla\Component\Acumulus\Administrator\Table\AcumulusEntryTable;

/**
 * Implements the VirtueMart specific acumulus entry model class.
 *
 * SECURITY REMARKS
 * ----------------
 * In Joomla (VirtueMart/HikaShop) saving and querying acumulus entries is done
 * via the Joomla table classes which take care of sanitising.
 */
class AcumulusEntryManager extends BaseAcumulusEntryManager
{
    /**
     * @throws \Exception
     */
    private function getAcumulusComponent(): AcumulusComponent
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        /** @noinspection NullPointerExceptionInspection */
        return Factory::getApplication()->bootComponent('acumulus');
    }

    protected function newTable(): AcumulusEntryTable
    {
        try {
            $table = $this->getAcumulusComponent()->getAcumulusEntryTable();
        } catch (Exception) {
            $e = new RuntimeException('AcumulusEntryManager::newTable(): table not created');
            $this->log->error($e->getMessage());
            throw $e;
        }
        return $table;
    }

    public function getByEntryId(int $entryId): ?AcumulusEntry
    {
        $table = $this->newTable();
        $result = $table->load(['entry_id' => $entryId]);
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $result ? $this->convertDbResultToAcumulusEntry($table) : null;
    }

    public function getByInvoiceSource(Source $invoiceSource, bool $ignoreLock = true): ?AcumulusEntry
    {
        $table = $this->newTable();
        // If we do not set it to null, it will remain undefined when we want to update
        // or delete it later.
        $table->id = null;
        $result = $table->load(['source_type' => $invoiceSource->getType(), 'source_id' => $invoiceSource->getId()], true);
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $result ? $this->convertDbResultToAcumulusEntry($table, $ignoreLock) : null;
    }

    /**
     * @throws \DateInvalidTimeZoneException
     */
    protected function insert(Source $invoiceSource, ?int $entryId, ?string $token, DateTimeInterface $created): bool
    {
        $timestamp = $this->toSql($created);
        // Start with new table class to not overwrite any loaded record.
        $table = $this->newTable();
        $table->entry_id = $entryId;
        $table->token = $token;
        $table->source_type = $invoiceSource->getType();
        $table->source_id = $invoiceSource->getId();
        $table->created = $timestamp;
        $table->updated = $timestamp;
        return $table->store(true);
    }

    /**
     * @throws \DateInvalidTimeZoneException
     */
    protected function update(BaseAcumulusEntry $entry, ?int $entryId, ?string $token, DateTimeInterface $updated): bool
    {
        $timestamp = $this->toSql($updated);
        // Continue with existing table object with already loaded record.
        /** @var AcumulusEntryTable $table */
        $table = $entry->getRecord();
        $table->entry_id = $entryId;
        $table->token = $token;
        $table->updated = $timestamp;
        return $table->store(true);
    }

    public function delete(BaseAcumulusEntry $entry): bool
    {
        /** @var AcumulusEntryTable $table */
        $table = $entry->getRecord();
        return $table->delete();
    }

    /**
     * @throws \DateInvalidTimeZoneException
     */
    protected function toSql(DateTimeInterface $date): string
    {
        /** @noinspection NullPointerExceptionInspection, PhpUnhandledExceptionInspection */
        return (new Date($date->format(Api::Format_TimeStamp), new DateTimeZone(Factory::getApplication()->get('offset'))))->toSql(true);
    }

    /**
     * {@inheritdoc}
     *
     * Joomla has separate installation scripts, so nothing has to be done here.
     */
    public function install(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * Joomla has separate installation scripts, so nothing has to be done here.
     */
    public function uninstall(): bool
    {
        return false;
    }
}
