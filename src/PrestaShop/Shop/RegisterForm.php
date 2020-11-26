<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use Siel\Acumulus\Shop\RegisterForm as BaseRegisterForm;

/**
 * Provides PrestaShop specific handling for the Register form.
 */
class RegisterForm extends BaseRegisterForm
{
    /**
     * {@inheritdoc}
     */
    public function getFieldDefinitions()
    {
        $result = parent::getFieldDefinitions();

        // Add icons.
        if (isset($result['congratulations'])) {
            $result['congratulations']['icon'] = 'icon-thumbs-up';
        }
        if (isset($result['loginDetails'])) {
            $result['loginDetails']['icon'] = 'icon-key';
        }
        if (isset($result['apiLoginDetails'])) {
            $result['apiLoginDetails']['icon'] = 'icon-key';
        }
        if (isset($result['whatsNext'])) {
            $result['whatsNext']['icon'] = 'icon-forward';
        }
        if (isset($result['versionInformation'])) {
            $result['versionInformation']['icon'] = 'icon-info-circle';
        }

        return $result;
    }
}
