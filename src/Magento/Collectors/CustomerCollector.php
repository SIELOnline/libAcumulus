<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Collectors;

use Magento\Store\Model\ScopeInterface;
use Magento\Tax\Model\Config as MagentoTaxConfig;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Meta;

/**
 * CustomerCollector for Magento.
 */
class CustomerCollector extends \Siel\Acumulus\Collectors\CustomerCollector
{
    use MagentoRegistryTrait;

    /**
     * @param \Siel\Acumulus\Data\Customer $acumulusObject
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        $vatBasedOn = $this->getVatBasedOn();
        $acumulusObject->metadataSet(Meta::VatBasedOnShop, $vatBasedOn);
        $taxBasedOnMapping = [
            'shipping' => AddressType::Shipping,
            'billing' => AddressType::Invoice,
            'origin' => null,
        ];
        $acumulusObject->setVatAddress($taxBasedOnMapping[$vatBasedOn] ?? null);
    }

    /**
     * Returns the setting that determines which address to use for tax calculations.
     */
    protected function getVatBasedOn(): string
    {
        return $this->getScopeConfig()->getValue(MagentoTaxConfig::CONFIG_XML_PATH_BASED_ON, ScopeInterface::SCOPE_STORE);
    }
}
