<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use Siel\Acumulus\Shop\AdvancedConfigForm as BaseAdvancedConfigForm;

/**
 * Class ConfigForm processes and builds the settings form page for the
 * PrestaShop Acumulus module.
 */
class AdvancedConfigForm extends BaseAdvancedConfigForm
{
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
        if (isset($result['configHeader'])) {
            $result['configHeader']['icon'] = 'icon-cogs';
        }
        if (isset($result['tokenHelpHeader'])) {
            $result['tokenHelpHeader']['icon'] = 'icon-question-circle';
        }
        if (isset($result['relationSettingsHeader'])) {
            $result['relationSettingsHeader']['icon'] = 'icon-users';
        }
        if (isset($result['invoiceSettingsHeader'])) {
            $result['invoiceSettingsHeader']['icon'] = 'icon-list-alt';
        }
        if (isset($result['optionsSettingsHeader'])) {
            $result['optionsSettingsHeader']['icon'] = 'icon-indent';
        }
        if (isset($result['emailAsPdfSettingsHeader'])) {
            $result['emailAsPdfSettingsHeader']['icon'] = 'icon-file-pdf-o';
        }
        if (isset($result['versionInformationHeader'])) {
            $result['versionInformationHeader']['icon'] = 'icon-info-circle';
        }

        return $result;
    }
}
