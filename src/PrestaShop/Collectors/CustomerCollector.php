<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Collectors;

use Configuration;
use Siel\Acumulus\Data\AddressType;

/**
 * CustomerCollector for WooCommerce.
 */
class CustomerCollector extends \Siel\Acumulus\Collectors\CustomerCollector
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
