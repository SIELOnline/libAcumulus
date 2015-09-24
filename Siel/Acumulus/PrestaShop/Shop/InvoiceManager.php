<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use DateTime;
use Db;
use Hook;
use Order;
use \Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\PrestaShop\Invoice\Source;
use Siel\Acumulus\Shop\Config;
use \Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;

class InvoiceManager extends BaseInvoiceManager {

  /** @var string */
  protected $tableName;

  /**
   * {@inheritdoc}
   */
  public function __construct(Config $config) {
    parent::__construct($config);
    $this->tableName = _DB_PREFIX_ . Order::$definition['table'];
  }

  /**
   * {@inheritdoc}
   */
  public function getInvoiceSourcesByIdRange($invoiceSourceType, $InvoiceSourceIdFrom, $InvoiceSourceIdTo) {
    $key = Order::$definition['primary'];
    $orderIds = Db::getInstance()->executeS(sprintf("SELECT `%s` FROM `%s` WHERE `%s` BETWEEN %u AND %u", $key, $this->tableName, $key, $InvoiceSourceIdFrom, $InvoiceSourceIdTo));
    return Source::invoiceSourceIdsToSources($invoiceSourceType, $this->getOrderIds($orderIds));
  }

  /**
   * {@inheritdoc}
   */
  public function getInvoiceSourcesByReferenceRange($invoiceSourceType, $InvoiceSourceReferenceFrom, $InvoiceSourceReferenceTo) {
    $key = Order::$definition['primary'];
    $reference = 'reference';
    $orderIds = Db::getInstance()->executeS(sprintf("SELECT `%s` FROM `%s` WHERE `%s` BETWEEN '%s' AND '%s'", $key, $this->tableName, $reference, pSQL($InvoiceSourceReferenceFrom), pSQL($InvoiceSourceReferenceTo)));
    return Source::invoiceSourceIdsToSources($invoiceSourceType, $this->getOrderIds($orderIds));
  }

  /**
   * {@inheritdoc}
   */
  public function getInvoiceSourcesByDateRange($invoiceSourceType, DateTime $dateFrom, DateTime $dateTo) {
    $orderIds = Order::getOrdersIdByDate($dateFrom, $dateTo);
    return Source::invoiceSourceIdsToSources($invoiceSourceType, $this->getOrderIds($orderIds));
  }

  /**
   * {@inheritdoc}
   *
   * This PrestaShop override executes the 'actionAcumulusInvoiceCreated' hook.
   */
  protected function triggerInvoiceCreated(array &$invoice, BaseSource $invoiceSource) {
    Hook::exec('actionAcumulusInvoiceCreated', array('invoice' => &$invoice, 'source' => $invoiceSource), null, true);
  }

  /**
   * {@inheritdoc}
   *
   * This PrestaShop override executes the 'actionAcumulusInvoiceCompleted' hook.
   */
  protected function triggerInvoiceCompleted(array &$invoice, BaseSource $invoiceSource) {
    Hook::exec('actionAcumulusInvoiceCompleted', array('invoice' => &$invoice, 'source' => $invoiceSource), null, true);
  }

  /**
   * {@inheritdoc}
   *
   * This PrestaShop override executes the 'actionAcumulusInvoiceSent' hook.
   */
  protected function triggerInvoiceSent(array $invoice, BaseSource $invoiceSource, array $result) {
    Hook::exec('actionAcumulusInvoiceSent', array('invoice' => &$invoice, 'source' => $invoiceSource), null, true);
  }

  /**
   * Helper method to retrieve the values from 1 column of a query result.
   *
   * @param array $dbResult
   *
   * @return int[]
   */
  protected function getOrderIds(array $dbResult) {
    $results = array();
    foreach ($dbResult as $order) {
      $results[] = (int) $order['id_order'];
    }
    return $results;
  }
}
