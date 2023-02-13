<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Api;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\AcumulusProperty;
use Siel\Acumulus\Data\Address;
use Siel\Acumulus\Fld;

/**
 * Creates an {@see Address} object.
 */
class AddressCollector extends Collector
{
    protected function getAcumulusObjectType(): string
    {
        return 'Address';
    }

    /**
     * @param \Siel\Acumulus\Data\Address $acumulusObject
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        /** @var \Siel\Acumulus\Invoice\Source $invoiceSource */
        $invoiceSource = $this->propertySources['invoiceSource'];
        $acumulusObject->setCountryCode($invoiceSource->getCountryCode());
        // Add 'nl' as default country code.
        $acumulusObject->setCountryCode('nl', AcumulusProperty::Set_NotOverwrite);
        // @todo: how to handle country name?: collect and let completor decide whether to use it or not.
        //$customer->setCountry($this->countries->getCountryName($customer[Fld::CountryCode]), AcumulusProperty::Set_NotEmpty);

    }
}
