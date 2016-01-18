<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use Db;
use Siel\Acumulus\PrestaShop\Invoice\Source;
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
   * AcumulusEntryModel constructor.
   */
  public function __construct() {
    $this->tableName = _DB_PREFIX_ . 'acumulus_entry';
  }

  /**
   * {@inheritdoc}
   */
  public function getByEntryId($entryId) {
    $result = Db::getInstance()->executeS(sprintf("SELECT * FROM `%s` WHERE id_entry = %u", $this->tableName, $entryId));
    return count($result) === 1 ? reset($result) : null;
  }

  /**
   * {@inheritdoc}
   */
  public function getByInvoiceSourceId($invoiceSourceType, $invoiceSourceId) {
    $result = Db::getInstance()->executeS(sprintf("SELECT * FROM `%s` WHERE source_type = '%s' AND source_id = %u", $this->tableName, $invoiceSourceType, $invoiceSourceId));
    return count($result) === 1 ? reset($result) : null;
  }

  /**
   * {@inheritdoc}
   */
  protected function insert($invoiceSource, $entryId, $token, $created) {
    if ($invoiceSource->getType() === Source::Order) {
      $shopId = $invoiceSource->getSource()->id_shop;
      $shopGroupId = $invoiceSource->getSource()->id_shop_group;
    }
    else {
      $shopId = 0;
      $shopGroupId = 0;
    }
    return Db::getInstance()->execute(sprintf("INSERT INTO `%s` (id_shop, id_shop_group, id_entry, token, source_type, source_id, updated) VALUES (%u, %u, %u, '%s', '%s', %u, '%s')",
      $this->tableName, $shopId, $shopGroupId, $entryId, $token, $invoiceSource->getType(), $invoiceSource->getId(), $created));
  }

  /**
   * {@inheritdoc}
   */
  protected function update($record, $entryId, $token, $updated) {
    return Db::getInstance()->execute(sprintf("UPDATE `%s` SET id_entry = %u, token = '%s', updated = '%s' WHERE id = %u",
      $this->tableName, $entryId, $token, $updated, $record['id']));
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
  public function install() {
    return Db::getInstance()->execute("CREATE TABLE IF NOT EXISTS `{$this->tableName}` (
      `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			`id_shop` int(11) UNSIGNED NOT NULL DEFAULT '1',
			`id_shop_group` int(11) UNSIGNED NOT NULL DEFAULT '1',
      `id_entry` int(11) UNSIGNED NOT NULL,
      `token` char(32) NOT NULL,
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
  public function uninstall() {
    return Db::getInstance()->execute("DROP TABLE `{$this->tableName}`");
  }

}
