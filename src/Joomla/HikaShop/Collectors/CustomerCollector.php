<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\HikaShop\Collectors;

use Siel\Acumulus\Data\AddressType;

/**
 * CustomerCollector for Magento.
 */
class CustomerCollector extends \Siel\Acumulus\Collectors\CustomerCollector
{
    protected function getVatBasedOn(): string
    {
        return hikashop_config()->get($this->getContainer()->getShopCapabilities()->getFiscalAddressSetting(), 'shipping');
    }

    protected function getVatBasedOnMapping(): array
    {
        return [
                'shipping' => AddressType::Shipping,
                'billing' => AddressType::Invoice,
            ] + parent::getVatBasedOnMapping();
    }
}
