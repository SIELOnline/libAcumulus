<?php
namespace Siel\Acumulus\WooCommerce\Shop;

use DateTime;
use \Siel\Acumulus\Invoice\Source as BaseSource;
use \Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;

class InvoiceManager extends BaseInvoiceManager {

  /**
   * {@inheritdoc}
   */
  public function getInvoiceSourcesByIdRange($invoiceSourceType, $InvoiceSourceIdFrom, $InvoiceSourceIdTo) {
    $field = 'entity_id';
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
   * This Magento override dispatches the 'acumulus_invoice_created' event.
   */
  protected function triggerInvoiceCreated(array &$invoice, BaseSource $invoiceSource) {
    $transportObject = new Varien_Object(array('invoice' => $invoice));
    Mage::dispatchEvent('acumulus_invoice_created', array('transport_object' => $transportObject, 'source' => $invoiceSource));
  }

  /**
   * {@inheritdoc}
   *
   * This Magento override dispatches the 'acumulus_invoice_completed' event.
   */
  protected function triggerInvoiceCompleted(array &$invoice, BaseSource $invoiceSource) {
    $transportObject = new Varien_Object(array('invoice' => $invoice));
    Mage::dispatchEvent('acumulus_invoice_completed', array('transport_object' => $transportObject, 'source' => $invoiceSource));
  }

  /**
   * {@inheritdoc}
   *
   * This Magento override dispatches the 'acumulus_invoice_sent' event.
   */
  protected function triggerInvoiceSent(array $invoice, BaseSource $invoiceSource, array $result) {
    Mage::dispatchEvent('acumulus_invoice_sent', array('invoice' => $invoice, 'source' => $invoiceSource, 'result' => $result));
  }

}
