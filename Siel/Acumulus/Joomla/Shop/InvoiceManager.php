<?php
namespace Siel\Acumulus\Joomla\Shop;

use DateTimeZone;
use JDate;
use JEventDispatcher;
use JFactory;
use JPluginHelper;
use \Siel\Acumulus\Invoice\Source as Source;
use \Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;

/**
 * {@inheritdoc}
 *
 * This override provides Joomla specific db helper methods and defines
 * and dispatches Joomla events for the events defined by our library.
 */
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
    return $this->getSourcesByIdsOrSources($invoiceSourceType, $sourceIds);
  }

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

  /**
   * {@inheritdoc}
   *
   * This Joomla override dispatches the 'onAcumulusInvoiceCreated' event.
   */
  protected function triggerInvoiceCreated(array &$invoice, Source $invoiceSource) {
    JPluginHelper::importPlugin('acumulus');
    $results = JEventDispatcher::getInstance()->trigger('onAcumulusInvoiceCreated', array(&$invoice, $invoiceSource));
    if (count(array_filter($results, function($value) { return $value === FALSE; })) > 1) {
      $invoice = NULL;
    }
  }

  /**
   * {@inheritdoc}
   *
   * This Joomla override dispatches the 'onAcumulusInvoiceCompleted' event.
   */
  protected function triggerInvoiceCompleted(array &$invoice, Source $invoiceSource) {
    JPluginHelper::importPlugin('acumulus');
    $results = JEventDispatcher::getInstance()->trigger('onAcumulusInvoiceCompleted', array(&$invoice, $invoiceSource));
    if (count(array_filter($results, function($value) { return $value === FALSE; })) > 1) {
      $invoice = NULL;
    }
  }

  /**
   * {@inheritdoc}
   *
   * This Joomla override dispatches the 'onAcumulusInvoiceSent' event.
   */
  protected function triggerInvoiceSent(array $invoice, Source $invoiceSource, array $result) {
    JPluginHelper::importPlugin('acumulus');
    JEventDispatcher::getInstance()->trigger('onAcumulusInvoiceSent', array($invoice, $invoiceSource, $result));
  }

}
