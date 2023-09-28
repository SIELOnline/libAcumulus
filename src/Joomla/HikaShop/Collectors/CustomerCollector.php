<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\HikaShop\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Meta;

/**
 * CustomerCollector for Magento.
 */
class CustomerCollector extends \Siel\Acumulus\Collectors\CustomerCollector
{
    /**
     * @param \Siel\Acumulus\Data\Customer $acumulusObject
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        parent::collectLogicFields($acumulusObject);
        $vatBasedOn = $this->getVatBasedOn();
        $acumulusObject->metadataSet(Meta::VatBasedOnShop, $vatBasedOn);
        $taxBasedOnMapping = [
            'shipping' => AddressType::Shipping,
            'billing' => AddressType::Invoice,
        ];
        $acumulusObject->setMainAddress($taxBasedOnMapping[$vatBasedOn] ?? null);
    }

    /**
     * Returns the value of the setting indicating which address is used for tax
     * calculations: billing or shipping
     */
    protected function getVatBasedOn(): string
    {
        return hikashop_config()->get($this->getContainer()->getShopCapabilities()->getFiscalAddressSetting(), 'shipping');
    }
}
