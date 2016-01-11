<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use DateTime;
use Db;
use Hook;
use Order;
use OrderSlip;
use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\PrestaShop\Invoice\Source; // @todo: still needed?
use Siel\Acumulus\Shop\Config;
use Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;

class InvoiceManager extends BaseInvoiceManager {

  /** @var string */
  protected $orderTableName;

  /** @var string */
  protected $orderSlipTableName;

  /**
   * {@inheritdoc}
   */
  public function __construct(Config $config) {
    parent::__construct($config);
    $this->orderTableName = _DB_PREFIX_ . Order::$definition['table'];
    $this->orderSlipTableName = _DB_PREFIX_ . OrderSlip::$definition['table'];
  }

  /**
   * {@inheritdoc}
   */
  public function getInvoiceSourcesByIdRange($invoiceSourceType, $InvoiceSourceIdFrom, $InvoiceSourceIdTo) {
    switch ($invoiceSourceType) {
      case Source::Order:
        $key = Order::$definition['primary'];
        $ids = Db::getInstance()->executeS(sprintf("SELECT `%s` FROM `%s` WHERE `%s` BETWEEN %u AND %u", $key, $this->orderTableName, $key, $InvoiceSourceIdFrom, $InvoiceSourceIdTo));
        return $this->getSourcesByIdsOrSources($invoiceSourceType, $this->getCol($ids, $key));
      case Source::CreditNote:
        $key = OrderSlip::$definition['primary'];
        $ids = Db::getInstance()->executeS(sprintf("SELECT `%s` FROM `%s` WHERE `%s` BETWEEN %u AND %u", $key, $this->orderSlipTableName, $key, $InvoiceSourceIdFrom, $InvoiceSourceIdTo));
        return $this->getSourcesByIdsOrSources($invoiceSourceType, $this->getCol($ids, $key));
    }
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function getInvoiceSourcesByReferenceRange($invoiceSourceType, $InvoiceSourceReferenceFrom, $InvoiceSourceReferenceTo) {
    switch ($invoiceSourceType) {
      case Source::Order:
        $key = Order::$definition['primary'];
        $reference = 'reference';
        $ids = Db::getInstance()->executeS(sprintf("SELECT `%s` FROM `%s` WHERE `%s` BETWEEN '%s' AND '%s'", $key, $this->orderTableName, $reference, pSQL($InvoiceSourceReferenceFrom), pSQL($InvoiceSourceReferenceTo)));
        return $this->getSourcesByIdsOrSources($invoiceSourceType, $this->getCol($ids, $key));
      case Source::CreditNote:
        return $this->getInvoiceSourcesByIdRange($invoiceSourceType, $InvoiceSourceReferenceFrom, $InvoiceSourceReferenceTo);
    }
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function getInvoiceSourcesByDateRange($invoiceSourceType, DateTime $dateFrom, DateTime $dateTo) {
    $dateFrom = $dateFrom->format('c');
    $dateTo = $dateTo->format('c');
    switch ($invoiceSourceType) {
      case Source::Order:
        $ids = Order::getOrdersIdByDate($dateFrom, $dateTo);
        return $this->getSourcesByIdsOrSources($invoiceSourceType, $ids);
      case Source::CreditNote:
        $ids = OrderSlip::getSlipsIdByDate($dateFrom, $dateTo);
        return $this->getSourcesByIdsOrSources($invoiceSourceType, $ids);
    }
    return array();
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
    // @todo: not by ref + also pass result.
    Hook::exec('actionAcumulusInvoiceSent', array('invoice' => &$invoice, 'source' => $invoiceSource), null, true);
  }

}
