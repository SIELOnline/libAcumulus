<?php
namespace Siel\Acumulus\Magento\Shop;

use Mage;
use Mage_Core_Model_Locale;
use Siel\Acumulus\Shop\BatchForm as BaseBatchForm;

/**
 * Provides the Batch send form handling for the Magento Acumulus module.
 */
class BatchForm extends BaseBatchForm {

  /**
   * {@inheritdoc}
   *
   * This override returns the default date format as set in the Magento config.
   */
  public function getDateFormat() {
    $result = $this->getShopDateFormat();
    $result = str_replace(array('yyyy', 'MM', 'dd'), array('Y', 'm', 'd'), $result);
    return $result;
  }

  public function getShopDateFormat() {
    return Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT);
  }

}
