<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Collectors;

use Siel\Acumulus\Collectors\CustomerCollector as BaseCustomerCollector;
use Siel\Acumulus\Data\AddressType;

/**
 * CustomerCollector for WooCommerce.
 */
class CustomerCollector extends BaseCustomerCollector
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
