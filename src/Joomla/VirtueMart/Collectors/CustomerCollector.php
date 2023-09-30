<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\VirtueMart\Collectors;

use Siel\Acumulus\Data\AddressType;

/**
 * CustomerCollector for Magento.
 */
class CustomerCollector extends \Siel\Acumulus\Collectors\CustomerCollector
{
    /**
     * Returns the value {@see AddressType::Invoice}}, VM cannot be configured for this
     * aspect.
     */
    protected function getVatBasedOn(): string
    {
        return AddressType::Invoice;
    }
}
