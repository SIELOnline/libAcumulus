<?php
namespace Siel\Acumulus\Magento\Magento2\Shop;

use Siel\Acumulus\Magento\Magento2\Helpers\Registry;
use Siel\Acumulus\Magento\Shop\BatchForm as BaseBatchForm;

/**
 * Provides the Batch send form handling for the Magento Acumulus module.
 */
class BatchForm extends BaseBatchForm
{
    public function getShopDateFormat()
    {
        return $this->getTimezone()->getDateFormat(\IntlDateFormatter::SHORT);
    }

    /**
     * Get locale
     *
     * @return \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    protected function getTimezone()
    {
        return Registry::getInstance()->getObjectManager()->get('Magento\Framework\Stdlib\DateTime\TimezoneInterface');
    }
}
