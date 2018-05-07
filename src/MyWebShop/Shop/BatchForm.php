<?php
namespace Siel\Acumulus\MyWebShop\Shop;

use Siel\Acumulus\Shop\BatchForm as BaseBatchForm;

/**
 * Provides the Batch send form handling for the VirtueMart Acumulus module.
 */
class BatchForm extends BaseBatchForm
{
    /**
     * {@inheritdoc}
     */
    protected function setFormValues()
    {
        parent::setFormValues();

        // Prepend (checked) checkboxes with their collection name.
        foreach ($this->getCheckboxKeys() as $checkboxName => $collectionName) {
            if (isset($this->formValues[$checkboxName])) {
                $this->formValues["{$collectionName}_{$checkboxName}"] = $this->formValues[$checkboxName];
            }
        }
    }
}
