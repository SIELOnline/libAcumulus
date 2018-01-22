<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use Db;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\PrestaShop\Invoice\Source;
use Siel\Acumulus\Shop\AcumulusEntryModel as BaseAcumulusEntryModel;

/**
 * Implements the PrestaShop specific acumulus entry model class.
 *
 * In WooCommerce this data is stored as metadata. As such, the "records"
 * returned here are an array of all metadata values, thus not filtered by
 * Acumulus keys.
 */
class AcumulusEntryModel extends BaseAcumulusEntryModel
{
    /** @var string */
    protected $tableName;

    /**
     * AcumulusEntryModel constructor.
     *
     * @param \Siel\Acumulus\Helpers\Log $log
     */
    public function __construct(Log $log)
    {
        parent::__construct($log);
        $this->tableName = _DB_PREFIX_ . 'acumulus_entry';
    }

    /**
     * {@inheritdoc}
     */
    public function getByEntryId($entryId)
    {
        $operator = $entryId === null ? 'is' : '=';
        $entryId = $entryId === null ? 'null' : (string) (int) $entryId;
        $result = Db::getInstance()->executeS("SELECT * FROM `{$this->tableName}` WHERE id_entry $operator $entryId");
        return empty($result) ? null : (count($result) === 1 ? reset($result) : $result);
    }

    /**
     * {@inheritdoc}
     */
    public function getByInvoiceSourceId($invoiceSourceType, $invoiceSourceId)
    {
        $result = Db::getInstance()->executeS("SELECT * FROM `{$this->tableName}` WHERE source_type = '$invoiceSourceType' AND source_id = $invoiceSourceId");
        return count($result) === 1 ? reset($result) : null;
    }

    /**
     * {@inheritdoc}
     */
    protected function insert($invoiceSource, $entryId, $token, $created)
    {
        if ($invoiceSource->getType() === Source::Order) {
            $shopId = $invoiceSource->getSource()->id_shop;
            $shopGroupId = $invoiceSource->getSource()->id_shop_group;
        } else {
            $shopId = 0;
            $shopGroupId = 0;
        }
        $entryId = $entryId === null ? 'null' : (string) (int) $entryId;
        $token = $token === null ? 'null' : "'" . Db::getInstance()->escape($token) . "'";
        $invoiceSourceType = $invoiceSource->getType();
        $invoiceSourceId = $invoiceSource->getId();
        return Db::getInstance()->execute("INSERT INTO `{$this->tableName}` (id_shop, id_shop_group, id_entry, token, source_type, source_id, updated) VALUES ($shopId, $shopGroupId, $entryId, $token, '$invoiceSourceType', $invoiceSourceId, '$created')");
    }

    /**
     * {@inheritdoc}
     */
    protected function update($record, $entryId, $token, $updated)
    {
        $entryId = $entryId === null ? 'null' : (string) (int) $entryId;
        $token = $token === null ? 'null' : "'" . Db::getInstance()->escape($token) . "'";
        return Db::getInstance()->execute("UPDATE `{$this->tableName}` SET id_entry = $entryId, token = $token, updated = '$updated' WHERE id = {$record['id']}");
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
        return Db::getInstance()->execute("CREATE TABLE IF NOT EXISTS `{$this->tableName}` (
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
        return Db::getInstance()->execute("DROP TABLE `{$this->tableName}`");
    }
}
