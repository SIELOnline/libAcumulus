<?php
namespace Siel\Acumulus\OpenCart\Shop;

use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\OpenCart\Helpers\Registry;
use Siel\Acumulus\Shop\AcumulusEntryModel as BaseAcumulusEntryModel;

/**
 * Implements the OpenCart specific acumulus entry model class.
 *
 * In WooCommerce this data is stored as metadata. As such, the "records"
 * returned here are an array of all metadata values, thus not filtered by
 * Acumulus keys.
 */
class AcumulusEntryModel extends BaseAcumulusEntryModel
{
    /** @var \DB\MySQLi */
    private $db;

    /** @var string */
    protected $tableName;

    /**
     * AcumulusEntryModel constructor.
     */
    public function __construct()
    {
        $this->tableName = DB_PREFIX . 'acumulus_entry';
    }

    /**
     * {@inheritdoc}
     */
    public function getByEntryId($entryId)
    {
        $result = $this->getDb()->query(sprintf('SELECT * FROM %s WHERE entry_id = %u', $this->tableName, $entryId));
        return empty($result->row) ? $result->row : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getByInvoiceSourceId($invoiceSourceType, $invoiceSourceId)
    {
        $result = $this->getDb()->query(sprintf("SELECT * FROM `%s` WHERE source_type = '%s' AND source_id = %u", $this->tableName, $invoiceSourceType, $invoiceSourceId));
        return !empty($result->row) ? $result->row : null;
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
        return (bool) $this->getDb()->query(sprintf("INSERT INTO `%s` (store_id, entry_id, token, source_type, source_id, updated) VALUES (%d, %d, '%s', '%s', %d, '%s')",
            $this->tableName, $storeId, $entryId, $token, $invoiceSource->getType(), $invoiceSource->getId(), $created));
    }

    /**
     * {@inheritdoc}
     */
    protected function update($record, $entryId, $token, $updated)
    {
        return (bool) $this->getDb()->query(sprintf("UPDATE `%s` SET entry_id = %u, token = '%s', updated = '%s' WHERE id = %u",
            $this->tableName, $entryId, $token, $updated, $record['id']));
    }

    /**
     * Helper method to get the db object.
     *
     * @return \DB\MySQLi
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
        $queryResult = $this->getDb()->query("show tables like '{$this->tableName}'");
        $tableExists = !empty($queryResult->num_rows);
        if (!$tableExists) {
            // Table does not exist: create it.
            return $this->createTable();
        } else {
            // Table does exist: but in old or current data model?
            $columnExists = $this->getDb()->query("show columns from `{$this->tableName}` like 'source_type'");
            $columnExists = !empty($columnExists->num_rows);
            if (!$columnExists) {
                // Table exists but in old data model: alter table
                // Rename currently existing table.
                $oldTableName = $this->tableName . '_old';
                $result = $this->getDb()->query("ALTER TABLE `{$this->tableName}` RENAME `$oldTableName`;");

                // Create table in new data model.
                $result = $this->createTable() && $result;

                // Copy data from old to new table.
                // Orders only, credit slips were not supported in that version.
                // Nor did we support multi store shops (though a join could add that).
                $result = $result && $this->getDb()->query("insert into `{$this->tableName}`
          (entry_id, token, source_type, source_id, created, updated)
          select entry_id, token, 'Order' as source_type, order_id as source_id, created, updated
          from `$oldTableName``;");

                // Delete old table.
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
        return (bool) $this->getDb()->query("DROP TABLE `{$this->tableName}`");
    }

    /**
     *
     *
     *
     * @return bool
     *
     */
    protected function createTable()
    {
        return (bool) $this->getDb()->query("CREATE TABLE IF NOT EXISTS `{$this->tableName}` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `store_id` int(11) NOT NULL DEFAULT '0',
        `entry_id` int(11) NOT NULL,
        `token` char(32) NOT NULL,
        `source_type` varchar(32) NOT NULL,
        `source_id` int(11) NOT NULL,
        `created` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated` timestamp NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE INDEX `acumulus_idx_entry_id` (`entry_id`),
        UNIQUE INDEX `acumulus_idx_source` (`source_id`, `source_type`)
      )");
    }
}
