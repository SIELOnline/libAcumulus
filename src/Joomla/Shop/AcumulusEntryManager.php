<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\Shop;

use DateTimeZone;
use Exception;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use RuntimeException;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\AcumulusEntry;
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
 * via the Joomla table classes which take care of sanitizing.
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

    public function getByEntryId(?int $entryId): AcumulusEntry|array|null
    {
        $table = $this->newTable();
        $result = $table->loadMultiple(['entry_id' => $entryId]);
        return $this->convertDbResultToAcumulusEntries($result);
    }

    public function getByInvoiceSource(Source $invoiceSource, bool $ignoreLock = true): ?AcumulusEntry
    {
        $table = $this->newTable();
        // If we do not set it to null, it will remain undefined when we want to update
        // or delete it later.
        $table->id = null;
        $result = $table->load(['source_type' => $invoiceSource->getType(), 'source_id' => $invoiceSource->getId()], true);
        return $result ? $this->convertDbResultToAcumulusEntries($table, $ignoreLock) : null;
    }

    protected function insert(Source $invoiceSource, ?int $entryId, ?string $token, int|string $created): bool
    {
        // Start with new table class to not overwrite any loaded record.
        $table = $this->newTable();
        $table->entry_id = $entryId;
        $table->token = $token;
        $table->source_type = $invoiceSource->getType();
        $table->source_id = $invoiceSource->getId();
        $table->created = $created;
        $table->updated = $created;
        return $table->store(true);
    }

    protected function update(
        BaseAcumulusEntry $entry,
        ?int $entryId,
        ?string $token,
        int|string $updated,
        ?Source $invoiceSource = null
    ): bool {
        // Continue with existing table object with already loaded record.
        /** @var AcumulusEntryTable $table */
        $table = $entry->getRecord();
        $table->entry_id = $entryId;
        $table->token = $token;
        $table->updated = $updated;
        return $table->store(true);
    }

    public function delete(BaseAcumulusEntry $entry, ?Source $invoiceSource = null): bool
    {
        /** @var AcumulusEntryTable $table */
        $table = $entry->getRecord();
        return $table->delete();
    }

    /**
     * @throws \DateInvalidTimeZoneException
     * @throws \Exception
     */
    protected function sqlNow(): int|string
    {
        /** @noinspection NullPointerExceptionInspection */
        return (new Date('now', new DateTimeZone(Factory::getApplication()->get('offset'))))->toSql(true);
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
