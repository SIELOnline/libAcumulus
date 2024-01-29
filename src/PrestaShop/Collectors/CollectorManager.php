<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Collectors;

use Address;
use Siel\Acumulus\Collectors\CollectorManager as BaseCollectorManager;
use Siel\Acumulus\Invoice\Source;

/**
 * CollectorManager contains a PrestaShop specific override for
 * {@see \Siel\Acumulus\Collectors\CollectorManager::setPropertySourcesForSource()}.
 */
class CollectorManager extends BaseCollectorManager
{
    protected function setPropertySourcesForSource(Source $source): void
    {
        parent::setPropertySourcesForSource($source);

        /** @var \Order $order */
        $order = $source->getOrder();
        $this->addPropertySource('address_invoice', new Address($order->id_address_invoice));
        $this->addPropertySource('address_delivery', new Address($order->id_address_delivery));
    }
}
