<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use Db;
use Siel\Acumulus\Shop\AcumulusEntryModel as BaseAcumulusEntryModel;

/**
 * Implements the PrestaShop specific acumulus entry model class.
 *
 * In WooCommerce this data is stored as metadata. As such, the "records"
 * returned here are an array of all metadata values, thus not filtered by
 * Acumulus keys.
 */
class AcumulusEntryModel extends BaseAcumulusEntryModel {

  /** @var string */
  protected $tableName;

  /**
   * Constructor
   */
  function __construct() {
    $this->tableName = _DB_PREFIX_ . 'acumulus_entry';
  }

  /**
   * {@inheritdoc}
   */
  public function getByEntryId($entryId) {
    $result = Db::getInstance()->executeS(sprintf("SELECT * FROM %s WHERE id_entry = %u", $this->tableName, $entryId));
    return count($result) === 1 ? reset($result) : null;
  }

  /**
   * {@inheritdoc}
   */
  public function getByInvoiceSourceId($invoiceSourceType, $invoiceSourceId) {
    $result = Db::getInstance()->executeS(sprintf("SELECT * FROM %s WHERE id_type = '%s' AND id_order = %u", $this->tableName, $invoiceSourceType, $invoiceSourceId));
    return count($result) === 1 ? reset($result) : null;
  }

  /**
   * {@inheritdoc}
   */
  protected function insert($invoiceSource, $entryId, $token, $created) {
    $shopId = $invoiceSource->getSource()->id_shop;
    $shopGroupId = $invoiceSource->getSource()->id_shop_group;
    return Db::getInstance()->execute(sprintf("INSERT INTO %s (id_shop, id_shop_group, id_entry, token, id_order, updated) VALUES (%u, %u, %u, '%s', %u, '%s')",
      $this->tableName, $shopId, $shopGroupId, $entryId, $token, $invoiceSource->getId(), $created));
  }

  /**
   * {@inheritdoc}
   */
  protected function update($record, $entryId, $token, $updated) {
    return Db::getInstance()->execute(sprintf("UPDATE %s SET id_shop = %u, id_shop_group = %u, id_entry = %u, token = '%s', id_order = %u, updated = '%s' WHERE id = %u",
      $this->tableName, $record['id_shop'], $record['id_shop_group'], $entryId, $token, $record['id_order'], $updated, $record['id']));
  }

  /**
   * {@inheritdoc}
   */
  protected function sqlNow() {
    return date('Y-m-d H:i:s');
  }

  /**
   * {@inheritdoc}
   */
  function install() {
    return Db::getInstance()->execute("CREATE TABLE IF NOT EXISTS `{$this->tableName}` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
			`id_shop` INTEGER UNSIGNED NOT NULL DEFAULT '1',
			`id_shop_group` INTEGER UNSIGNED NOT NULL DEFAULT '1',
      `id_entry` int(11) NOT NULL,
      `token` char(32) NOT NULL,
      `id_type` varchar(32) NOT NULL,
      `id_order` int(11) NOT NULL,
      `created` timestamp DEFAULT CURRENT_TIMESTAMP,
      `updated` timestamp NOT NULL,
      PRIMARY KEY (`id`),
      UNIQUE INDEX `idx_entry_id` (`id_entry`),
      UNIQUE INDEX `idx_order_id` (`id_order`)
    )");
  }

  /**
   * {@inheritdoc}
   */
  public function uninstall() {
    return Db::getInstance()->execute("DROP TABLE `{$this->tableName}`");
  }

}
