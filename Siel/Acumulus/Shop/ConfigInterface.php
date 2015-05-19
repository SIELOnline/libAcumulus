<?php
namespace Siel\Acumulus\Shop;

/**
 * Defines an interface to retrieve shop specific configuration settings.
 *
 * Configuration is stored in the host environment, normally a web shop.
 * This interface abstracts from how a specific web shop does so.
 */
interface ConfigInterface {
  // Web shop configuration related constants.
  const TriggerInvoiceSendEvent_None = 0;
  const TriggerInvoiceSendEvent_OrderStatus = 1;
  const TriggerInvoiceSendEvent_InvoiceCreate = 2;

  const InvoiceNrSource_ShopInvoice = 1;
  const InvoiceNrSource_ShopOrder = 2;
  const InvoiceNrSource_Acumulus = 3;

  const InvoiceDate_InvoiceCreate = 1;
  const InvoiceDate_OrderCreate = 2;
  const InvoiceDate_Transfer = 3;

  /**
   * Returns the set of settings related to adding an invoice.
   *
   * @return array
   *   A keyed array with the keys:
   *   - invoiceNrSource
   *   - dateToUse
   *   - triggerInvoiceSendEvent
   *   - triggerOrderStatus
   */
  public function getShopSettings() ;

}
