<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Collectors;

use Configuration;
use Siel\Acumulus\Collectors\CustomerCollector as BaseCustomerCollector;
use Siel\Acumulus\Data\AddressType;

/**
 * CustomerCollector for PrestaShop.
 */
class CustomerCollector extends BaseCustomerCollector
{
    protected function getVatBasedOn(): string
    {
        return Configuration::get($this->getContainer()->getShopCapabilities()->getFiscalAddressSetting());
    }

    protected function getVatBasedOnMapping(): array
    {
        return [
                'id_address_delivery' => AddressType::Shipping,
                'id_address_invoice' => AddressType::Invoice,
            ] + parent::getVatBasedOnMapping();
    }
}
