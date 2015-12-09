<?php
namespace Siel\Acumulus\Joomla\Shop;

use DateTimeZone;
use JDate;
use JFactory;
use \Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;

abstract class InvoiceManager extends BaseInvoiceManager {

  /**
   * Helper method that executes a query to retrieve a list of invoice source
   * ids and returns a list of invoice sources for these ids.
   *
   * @param string $invoiceSourceType
   * @param string $query
   *
   * @return \Siel\Acumulus\Invoice\Source[]
   *   A non keyed array with invoice Sources.
   */
  protected function getSourcesByQuery($invoiceSourceType, $query) {
    $sourceIds = $this->loadColumn($query);
    return $this->getSourcesByIds($invoiceSourceType, $sourceIds);
  }

  /**
   * Creates a set of sources given their ids.
   *
   * @param string $invoiceSourceType
   * @param int[] $sourceIds
   *
   * @return \Siel\Acumulus\Invoice\Source[]
   *   A non keyed array with invoice Sources.
   *
   * @todo: can we generalize this, including the next abstract method?
   */
  protected function getSourcesByIds($invoiceSourceType, $sourceIds) {
    $results = array();
    foreach ($sourceIds as $sourceId) {
      $results[] = $this->getSourceById($invoiceSourceType, $sourceId);
    }
    return $results;
  }

  /**
   * Creates a source given its type and id.
   *
   * @param string $invoiceSourceType
   * @param int $sourceId
   *
   * @return \Siel\Acumulus\Invoice\Source
   *   An invoice Source.
   */
  abstract protected function getSourceById($invoiceSourceType, $sourceId);

  /**
   * Helper method to execute a query and return the 1st column from the
   * results.
   *
   * @param string $query
   *
   * @return int[]
   *   A non keyed array with the values of the 1st results of the query result.
   */
  protected function loadColumn($query) {
    return $this->getDb()->setQuery($query)->loadColumn();
  }

  /**
   * Helper method to get the db object.
   *
   * @return \JDatabaseDriver
   */
  protected function getDb() {
    return JFactory::getDBO();
  }

  /**
   * Helper method that returns a date in the correct and escaped sql format.
   *
   * @param string $date
   *   Date in yyyy-mm-dd format.
   *
   * @return string
   */
  protected function toSql($date) {
    $tz = new DateTimeZone(JFactory::getApplication()->get('offset'));
    $date = new JDate($date);
    $date->setTimezone($tz);
    return $date->toSql(TRUE);
  }

}
