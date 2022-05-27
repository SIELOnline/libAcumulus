<?php
namespace Siel\Acumulus\Joomla\Shop;

use AcumulusTableAcumulusEntry;
use DateTimeZone;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use RuntimeException;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\AcumulusEntry;
use Siel\Acumulus\Shop\AcumulusEntryManager as BaseAcumulusEntryManager;
use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;

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
    protected function newTable(): AcumulusTableAcumulusEntry
    {
        /**
         * @var bool|\AcumulusTableAcumulusEntry $table
         * @noinspection PhpDeprecationInspection : Deprecated as of J4
         */
        $table = Table::getInstance('AcumulusEntry', 'AcumulusTable');
        if ($table === false) {
            $e = new RuntimeException('AcumulusEntryManager::newTable(): table not created');
            $this->log->error($e->getMessage());
            throw $e;
        }
        return $table;
    }

    /**
     * {@inheritdoc}
     */
    public function getByEntryId(?int $entryId)
    {
        $table = $this->newTable();
        $result = $table->loadMultiple(['entry_id' => $entryId]);
        return $this->convertDbResultToAcumulusEntries($result);
    }

    /**
     * {@inheritdoc}
     */
    public function getByInvoiceSource(Source $invoiceSource, bool $ignoreLock = true): ?AcumulusEntry
    {
        $table = $this->newTable();
        /** @noinspection PhpRedundantOptionalArgumentInspection */
        $result = $table->load(['source_type' => $invoiceSource->getType(), 'source_id' => $invoiceSource->getId()], true);
        return $result ? $this->convertDbResultToAcumulusEntries($table, $ignoreLock) : null;
    }

    /**
     * {@inheritdoc}
     */
    protected function insert(Source $invoiceSource, ?int $entryId, ?string $token, $created): bool
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

    /**
     * {@inheritdoc}
     */
    protected function update(BaseAcumulusEntry $entry, ?int $entryId, ?string $token, $updated): bool
    {
        // Continue with existing table object with already loaded record.
        /** @var \AcumulusTableAcumulusEntry $table */
        $table = $entry->getRecord();
        $table->entry_id = $entryId;
        $table->token = $token;
        $table->updated = $updated;
        return $table->store(true);
    }

    public function delete(BaseAcumulusEntry $entry): bool
    {
        /** @var \AcumulusTableAcumulusEntry $table */
        $table = $entry->getRecord();
        return $table->delete();
    }

    /**
     * {@inheritdoc}
     */
    protected function sqlNow()
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $tz = new DateTimeZone(Factory::getApplication()->get('offset'));
        $date = new Date();
        $date->setTimezone($tz);
        return $date->toSql(true);
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
