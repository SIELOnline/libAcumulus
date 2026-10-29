<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Collectors;

use Address;
use Siel\Acumulus\Collectors\CollectorManager as BaseCollectorManager;

/**
 * CollectorManager contains a PrestaShop specific override for
 * {@see \Siel\Acumulus\Collectors\CollectorManager::setPropertySourcesForSource()}.
 *
 * @noinspection PhpUnused  Instantiated via the Container
 */
class CollectorManager extends BaseCollectorManager
{
    public function addShopPropertySources(): void
    {
        /** @var \Order $order */
        $order = $this->getPropertySources()['source']->getOrder()->getShopObject();
        $this->getPropertySources()->address_invoice = new Address($order->id_address_invoice);
        $this->getPropertySources()->address_shipping = new Address($order->id_address_delivery);
    }
}
