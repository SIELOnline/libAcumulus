<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Data\AcumulusObject;

class ShippingAddressCollector extends AddressCollector
{
    public function collect(array $propertySources, array $fieldDefinitions): AcumulusObject
    {
        // @todo: prioritise shipping address fields in the property sources,
        //   and/or use the field definitions for the shipping address.
        return parent::collect($propertySources, $fieldDefinitions);
    }

}
