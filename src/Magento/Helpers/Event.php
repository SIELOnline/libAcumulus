<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Helpers;

use Magento\Framework\Event\ManagerInterface;
use Siel\Acumulus\Helpers\Event as BaseEvent;

/**
 * Event implements {@see \Siel\Acumulus\Helpers\Event} for Magento.
 */
class Event extends BaseEvent
{
    protected function getEventName(string $methodName): string
    {
        return 'acumulus' . strtolower(preg_replace('/([A-Z])/', '_$1', $methodName));
    }

    protected function triggerEvent(string $eventName, array $args): void
    {
        $this->getEventManager()->dispatch($eventName, $args);
    }

    private function getEventManager(): ManagerInterface
    {
        return Registry::getInstance()->get(ManagerInterface::class);
    }
}
