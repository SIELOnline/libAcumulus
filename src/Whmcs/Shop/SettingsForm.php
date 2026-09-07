<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Shop;

use Siel\Acumulus\Shop\SettingsForm as BaseSettingsForm;

/**
 * SettingsForm provides WHMCS specific handling for the Settings form.
 *
 * @noinspection PhpUnused
 */
class SettingsForm extends BaseSettingsForm
{
    use FormHandlingTrait;

    protected function getFieldDefinitions(): array
    {
        $this->hidden = [
            'vatFreeClass',
            'zeroVatClass',
            'triggerOrderStatus',
            'mainAddress',
            'sendEmptyShipping',
        ];
        return $this->removeHiddenFields(parent::getFieldDefinitions());
    }
}
