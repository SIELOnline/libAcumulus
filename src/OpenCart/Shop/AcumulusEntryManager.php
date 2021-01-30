<?php
namespace Siel\Acumulus\OpenCart\Shop;

use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\OpenCart\Helpers\Registry;
use Siel\Acumulus\Shop\AcumulusEntryManager as BaseAcumulusEntryManager;
use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;

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
    /** @var string */
    protected $tableName;

    /**
     * {@inheritdoc}
     */
    public function __construct(Container $container, Log $log)
    {
        parent::__construct($container, $log);
        $this->tableName = DB_PREFIX . 'acumulus_entry';
    }

    /**
     * {@inheritdoc}
     */
    public function getByEntryId($entryId)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $result = $this->getDb()->query(sprintf(
            "SELECT * FROM `%s` WHERE entry_id %s %s",
            $this->tableName,
            $entryId === null ? 'is' : '=',
            $entryId === null ? 'null' : (string) (int) $entryId
        ));
        return $this->convertDbResultToAcumulusEntries($result->rows);
    }

    /**
     * {@inheritdoc}
     */
    public function getByInvoiceSource(Source $invoiceSource, $ignoreLock = true)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $result = $this->getDb()->query(sprintf(
            "SELECT * FROM `%s` WHERE source_type = '%s' AND source_id = %u",
            $this->tableName,
            $this->getDb()->escape($invoiceSource->getType()),
            $invoiceSource->getId()
        ));
        return $this->convertDbResultToAcumulusEntries($result->rows, $ignoreLock);
    }

    /**
     * {@inheritdoc}
     */
    protected function insert(Source $invoiceSource, $entryId, $token, $created)
    {
        if ($invoiceSource->getType() === Source::Order) {
            $order = $invoiceSource->getSource();
            $storeId = $order['store_id'];
        } else {
            $storeId = 0;
        }
        /** @noinspection PhpUnhandledExceptionInspection */
        return (bool) $this->getDb()->query(sprintf(
            "INSERT INTO `%s` (store_id, entry_id, token, source_type, source_id, updated) VALUES (%u, %s, %s, '%s', %u, '%s')",
            $this->tableName,
            $storeId,
            $entryId === null ? 'null' : (string) (int) $entryId,
            $token === null ? 'null' : ("'" . $this->getDb()->escape($token) . "'"),
            $this->getDb()->escape($invoiceSource->getType()),
            $invoiceSource->getId(),
            $this->getDb()->escape($created)
        ));
    }

    /**
     * {@inheritdoc}
     */
    protected function update(BaseAcumulusEntry $entry, $entryId, $token, $updated)
    {
        $record = $entry->getRecord();
        /** @noinspection PhpUnhandledExceptionInspection */
        return (bool) $this->getDb()->query(sprintf(
            "UPDATE `%s` SET entry_id = %s, token = %s, updated = '%s' WHERE id = %u",
            $this->tableName,
            $entryId === null ? 'null' : (string) (int) $entryId,
            $token === null ? 'null' : "'" . $this->getDb()->escape($token) . "'",
            $this->getDb()->escape($updated),
            $record['id']
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function delete(BaseAcumulusEntry $entry)
    {
        $record = $entry->getRecord();
        /** @noinspection PhpUnhandledExceptionInspection */
        return (bool) $this->getDb()->query(sprintf(
            "DELETE FROM `%s` WHERE id = %u",
            $this->tableName,
            $record['id']
        ));
    }

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * Helper method to get the db object.
     *
     * @return \DBMySQLi|\DB\MySQLi
     */
    protected function getDb()
    {
        return Registry::getInstance()->db;
    }

    /**
     * {@inheritdoc}
     */
    protected function sqlNow()
    {
        return date(Api::Format_TimeStamp);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    public function install()
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $queryResult = $this->getDb()->query("show tables like '{$this->tableName}'");
        $tableExists = !empty($queryResult->num_rows);
        if (!$tableExists) {
            // Table does not exist: create it.
            return $this->createTable();
        } else {
            // Table does exist: but in old or current data model?
            /** @noinspection PhpUnhandledExceptionInspection */
            $columnExists = $this->getDb()->query("show columns from `{$this->tableName}` like 'source_type'");
            $columnExists = !empty($columnExists->num_rows);
            if (!$columnExists) {
                // Table exists but in old data model: alter table
                // Rename currently existing table.
                $oldTableName = $this->tableName . '_old';
                /** @noinspection PhpUnhandledExceptionInspection */
                $result = $this->getDb()->query("ALTER TABLE `{$this->tableName}` RENAME `$oldTableName`;");

                // Create table in new data model.
                $result = $this->createTable() && $result;

                // Copy data from old to new table.
                // Orders only, credit slips were not supported in that version.
                // Nor did we support multi store shops (though a join could add that).
                /** @noinspection PhpUnhandledExceptionInspection */
                $result = $result && $this->getDb()->query("insert into `{$this->tableName}`
                    (entry_id, token, source_type, source_id, created, updated)
                    select entry_id, token, 'Order' as source_type, order_id as source_id, created, updated
                    from `$oldTableName``;");

                // Delete old table.
                /** @noinspection PhpUnhandledExceptionInspection */
                $result = $result && $this->getDb()->query("DROP TABLE `$oldTableName`");

                return $result;
            } else {
                // Table exists in current data model.
                return true;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function uninstall()
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return (bool) $this->getDb()->query("DROP TABLE `{$this->tableName}`");
    }

    /**
     * @return bool
     *
     * @throws \Exception
     */
    protected function createTable()
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return (bool) $this->getDb()->query("CREATE TABLE IF NOT EXISTS `{$this->tableName}` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `store_id` int(11) NOT NULL DEFAULT '0',
            `entry_id` int(11) DEFAULT NULL,
            `token` char(32) DEFAULT NULL,
            `source_type` varchar(32) NOT NULL,
            `source_id` int(11) NOT NULL,
            `created` timestamp DEFAULT CURRENT_TIMESTAMP,
            `updated` timestamp NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE INDEX `acumulus_idx_entry_id` (`entry_id`),
            UNIQUE INDEX `acumulus_idx_source` (`source_id`, `source_type`)
            )");
    }

    /**
     * {@inheritDoc}
     */
    public function upgrade($currentVersion)
    {
        $result = true;

        if (version_compare($currentVersion, '4.4.0', '<')) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $result = $this->getDb()->query("ALTER TABLE `{$this->tableName}`
                CHANGE COLUMN `entry_id` `entry_id` INT(11) NULL DEFAULT NULL,
                CHANGE COLUMN `token` `token` CHAR(32) NULL DEFAULT NULL");
        }

        // Drop and recreate index (to make it non-unique).
        if (version_compare($currentVersion, '6.0.0', '<')) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $result = $this->getDb()->query("ALTER TABLE `{$this->tableName}` DROP INDEX `acumulus_idx_entry_id`")
                  AND $this->getDb()->query("CREATE INDEX `acumulus_idx_entry_id` ON `{$this->tableName}` (`entry_id`)");
        }

        return $result;
    }
}
