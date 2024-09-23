<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Collectors;

use Address;
use Siel\Acumulus\Collectors\CollectorManager as BaseCollectorManager;

/**
 * CollectorManager contains a PrestaShop specific override for
 * {@see \Siel\Acumulus\Collectors\CollectorManager::setPropertySourcesForSource()}.
 */
class CollectorManager extends BaseCollectorManager
{
    public function addShopPropertySources(): void
    {
        /** @var \Order $order */
        $order = $this->getPropertySources()->get('source')->getOrder()->getShopObject();
        $this->getPropertySources()->add('address_invoice', new Address($order->id_address_invoice));
        $this->getPropertySources()->add('address_shipping', new Address($order->id_address_delivery));
    }
}
