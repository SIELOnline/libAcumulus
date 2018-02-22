<?php
namespace Siel\Acumulus\OpenCart\Shop;

use Siel\Acumulus\Helpers\ContainerInterface;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\OpenCart\Helpers\Registry;
use Siel\Acumulus\Shop\AcumulusEntryManager as BaseAcumulusEntryManager;
use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;

/**
 * Implements the OpenCart specific acumulus entry model class.
 *
 * In WooCommerce this data is stored as metadata. As such, the "records"
 * returned here are an array of all metadata values, thus not filtered by
 * Acumulus keys.
 */
class AcumulusEntryManager extends BaseAcumulusEntryManager
{
    /** @var string */
    protected $tableName;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container, Log $log)
    {
        parent::__construct($container, $log);
        $this->tableName = DB_PREFIX . 'acumulus_entry';
    }

    /**
     * {@inheritdoc}
     */
    public function getByEntryId($entryId)
    {
        $operator = $entryId === null ? 'is' : '=';
        $entryId = $entryId === null ? 'null' : (string) (int) $entryId;
        /** @noinspection PhpUnhandledExceptionInspection */
        $result = $this->getDb()->query("SELECT * FROM {$this->tableName} WHERE entry_id $operator $entryId");
        return $this->convertDbResultToAcumulusEntries($result->rows);
    }

    /**
     * {@inheritdoc}
     */
    public function getByInvoiceSourceId($invoiceSourceType, $invoiceSourceId)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $result = $this->getDb()->query("SELECT * FROM `{$this->tableName}` WHERE source_type = '$invoiceSourceType' AND source_id = $invoiceSourceId");
        return $this->convertDbResultToAcumulusEntries($result->rows);
    }

    /**
     * {@inheritdoc}
     */
    protected function insert($invoiceSource, $entryId, $token, $created)
    {
        if ($invoiceSource->getType() === Source::Order) {
            $storeId = $invoiceSource->getSource();
            $storeId = $storeId['store_id'];
        } else {
            $storeId = 0;
        }
        $entryId = $entryId === null ? 'null' : (string) (int) $entryId;
        $token = $token === null ? 'null' : "'" . $this->getDb()->escape($token) . "'";
        $invoiceSourceType = $invoiceSource->getType();
        $invoiceSourceId = $invoiceSource->getId();
        /** @noinspection PhpUnhandledExceptionInspection */
        return (bool) $this->getDb()->query("INSERT INTO `{$this->tableName}` (store_id, entry_id, token, source_type, source_id, updated) VALUES ($storeId, $entryId, $token, '$invoiceSourceType', $invoiceSourceId, '$created')");
    }

    /**
     * {@inheritdoc}
     */
    protected function update(BaseAcumulusEntry $record, $entryId, $token, $updated)
    {
        $record = $record->getRecord();
        $entryId = $entryId === null ? 'null' : (string) (int) $entryId;
        $token = $token === null ? 'null' : "'" . $this->getDb()->escape($token) . "'";
        /** @noinspection PhpUnhandledExceptionInspection */
        return (bool) $this->getDb()->query("UPDATE `{$this->tableName}` SET entry_id = $entryId, token = $token, updated = '$updated' WHERE id = {$record['id']}");
    }

    /** @noinspection PhpUndefinedNamespaceInspection */
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
        return date('Y-m-d H:i:s');
    }

    /**
     * {@inheritdoc}
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
     * Updates the table to the correct version.
     *
     * @param string $version
     *
     * @return bool
     */
    public function upgrade($version)
    {
        if ($version === '4.4.0') {
            /** @noinspection PhpUnhandledExceptionInspection */
            return (bool)$this->getDb()->query("ALTER TABLE `{$this->tableName}`
                CHANGE COLUMN `entry_id` `entry_id` INT(11) NULL DEFAULT NULL,
                CHANGE COLUMN `token` `token` CHAR(32) NULL DEFAULT NULL");
        }
        return true;
    }
}
