<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use Db;
use Siel\Acumulus\Helpers\ContainerInterface;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\AcumulusEntryManager as BaseAcumulusEntryManager;
use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;

/**
 * Implements the PrestaShop specific acumulus entry model class.
 *
 * SECURITY REMARKS
 * ----------------
 * In PrestaShop saving and querying acumulus entries is done via self
 * constructed queries, therefore this class takes care of sanitizing itself.
 * - Numbers are cast to (int).
 * - Strings are escaped using the escape() method of the DB driver class
 *   (unless they are hard coded).
 * Note that:
 * - $invoiceSource, $created and $updated are set in calling code, and can
 *   thus be considered trusted, but are still escaped.
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
    public function __construct(ContainerInterface $container, Log $log)
    {
        parent::__construct($container, $log);
        $this->tableName = _DB_PREFIX_ . 'acumulus_entry';
    }

    /**
     * {@inheritdoc}
     */
    public function getByEntryId($entryId)
    {
        $operator = $entryId === null ? 'is' : '=';
        $entryId = $entryId === null ? 'null' : (string) (int) $entryId;
        /** @noinspection PhpUnhandledExceptionInspection */
        $result = $this->getDb()->executeS("SELECT * FROM `{$this->tableName}` WHERE id_entry $operator $entryId");
        return $this->convertDbResultToAcumulusEntries($result);
    }

    /**
     * {@inheritdoc}
     */
    public function getByInvoiceSource(Source $invoiceSource)
    {
        $invoiceSourceType = $this->getDb()->escape($invoiceSource->getType());
        $invoiceSourceId = (int) $invoiceSource->getId();
        /** @noinspection PhpUnhandledExceptionInspection */
        $result = $this->getDb()->executeS("SELECT * FROM `{$this->tableName}` WHERE source_type = '$invoiceSourceType' AND source_id = $invoiceSourceId");
        return $this->convertDbResultToAcumulusEntries($result);
    }

    /**
     * {@inheritdoc}
     */
    protected function insert(Source $invoiceSource, $entryId, $token, $created)
    {
        if ($invoiceSource->getType() === Source::Order) {
            $shopId = (int) $invoiceSource->getSource()->id_shop;
            $shopGroupId = (int) $invoiceSource->getSource()->id_shop_group;
        } else {
            $shopId = 0;
            $shopGroupId = 0;
        }
        $invoiceSourceType = $this->getDb()->escape($invoiceSource->getType());
        $invoiceSourceId = (int) $invoiceSource->getId();
        $entryId = $entryId === null ? 'null' : (string) (int) $entryId;
        $token = $token === null ? 'null' : "'" . $this->getDb()->escape($token) . "'";
        $created = $this->getDb()->escape($created);
        return $this->getDb()->execute("INSERT INTO `{$this->tableName}` (id_shop, id_shop_group, id_entry, token, source_type, source_id, updated) VALUES ($shopId, $shopGroupId, $entryId, $token, '$invoiceSourceType', $invoiceSourceId, '$created')");
    }

    /**
     * {@inheritdoc}
     */
    protected function update(BaseAcumulusEntry $record, $entryId, $token, $updated)
    {
        $record = $record->getRecord();
        $recordId = (int) $record['id'];
        $entryId = $entryId === null ? 'null' : (string) (int) $entryId;
        $token = $token === null ? 'null' : "'" . $this->getDb()->escape($token) . "'";
        $updated = $this->getDb()->escape($updated);
        return $this->getDb()->execute("UPDATE `{$this->tableName}` SET id_entry = $entryId, token = $token, updated = '$updated' WHERE id = $recordId");
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
        return $this->getDb()->execute("CREATE TABLE IF NOT EXISTS `{$this->tableName}` (
        `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `id_shop` int(11) UNSIGNED NOT NULL DEFAULT '1',
        `id_shop_group` int(11) UNSIGNED NOT NULL DEFAULT '1',
        `id_entry` int(11) UNSIGNED DEFAULT NULL,
        `token` char(32) DEFAULT NULL,
        `source_type` varchar(32) NOT NULL,
        `source_id` int(11) UNSIGNED NOT NULL,
        `created` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated` timestamp NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE INDEX `acumulus_idx_entry_id` (`id_entry`),
        UNIQUE INDEX `acumulus_idx_source` (`source_id`, `source_type`)
        )");
    }

    /**
     * {@inheritdoc}
     */
    public function uninstall()
    {
        return $this->getDb()->execute("DROP TABLE `{$this->tableName}`");
    }

    /**
     * Wrapper method around teh Db instance.
     *
     * @return \Db
     */
    protected function getDb()
    {
        return Db::getInstance();
    }
}
