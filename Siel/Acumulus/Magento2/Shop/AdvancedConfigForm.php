<?php
namespace Siel\Acumulus\Magento2\Shop;

use Siel\Acumulus\Shop\AdvancedConfigForm as BaseAdvancedConfigForm;

/**
 * Class AdvancedConfigForm processes and builds the advanced settings form page
 * for the Magento 2 Acumulus module.
 */
class AdvancedConfigForm extends BaseAdvancedConfigForm
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
                // Handle the case where $collectionName and $checkboxName are
                // the same.
                if (array_key_exists($collectionName, $this->formValues) && is_array($this->formValues[$collectionName])) {
                    $this->formValues[$collectionName][] = $checkboxName;
                } else {
                    $this->formValues[$collectionName] = array($checkboxName);
                }
            }
        }
    }
}
