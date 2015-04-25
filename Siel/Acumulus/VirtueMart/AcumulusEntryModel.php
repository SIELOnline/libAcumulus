<?php
namespace Siel\Acumulus\VirtueMart;

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
    $table = $this->newTable();
    $table->entry_id = $entryId;
    $table->token = $token;
    $table->source_type = $invoiceSource->getType();
    $table->source_id = $invoiceSource->getId();
    $table->created = $created;
    $table->updated = $created;
    $table->store();
  }

  /**
   * {@inheritdoc}
   */
  protected function update($record, $entryId, $token, $updated) {
    $table = $this->table;
    $table->entry_id = $entryId;
    $table->token = $token;
    $table->source_type = $record['source_type'];
    $table->source_id = $record['source_id'];
    $table->updated = $updated;
    $table->store(false);
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
   * Joomla has separate install scripts, so nothing has tobe done here.
   */
  function install() {
    return false;
  }

  /**
   * {@inheritdoc}
   *
   * Joomla has separate install scripts, so nothing has tobe done here.
   */
  public function uninstall() {
    return false;
  }

}
