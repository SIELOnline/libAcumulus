<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use Siel\Acumulus\Shop\ConfigForm as BaseConfigForm;
use Siel\Acumulus\Tag;

/**
 * Class ConfigForm processes and builds the settings form page for the
 * PrestaShop Acumulus module.
 */
class ConfigForm extends BaseConfigForm
{
    /**
     * {@inheritdoc}
     *
     * This override ensures that the password value is filled on submit with
     * its current value when the user did not fill it in (not fill it = leave
     * unchanged).
     */
    protected function setSubmittedValues()
    {
        parent::setSubmittedValues();
        if (array_key_exists(Tag::Password, $this->submittedValues) && $this->submittedValues[Tag::Password] === '') {
            $credentials = $this->acumulusConfig->getCredentials();
            if (!empty($credentials[Tag::Password])) {
                $this->submittedValues[Tag::Password] = $credentials[Tag::Password];
            }
        }
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
        $result['foreignVatClasses[]'] = $result['foreignVatClasses'];
        $result['triggerOrderStatus[]'] = $result['triggerOrderStatus'];
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override returns the config form. At the minimum, this includes the
     * account settings. If these are OK, the other settings are included as
     * well.
     */
    public function getFieldDefinitions()
    {
        $result = parent::getFieldDefinitions();

        // Add icons.
        if (isset($result['accountSettingsHeader'])) {
            $result['accountSettingsHeader']['icon'] = 'icon-user';
            if (isset($result['accountSettingsHeader']['fields'][Tag::Password])) {
                $result['accountSettingsHeader']['fields'][Tag::Password]['attributes']['required'] = false;
            }
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
