<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use Siel\Acumulus\Shop\AdvancedConfigForm as BaseAdvancedConfigForm;

/**
 * Provides PrestaShop specific handling for the Advanced config form.
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
        if (isset($result['accountSettings'])) {
            $result['accountSettings']['icon'] = 'icon-user';
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
        if (isset($result['invoiceSettings'])) {
            $result['invoiceSettings']['icon'] = 'icon-list-alt';
        }
        if (isset($result['optionsSettingsHeader'])) {
            $result['optionsSettingsHeader']['icon'] = 'icon-indent';
        }
        if (isset($result['emailAsPdfSettingsHeader'])) {
            $result['emailAsPdfSettingsHeader']['icon'] = 'icon-file-pdf-o';
        }
        if (isset($result['versionInformation'])) {
            $result['versionInformation']['icon'] = 'icon-info-circle';
        }

        return $result;
    }
}
