<?php
/**
 * @noinspection PhpUndefinedClassInspection Mix of OC4 and OC3 classes
 * @noinspection PhpUndefinedNamespaceInspection Mix of OC4 and OC3 classes
 */

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Shop;

use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\OpenCart\Helpers\Registry;
use Siel\Acumulus\Shop\AcumulusEntry;
use Siel\Acumulus\Shop\AcumulusEntryManager as BaseAcumulusEntryManager;

/**
 * Implements the OpenCart specific acumulus entry model class.
 *
 * SECURITY REMARKS
 * ----------------
 * In OpenCart saving and querying acumulus entries is done via self
 * constructed queries, therefore this class takes care of sanitizing itself.
 * - Numbers are cast by using numeric formatters (like %u, %d, %f) with
 *   sprintf().
 * - Strings are escaped using the escape() method of the DB driver class
 *   (unless they are hard coded).
 * Note that:
 * - $invoiceSource, $created and $updated are set in calling code, and can
 *   thus be considered trusted, but are still escaped or cast.
 * - $entryId and $token come from outside, from the Acumulus API, and must
 *   thus be handled as untrusted.
 */
class AcumulusEntryManager extends BaseAcumulusEntryManager
{
    protected string $tableName;

    public function __construct(Container $container, Log $log)
    {
        parent::__construct($container, $log);
        $this->tableName = DB_PREFIX . 'acumulus_entry';
    }

    public function getByEntryId(?int $entryId): AcumulusEntry|array|null
    {
        $operator = $entryId === null ? 'is' : '=';
        $value = $entryId === null ? 'null' : (string) $entryId;
        /** @var \stdClass $result  (documentation error in DB) */
        $result = $this->getDb()->query("SELECT * FROM `$this->tableName` WHERE entry_id $operator $value");
        return $this->convertDbResultToAcumulusEntries($result->rows);
    }

    public function getByInvoiceSource(Source $invoiceSource, bool $ignoreLock = true): ?AcumulusEntry
    {
        /** @var \stdClass $result  (documentation error in DB) */
        $result = $this->getDb()->query(sprintf(
            "SELECT * FROM `%s` WHERE source_type = '%s' AND source_id = %u",
            $this->tableName,
            $this->getDb()->escape($invoiceSource->getType()),
            $invoiceSource->getId()
        ));
        return $this->convertDbResultToAcumulusEntries($result->rows, $ignoreLock);
    }

    protected function insert(Source $invoiceSource, ?int $entryId, ?string $token, int|string $created): bool
    {
        if ($invoiceSource->getType() === Source::Order) {
            $order = $invoiceSource->getShopObject();
            $storeId = $order['store_id'];
        } else {
            $storeId = 0;
        }
        return (bool) $this->getDb()->query(sprintf(
            "INSERT INTO `%s` (store_id, entry_id, token, source_type, source_id, updated) VALUES (%u, %s, %s, '%s', %u, '%s')",
            $this->tableName,
            $storeId,
            $entryId === null ? 'null' : (string) $entryId,
            $token === null ? 'null' : ("'" . $this->getDb()->escape($token) . "'"),
            $this->getDb()->escape($invoiceSource->getType()),
            $invoiceSource->getId(),
            $this->getDb()->escape($created)
        ));
    }

    protected function update(AcumulusEntry $entry, ?int $entryId, ?string $token, int|string $updated, ?Source $invoiceSource = null): bool
    {
        $record = $entry->getRecord();
        return (bool) $this->getDb()->query(sprintf(
            "UPDATE `%s` SET entry_id = %s, token = %s, updated = '%s' WHERE id = %u",
            $this->tableName,
            $entryId === null ? 'null' : (string) $entryId,
            $token === null ? 'null' : "'" . $this->getDb()->escape($token) . "'",
            $this->getDb()->escape($updated),
            $record['id']
        ));
    }

    public function delete(AcumulusEntry $entry, ?Source $invoiceSource = null): bool
    {
        $record = $entry->getRecord();
        return (bool) $this->getDb()->query(sprintf(
            'DELETE FROM `%s` WHERE id = %u',
            $this->tableName,
            $record['id']
        ));
    }

    protected function sqlNow()
    {
        return date(Api::Format_TimeStamp);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    public function install(): bool
    {
        $queryResult = $this->getDb()->query("show tables like '$this->tableName'");
        $tableExists = !empty($queryResult->num_rows);
        if (!$tableExists) {
            // Table does not exist: create it.
            $result = $this->createTable();
        } else {
            // Table does exist: but in old or current data model?
            $columnExists = $this->getDb()->query("show columns from `$this->tableName` like 'source_type'");
            $columnExists = !empty($columnExists->num_rows);
            if (!$columnExists) {
                // Table exists but in old data model: alter table
                // Rename currently existing table.
                $oldTableName = $this->tableName . '_old';
                $result = $this->getDb()->query("ALTER TABLE `$this->tableName` RENAME `$oldTableName`;");

                // Create table in new data model.
                $result = $this->createTable() && $result;

                // Copy data from old to new table.
                // Orders only, credit slips were not supported in that version.
                // Nor did we support multi store shops (though a join could add that).
                $result = $result && $this->getDb()->query("insert into `$this->tableName`
                    (entry_id, token, source_type, source_id, created, updated)
                    select entry_id, token, 'Order' as source_type, order_id as source_id, created, updated
                    from `$oldTableName``;");

                // Delete old table.
                $result = $result && $this->getDb()->query("DROP TABLE `$oldTableName`");
            } else {
                // Table exists in current data model.
                $result = true;
            }
        }
        return $result;
    }

    public function uninstall(): bool
    {
        return (bool) $this->getDb()->query("DROP TABLE `$this->tableName`");
    }

    /**
     * Creates the acumulus_entry table.
     *
     * For some background info about 2 timestamp columns see:
     * - {@link https://dev.mysql.com/doc/relnotes/mysql/5.6/en/news-5-6-5.html#mysqld-5-6-5-data-types}.
     * - {@link https://dev.mysql.com/doc/refman/5.6/en/timestamp-initialization.html}.
     * - {@link https://dev.mysql.com/doc/refman/8.0/en/sql-mode.html#sqlmode_no_zero_date}.
     *
     * @return bool
     *   Success.
     *
     * @throws \Exception
     */
    protected function createTable(): bool
    {
        return (bool) $this->getDb()->query("CREATE TABLE IF NOT EXISTS `$this->tableName` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) NOT NULL DEFAULT '0',
            `entry_id` int(11) DEFAULT NULL,
            `token` char(32) DEFAULT NULL,
            `source_type` varchar(32) NOT NULL,
            `source_id` int(11) NOT NULL,
            `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `acumulus_idx_entry_id` (`entry_id`),
            UNIQUE INDEX `acumulus_idx_source` (`source_id`, `source_type`)
            )");
    }

    /**
     * {@inheritDoc}
     *
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function upgrade(string $currentVersion): bool
    {
        $result = true;

        if (version_compare($currentVersion, '4.4.0', '<')) {
            $result = $this->getDb()->query("ALTER TABLE `$this->tableName`
                CHANGE COLUMN `entry_id` `entry_id` INT(11) NULL DEFAULT NULL,
                CHANGE COLUMN `token` `token` CHAR(32) NULL DEFAULT NULL");
        }

        // Drop and recreate index (to make it non-unique). (Already done in 6.0.0, but
        // the create statement was not adapted, so users who started using this module
        // after 6.0.0, and before 8.0.0, will still get a unique index.)
        if (version_compare($currentVersion, '8.0.0', '<')) {
            $result = $this->getDb()->query("ALTER TABLE `$this->tableName` DROP INDEX `acumulus_idx_entry_id`")
                  AND $this->getDb()->query("CREATE INDEX `acumulus_idx_entry_id` ON `$this->tableName` (`entry_id`)");
        }

        return $result;
    }

    /**
     * Wrapper method to get {@see Registry::$db}.
     *
     * @return \Opencart\System\Library\DB|\DB
     */
    protected function getDb()
    {
        return $this->getRegistry()->db;
    }

    /**
     * Wrapper method that returns the OpenCart registry class.
     */
    protected function getRegistry(): Registry
    {
        return Registry::getInstance();
    }
}
