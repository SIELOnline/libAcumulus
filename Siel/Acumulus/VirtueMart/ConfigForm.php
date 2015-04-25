<?php
namespace Siel\Acumulus\VirtueMart;

use JSession;
use JText;
use Siel\Acumulus\Shop\ConfigForm as BaseConfigForm;
use Siel\Acumulus\Shop\ConfigInterface;
use VmModel;

/**
 * Class ConfigForm processes and builds the settings form page for the
 * VirtueMart Acumulus module.
 */
class ConfigForm extends BaseConfigForm {

  /**
   * {@inheritdoc}
   *
   * This override checks the Joomla form token:
   * https://docs.joomla.org/How_to_add_CSRF_anti-spoofing_to_forms
   */
  protected function systemValidate() {
    return JSession::checkToken();
  }

  /**
   * {@inheritdoc}
   */
  protected function getShopOrderStatuses() {
    /** @var \VirtueMartModelOrderstatus $orderstatusModel */
    $orderstatusModel = VmModel::getModel('orderstatus');
    $orderStates = $orderstatusModel->getOrderStatusNames();
    foreach ($orderStates as $code => &$value) {
      $value = \JText::_($value['order_status_name']);
    }
    return $orderStates;
  }

  /**
   * {@inheritdoc}
   */
  protected function getTriggerInvoiceSendEventOptions() {
    $result = parent::getTriggerInvoiceSendEventOptions();
    // For now we don't support automatic sending.
    // @todo: find out if and how to implement automatic sending. This is for the next alpha or beta release.
    unset($result[ConfigInterface::TriggerInvoiceSendEvent_OrderStatus]);
    unset($result[ConfigInterface::TriggerInvoiceSendEvent_InvoiceCreate]);
    return $result;
  }

}
