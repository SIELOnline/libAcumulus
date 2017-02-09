<?php
namespace Siel\Acumulus\Magento\Shop;

use Siel\Acumulus\Shop\AdvancedConfigForm as BaseConfigForm;

/**
 * Class AdvancedConfigForm processes and builds the settings form page for the
 * Magento Acumulus module.
 */
class AdvancedConfigForm extends BaseConfigForm
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
                if (array_key_exists($collectionName, $this->formValues) && is_array($this->formValues[$collectionName])) {
                    $this->formValues[$collectionName][] = $checkboxName;
                } else {
                    $this->formValues[$collectionName] = array($checkboxName);
                }
            }
        }
    }
}
