<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Data\AcumulusObject;

class InvoiceAddressCollector extends AddressCollector
{
    public function collect(array $propertySources, array $fieldDefinitions): AcumulusObject
    {
        // @todo: prioritise invoice address fields in the property sources,
        //   and/or use the field definitions for the invoice address.
        return parent::collect($propertySources, $fieldDefinitions);
    }

}
