<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Shop;

use Siel\Acumulus\Shop\SettingsForm as BaseSettingsForm;
use Siel\Acumulus\Tag;

use function array_key_exists;

/**
 * SettingsForm provides PrestaShop specific handling for the Settings form.
 *
 * @noinspection PhpUnused
 */
class SettingsForm extends BaseSettingsForm
{
    /**
     * {@inheritdoc}
     *
     * This override ensures that the password value is filled on submit with
     * its current value when the user did not fill it in (not fill it = leave
     * unchanged).
     */
    protected function setSubmittedValues(): void
    {
        parent::setSubmittedValues();
        if (array_key_exists(Fld::Password, $this->submittedValues) && $this->submittedValues[Fld::Password] === '') {
            $credentials = $this->acumulusConfig->getCredentials();
            if (!empty($credentials[Fld::Password])) {
                $this->submittedValues[Fld::Password] = $credentials[Fld::Password];
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
        if (array_key_exists('triggerOrderStatus', $result)) {
            $result['triggerOrderStatus[]'] = $result['triggerOrderStatus'];
        }
        return $result;
    }
}
