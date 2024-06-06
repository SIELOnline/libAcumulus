<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Collectors;

use Siel\Acumulus\Collectors\CustomerCollector as BaseCustomerCollector;
use Siel\Acumulus\Data\AddressType;

/**
 * CustomerCollector for PrestaShop.
 */
class CustomerCollector extends BaseCustomerCollector
{
    /**
     * {@inheritDoc}
     *
     * In OpenCart this is not a setting but a property per tax rate per tax class. This
     * property can have one of the following values: 'shipping', 'payment', or 'store'.
     *
     * @todo: So, this cannot for all possible situations unambiguously be mapped to the
     *   way Acumulus handles this. Determine this dynamically when collecting and
     *   completing an invoice.
     */
    protected function getVatBasedOn(): string
    {
        return 'store';
    }

    protected function getVatBasedOnMapping(): array
    {
        return [
                'shipping' => AddressType::Shipping,
                'payment' => AddressType::Invoice,
                'store' => AddressType::Store,
            ] + parent::getVatBasedOnMapping();
    }
}
