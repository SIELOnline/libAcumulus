<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use Siel\Acumulus\Shop\ConfigForm as BaseConfigForm;
use Siel\Acumulus\Tag;

/**
 * Provides PrestaShop specific handling for the Config form.
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
    public function getFormValues(): array
    {
        $result = parent::getFormValues();
        $result['euVatClasses[]'] = $result['euVatClasses'];
        $result['triggerOrderStatus[]'] = $result['triggerOrderStatus'];
        return $result;
    }
}
