<?php
namespace Siel\Acumulus\MyWebShop\Shop;

use Siel\Acumulus\Shop\AdvancedConfigForm as BaseAdvancedConfigForm;

/**
 * Class ConfigForm processes and builds the settings form page for the
 * MyWebShop Acumulus module.
 */
class AdvancedConfigForm extends BaseAdvancedConfigForm
{
    /**
     * {@inheritdoc}
     *
     * @todo: adapt to the way you can find out in MyWebshop if a form was submitted. Remove if there's no way other than to check for $_SERVER['REQUEST_METHOD']
     */
    public function isSubmitted()
    {
        return Tools::isSubmit('submitAdd');
    }

    /**
     * {@inheritdoc}
     *
     * This override ensures that array values are passed with the correct key
     * to the PS form renderer.
     */
    public function getFormValues()
    {
        $result = parent::getFormValues();

        $result['triggerOrderStatus[]'] = $result['triggerOrderStatus'];
        return $result;
    }

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
