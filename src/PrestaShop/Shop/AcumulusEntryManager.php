<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use Db;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\PluginConfig;
use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;
use Siel\Acumulus\Shop\AcumulusEntryManager as BaseAcumulusEntryManager;

/**
 * Implements the PrestaShop specific acumulus entry model class.
 *
 * SECURITY REMARKS
 * ----------------
 * In PrestaShop saving and querying acumulus entries is done via self
 * constructed queries, therefore this class takes care of sanitizing itself.
 * - Numbers are cast by using numeric formatters (like %u, %d, %f) with
 *   sprintf().
 * - Strings are escaped using pSQL(), unless they are hard coded or are
 *   internal variables.
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
        $this->tableName = _DB_PREFIX_ . 'acumulus_entry';
    }

    /**
     * {@inheritdoc}
     */
    public function getByEntryId($entryId)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $result = $this->getDb()->executeS(sprintf(
            "SELECT * FROM `%s` WHERE id_entry %s %s",
            $this->tableName,
            $entryId === null ? 'is' : '=',
            $entryId === null ? 'null' : (string) (int) $entryId
        ));
        return $this->convertDbResultToAcumulusEntries($result);
    }

    /**
     * {@inheritdoc}
     */
    public function getByInvoiceSource(Source $invoiceSource)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $result = $this->getDb()->executeS(sprintf(
            "SELECT * FROM `%s` WHERE source_type = '%s' AND source_id = %u",
            $this->tableName,
            pSql($invoiceSource->getType()),
            $invoiceSource->getId()
        ));
        return $this->convertDbResultToAcumulusEntries($result);
    }

    /**
     * {@inheritdoc}
     */
    protected function insert(Source $invoiceSource, $entryId, $token, $created)
    {
        if ($invoiceSource->getType() === Source::Order) {
            $shopId = $invoiceSource->getSource()->id_shop;
            $shopGroupId = $invoiceSource->getSource()->id_shop_group;
        } else {
            $shopId = 0;
            $shopGroupId = 0;
        }
        return $this->getDb()->execute(sprintf(
            "INSERT INTO `%s` (id_shop, id_shop_group, id_entry, token, source_type, source_id, updated) VALUES (%u, %u, %u, %s, '%s', %u, '%s')",
            $this->tableName,
            $shopId,
            $shopGroupId,
            $entryId === null ? 'null' : (string) (int) $entryId,
            $token === null ? 'null' : ("'" . pSql($token) . "'"),
            pSql($invoiceSource->getType()),
            $invoiceSource->getId(),
            pSql($created)
        ));
    }

    /**
     * {@inheritdoc}
     */
    protected function update(BaseAcumulusEntry $record, $entryId, $token, $updated)
    {
        $record = $record->getRecord();
        return $this->getDb()->execute(sprintf(
            "UPDATE `%s` SET id_entry = %s, token = %s, updated = '%s' WHERE id = %u",
            $this->tableName,
            $entryId === null ? 'null' : (string) (int) $entryId,
            $token === null ? 'null' : ("'" . pSql($token) . "'"),
            pSql($updated),
            $record['id']
        ));
    }

    /**
     * {@inheritdoc}
     */
    protected function sqlNow()
    {
        return date(PluginConfig::TimeStampFormat_Sql);
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
     * Wrapper method around the Db instance.
     *
     * @return \Db
     */
    protected function getDb()
    {
        return Db::getInstance();
    }
}
