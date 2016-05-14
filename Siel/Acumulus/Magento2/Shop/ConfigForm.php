<?php
namespace Siel\Acumulus\Magento2\Shop;

use Siel\Acumulus\Magento2\Helpers\Registry;
use Siel\Acumulus\Shop\ConfigForm as BaseConfigForm;

/**
 * Class ConfigForm processes and builds the settings form page for the
 * Magento 2 Acumulus module.
 */
class ConfigForm extends BaseConfigForm
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

    /**
     * {@inheritdoc}
     */
    protected function getShopOrderStatuses()
    {
        /** @var \Magento\Sales\Model\Order\Status $model */
        $model = Registry::getInstance()->create('Magento\Sales\Model\Order\Status');
        $items = $model->getResourceCollection()->getData();
        $result = array();
        foreach ($items as $item) {
            $result[reset($item)] = next($item);
        }
        return $result;
    }
}