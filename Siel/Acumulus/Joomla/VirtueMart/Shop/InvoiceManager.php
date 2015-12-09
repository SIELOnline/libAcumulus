<?php
namespace Siel\Acumulus\Joomla\VirtueMart\Shop;

use DateTime;
use \Siel\Acumulus\Invoice\Source as BaseSource;
use \Siel\Acumulus\Joomla\Shop\InvoiceManager as BaseInvoiceManager;
use Siel\Acumulus\Joomla\VirtueMart\Invoice\Source;

class InvoiceManager extends BaseInvoiceManager {

  /**
   * {@inheritdoc}
   */
  protected function getSourceById($invoiceSourceType, $sourceId) {
    return new Source($invoiceSourceType, $sourceId);
  }

  /**
   * {@inheritdoc}
   *
   * This override only returns order as supported invoice source type.
   *
   * Note: the VMInvoice extension seems to offer credit notes, but for now we
   *   do not support them.
   */
  public function getSupportedInvoiceSourceTypes() {
    return array(
      Source::Order,
      //Source::CreditNote,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getInvoiceSourcesByIdRange($invoiceSourceType, $InvoiceSourceIdFrom, $InvoiceSourceIdTo) {
    $query = sprintf("select virtuemart_order_id
			from #__virtuemart_orders
			where virtuemart_order_id between %d and %d",
      $InvoiceSourceIdFrom, $InvoiceSourceIdTo);
    return $this->getSourcesByQuery($invoiceSourceType, $query);
  }

  /**
   * {@inheritdoc}
   *
   * By default, VirtueMart order numbers are non sequential random strings.
   * So getting a range is not logical. However, extensions exists that do
   * introduce sequential order numbers, E.g:
   * http://extensions.joomla.org/profile/extension/extension-specific/virtuemart-extensions/human-readable-order-numbers
   */
  public function getInvoiceSourcesByReferenceRange($invoiceSourceType, $InvoiceSourceReferenceFrom, $InvoiceSourceReferenceTo) {
    $query = sprintf("select virtuemart_order_id
			from #__virtuemart_orders
			where order_number between '%s' and '%s'",
      $this->getDb()->escape($InvoiceSourceReferenceFrom),
      $this->getDb()->escape($InvoiceSourceReferenceTo)
    );
    return $this->getSourcesByQuery($invoiceSourceType, $query);
  }

  /**
   * {@inheritdoc}
   */
  public function getInvoiceSourcesByDateRange($invoiceSourceType, DateTime $dateFrom, DateTime $dateTo) {
    $dateFrom = $this->getSqlDate($dateFrom);
    $dateTo = $this->getSqlDate($dateTo);
    $query = sprintf("select virtuemart_order_id
			from #__virtuemart_orders
			where modified_on between '%s' and '%s'",
      $this->toSql($dateFrom), $this->toSql($dateTo));
    return $this->getSourcesByQuery($invoiceSourceType, $query);
  }

  protected function triggerInvoiceCreated(array &$invoice, BaseSource $invoiceSource) {
    // @todo: find out about VM events.
    parent::triggerInvoiceCreated($invoice, $invoiceSource);
  }

  protected function triggerInvoiceCompleted(array &$invoice, BaseSource $invoiceSource) {
    parent::triggerInvoiceCompleted($invoice, $invoiceSource);
  }

  protected function triggerInvoiceSent(array $invoice, BaseSource $invoiceSource, array $result) {
    parent::triggerInvoiceSent($invoice, $invoiceSource, $result);
  }
}
