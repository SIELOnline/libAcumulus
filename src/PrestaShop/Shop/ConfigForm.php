<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use Siel\Acumulus\Config\ConfigInterface;
use Siel\Acumulus\Config\ShopCapabilitiesInterface;
use Siel\Acumulus\Helpers\TranslatorInterface;
use Siel\Acumulus\Shop\ConfigForm as BaseConfigForm;
use Siel\Acumulus\Web\Service;
use Tools;

/**
 * Class ConfigForm processes and builds the settings form page for the
 * PrestaShop Acumulus module.
 */
class ConfigForm extends BaseConfigForm
{
    /** @var string */
    protected $moduleName = 'acumulus';

    /**
     * {@inheritdoc}
     *
     * This override uses the PS way of checking if a form is submitted.
     */
    public function isSubmitted()
    {
        return Tools::isSubmit('submit' . $this->moduleName);
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

    /**
     * {@inheritdoc}
     */
    public function getFieldDefinitions()
    {
        $result = parent::getFieldDefinitions();

        // Add icons.
        if (isset($result['accountSettingsHeader'])) {
            $result['accountSettingsHeader']['icon'] = 'icon-user';
        }
        if (isset($result['shopSettingsHeader'])) {
            $result['shopSettingsHeader']['icon'] = 'icon-shopping-cart';
        }
        if (isset($result['triggerSettingsHeader'])) {
            $result['triggerSettingsHeader']['icon'] = 'icon-exchange';
        }
        if (isset($result['invoiceSettingsHeader'])) {
            $result['invoiceSettingsHeader']['icon'] = 'icon-list-alt';
        }
        if (isset($result['paymentMethodAccountNumberFieldset'])) {
            $result['paymentMethodAccountNumberFieldset']['icon'] = 'icon-credit-card';
        }
        if (isset($result['paymentMethodCostCenterFieldset'])) {
            $result['paymentMethodCostCenterFieldset']['icon'] = 'icon-credit-card';
        }
        if (isset($result['emailAsPdfSettingsHeader'])) {
            $result['emailAsPdfSettingsHeader']['icon'] = 'icon-file-pdf-o';
        }
        if (isset($result['pluginSettingsHeader'])) {
            $result['pluginSettingsHeader']['icon'] = 'icon-puzzle-piece';
        }
        if (isset($result['versionInformationHeader'])) {
            $result['versionInformationHeader']['icon'] = 'icon-info-circle';
        }
        if (isset($result['advancedConfigHeader'])) {
            $result['advancedConfigHeader']['icon'] = 'icon-cogs';
        }

        return $result;
    }
}
