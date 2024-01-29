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
    public function setPropertySourcesForSource(Source $source): BaseCollectorManager
    {
        parent::setPropertySourcesForSource($source);

        /** @var \Order $order */
        $order = $source->getOrder()->getSource();
        $this->addPropertySource('address_invoice', new Address($order->id_address_invoice));
        $this->addPropertySource('address_shipping', new Address($order->id_address_delivery));

        return $this;
    }
}
