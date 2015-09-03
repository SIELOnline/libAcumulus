<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use Context;
use OrderState;
use Siel\Acumulus\Shop\ConfigForm as BaseConfigForm;
use Siel\Acumulus\Shop\ConfigInterface;

/**
 * Class ConfigForm processes and builds the settings form page for the
 * PrestaShop Acumulus module.
 */
class ConfigForm extends BaseConfigForm {

  /**
   * {@inheritdoc}
   */
  protected function getShopOrderStatuses() {
    $result = array_values(OrderState::getOrderStates((int) Context::getContext()->language->id));

    return $result;
  }

  /**
   * {@inheritdoc}
   *
   * This override removes the 'Use invoice sent' option as WC does not have
   * an event on creating/sending the invoice.
   * @todo: check this.
   */
  protected function getTriggerInvoiceSendEventOptions() {
    $result = parent::getTriggerInvoiceSendEventOptions();
    unset($result[ConfigInterface::TriggerInvoiceSendEvent_InvoiceCreate]);
    return $result;
  }

}
