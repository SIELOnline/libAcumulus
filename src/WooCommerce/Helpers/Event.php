<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Helpers;

use Siel\Acumulus\Helpers\Event as BaseEvent;

/**
 * Event implements {@see \Siel\Acumulus\Helpers\Event} for WooCommerce.
 */
class Event extends BaseEvent
{
    protected function getEventName(string $methodName): string
    {
        return 'acumulus' . strtolower(preg_replace('/([A-Z])/', '_$1', $methodName));
    }

    protected function triggerEvent(string $eventName, array $args): void
    {
        do_action($eventName, ...array_values($args));
    }
}
