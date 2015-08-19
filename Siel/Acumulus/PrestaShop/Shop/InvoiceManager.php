<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use DateTime;
use Hook;
use Order;
use \Siel\Acumulus\Invoice\Source as BaseSource;
use \Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;

class InvoiceManager extends BaseInvoiceManager {

  /**
   * {@inheritdoc}
   */
  public function getInvoiceSourcesByIdRange($invoiceSourceType, $InvoiceSourceIdFrom, $InvoiceSourceIdTo) {
    $field = 'order_id';
    Order::getByReference('');
    $condition = array('from' => $InvoiceSourceIdFrom, 'to' => $InvoiceSourceIdTo);
    return $this->getByCondition($invoiceSourceType, $field, $condition);
  }

  /**
   * {@inheritdoc}
   */
  public function getInvoiceSourcesByReferenceRange($invoiceSourceType, $InvoiceSourceReferenceFrom, $InvoiceSourceReferenceTo) {
    $field = 'increment_id';
    $condition = array('from' => $InvoiceSourceReferenceFrom, 'to' => $InvoiceSourceReferenceTo);
    return $this->getByCondition($invoiceSourceType, $field, $condition);
  }

  /**
   * {@inheritdoc}
   */
  public function getInvoiceSourcesByDateRange($invoiceSourceType, DateTime $dateFrom, DateTime $dateTo) {
    $dateFrom = $this->getIsoDate($dateFrom);
    $dateTo = $this->getIsoDate($dateTo);
    $field = 'updated_at';
    $condition = array('from' => $dateFrom, 'to' => $dateTo);
    return $this->getByCondition($invoiceSourceType, $field, $condition);
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

}
