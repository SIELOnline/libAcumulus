<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Shop;

use Siel\Acumulus\Shop\MappingsForm as BaseMappingsForm;

/**
 * MappingsForm contains WHMCS specific handling for the Mappings form.
 *
 * @noinspection PhpUnused
 */
class MappingsForm extends BaseMappingsForm
{
    use FormHandlingTrait;

    protected function getFieldDefinitions(): array
    {
        $this->hidden = [
            'configHeader',
            'shippingAddressMappingsHeader',
        ];
        return $this->removeHiddenFields(parent::getFieldDefinitions());
    }
}
