<?php
namespace Siel\Acumulus\WooCommerce\Shop;

use Siel\Acumulus\Shop\ConfigForm as BaseConfigForm;
use Siel\Acumulus\Shop\ConfigInterface;

/**
 * Class ConfigForm processes and builds the settings form page for the
 * WooCommerce Acumulus module.
 */
class ConfigForm extends BaseConfigForm {

  /**
   * {@inheritdoc}
   */
  protected function getShopOrderStatuses() {
    $result = array();

    $orderStatuses = wc_get_order_statuses();
    foreach ($orderStatuses as $key => $label) {
      $result[substr($key, strlen('wc-'))] = $label;
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   *
   * This override removes the 'Use invoice #' option as WC does not have
   * separate invoices.
   */
  protected function getInvoiceNrSourceOptions() {
    $result = parent::getInvoiceNrSourceOptions();
    unset($result[ConfigInterface::InvoiceNrSource_ShopInvoice]);
    return $result;
  }

  /**
   * {@inheritdoc}
   *
   * This override removes the 'Use invoice date' option as WC does not have
   * separate invoices.
   */
  protected function getDateToUseOptions() {
    $result = parent::getDateToUseOptions();
    unset($result[ConfigInterface::InvoiceDate_InvoiceCreate]);
    return $result;
  }

  /**
   * {@inheritdoc}
   *
   * This override removes the 'Use invoice sent' option as WC does not have
   * separate invoices, let alone an event on sending it.
   */
  protected function getTriggerInvoiceSendEventOptions() {
    $result = parent::getTriggerInvoiceSendEventOptions();
    unset($result[ConfigInterface::TriggerInvoiceSendEvent_InvoiceCreate]);
    return $result;
  }

}
