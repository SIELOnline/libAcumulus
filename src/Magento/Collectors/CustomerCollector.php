<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Collectors;

use Magento\Store\Model\ScopeInterface;
use Siel\Acumulus\Collectors\CustomerCollector as BaseCustomerCollector;
use Siel\Acumulus\Data\AddressType;

/**
 * CustomerCollector for Magento.
 */
class CustomerCollector extends BaseCustomerCollector
{
    use MagentoRegistryTrait;

    protected function getVatBasedOn(): string
    {
        return $this->getScopeConfig()->getValue(
            $this->getContainer()->getShopCapabilities()->getFiscalAddressSetting(),
            ScopeInterface::SCOPE_STORE
        );
    }

    protected function getVatBasedOnMapping(): array
    {
        return [
                'shipping' => AddressType::Shipping,
                'billing' => AddressType::Invoice,
                'origin' => null,
            ] + parent::getVatBasedOnMapping();
    }
}
