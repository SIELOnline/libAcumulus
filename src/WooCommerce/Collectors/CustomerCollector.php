<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Meta;

/**
 * CustomerCollector for WooCommerce.
 */
class CustomerCollector extends \Siel\Acumulus\Collectors\CustomerCollector
{
    /**
     * @param \Siel\Acumulus\Data\Customer $acumulusObject
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        parent::collectLogicFields($acumulusObject);
        $taxBasedOn = $this->getVatBasedOn();
        $acumulusObject->metadataSet(Meta::VatBasedOnShop, $taxBasedOn);
        $taxBasedOnMapping = [
            'shipping' => AddressType::Shipping,
            'billing' => AddressType::Invoice,
            'base' => null,
        ];
        $acumulusObject->setMainAddress($taxBasedOnMapping[$taxBasedOn] ?? null);
    }

    /**
     * Returns the value of the setting indicating which address is used for tax
     * calculations.
     */
    protected function getVatBasedOn(): string
    {
        return get_option($this->getContainer()->getShopCapabilities()->getFiscalAddressSetting());
    }
}
