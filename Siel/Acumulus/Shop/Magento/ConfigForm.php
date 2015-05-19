<?php
namespace Siel\Acumulus\Shop\Magento;

use Mage;
use Siel\Acumulus\Shop\ConfigForm as BaseConfigForm;

/**
 * Class ConfigForm processes and builds the settings form page for the
 * Magento Acumulus module.
 */
class ConfigForm extends BaseConfigForm {

  /**
   * {@inheritdoc}
   */
  protected function getShopOrderStatuses() {
    $items = Mage::getModel('sales/order_status')->getResourceCollection()->getData();
    $result = array();
    foreach ($items as $item) {
      $result[reset($item)] = next($item);
    }
    return $result;
  }

}
