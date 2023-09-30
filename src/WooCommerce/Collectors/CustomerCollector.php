<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Collectors;

use Siel\Acumulus\Data\AddressType;

/**
 * CustomerCollector for WooCommerce.
 */
class CustomerCollector extends \Siel\Acumulus\Collectors\CustomerCollector
{
    protected function getVatBasedOn(): string
    {
        return get_option($this->getContainer()->getShopCapabilities()->getFiscalAddressSetting());
    }

    protected function getVatBasedOnMapping(): array
    {
        return [
                'shipping' => AddressType::Shipping,
                'billing' => AddressType::Invoice,
                'base' => null,
            ] + parent::getVatBasedOnMapping();
    }
}
