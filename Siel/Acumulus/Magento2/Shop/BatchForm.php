<?php
namespace Siel\Acumulus\Magento2\Shop;

use Siel\Acumulus\Magento2\Helpers\Registry;
use Siel\Acumulus\Shop\BatchForm as BaseBatchForm;

/**
 * Provides the Batch send form handling for the Magento 2 Acumulus module.
 */
class BatchForm extends BaseBatchForm
{
    /**
     * {@inheritdoc}
     */
    protected function setFormValues()
    {
        parent::setFormValues();

        // Group (checked) checkboxes into their collections.
        foreach ($this->getCheckboxKeys() as $checkboxName => $collectionName) {
            if (!empty($this->formValues[$checkboxName])) {
                // Handle the case where $collectionName and $checkboxName are the same.
                if (array_key_exists($collectionName,$this->formValues) && is_array($this->formValues[$collectionName])) {
                    $this->formValues[$collectionName][] = $checkboxName;
                } else {
                    $this->formValues[$collectionName] = array($checkboxName);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * This override returns the default date format as set in the Magento
     * config.
     */
    public function getDateFormat()
    {
        $result = $this->getShopDateFormat();
        $result = str_replace(array('yyyy', 'MM', 'dd'), array('Y', 'm', 'd'),
            $result);
        return $result;
    }

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
