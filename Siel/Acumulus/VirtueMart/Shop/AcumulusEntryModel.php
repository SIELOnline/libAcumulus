<?php
namespace Siel\Acumulus\VirtueMart\Shop;

use DateTimeZone;
use JDate;
use JFactory;
use JTable;
use Siel\Acumulus\Shop\AcumulusEntryModel as BaseAcumulusEntryModel;

/**
 * Implements the VirtueMart specific acumulus entry model class.
 */
class AcumulusEntryModel extends BaseAcumulusEntryModel {

  /** @var \AcumulusTableAcumulusEntry */
  protected $table;

  /**
   * @return \AcumulusTableAcumulusEntry
   */
  protected function newTable() {
    $this->table = JTable::getInstance('AcumulusEntry', 'AcumulusTable');
    if ($this->table === false) {
      $this->config->getLog()->error('AcumulusEntryModel::newTable(): table not created');
    }
    return $this->table;
  }

  /**
   * {@inheritdoc}
   */
  public function getByEntryId($entryId) {
    $result = $this->newTable()->load(array('entry_id' => $entryId), true);
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getByInvoiceSourceId($invoiceSourceType, $invoiceSourceId) {
    $result = $this->newTable()->load(array('source_type' => $invoiceSourceType, 'source_id' => $invoiceSourceId), true);
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function insert($invoiceSource, $entryId, $token, $created) {
    // Start with new table class to not overwrite any loaded record.
    $table = $this->newTable();
    $table->entry_id = $entryId;
    $table->token = $token;
    $table->source_type = $invoiceSource->getType();
    $table->source_id = $invoiceSource->getId();
    $table->created = $created;
    $table->updated = $created;
    return $table->store();
  }

  /**
   * {@inheritdoc}
   */
  protected function update($record, $entryId, $token, $updated) {
    // Continue with existing table class with already loaded record.
    $table = $this->table;
    $table->entry_id = $entryId;
    $table->token = $token;
    $table->source_type = $record['source_type'];
    $table->source_id = $record['source_id'];
    $table->updated = $updated;
    return $table->store(false);
  }

  /**
   * {@inheritdoc}
   */
  protected function sqlNow() {
    $tz = new DateTimeZone(JFactory::getApplication()->get('offset'));
    $date = new JDate();
    $date->setTimezone($tz);
    return $date->toSql(true);
  }

  /**
   * {@inheritdoc}
   *
   * Joomla has separate install scripts, so nothing has to be done here.
   */
  function install() {
    return false;
  }

  /**
   * {@inheritdoc}
   *
   * Joomla has separate install scripts, so nothing has to be done here.
   */
  public function uninstall() {
    return false;
  }

}
