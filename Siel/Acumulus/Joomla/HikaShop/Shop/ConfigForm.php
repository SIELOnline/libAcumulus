<?php
namespace Siel\Acumulus\Joomla\HikaShop\Shop;

use JText;
use Siel\Acumulus\Joomla\Shop\ConfigForm as BaseConfigForm;
use Siel\Acumulus\Shop\ConfigInterface;

/**
 * Class ConfigForm processes and builds the settings form page for the
 * HikaShop Acumulus module.
 */
class ConfigForm extends BaseConfigForm {

  /**
   * {@inheritdoc}
   */
  protected function getShopOrderStatuses() {
    /** @var \hikashopCategoryClass $class */
    $class = hikashop_get('class.category');
    $statuses = $class->loadAllWithTrans('status');

    $orderStatuses = array();
    foreach ($statuses as $state) {
      $orderStatuses[$state->category_namekey] = $state->translation;
    }
    return $orderStatuses;
  }

  /**
   * {@inheritdoc}
   */
  protected function getTriggerInvoiceSendEventOptions() {
    $result = parent::getTriggerInvoiceSendEventOptions();
    // HikaShop does not have separate invoice entities, let alone a create
    // event for that.
    unset($result[ConfigInterface::TriggerInvoiceSendEvent_InvoiceCreate]);
    return $result;
  }

}
