<?php
namespace Siel\Acumulus\Magento\Magento1\Shop;

use Siel\Acumulus\Magento\Shop\BatchForm as BaseBatchForm;

/**
 * Provides the Batch send form handling for the Magento Acumulus module.
 */
class BatchForm extends BaseBatchForm
{
    public function getShopDateFormat()
    {
        return \Mage::app()->getLocale()->getDateFormat(\Mage_Core_Model_Locale::FORMAT_TYPE_SHORT);
    }
}
