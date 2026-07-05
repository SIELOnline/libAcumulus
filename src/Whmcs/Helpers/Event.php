<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Helpers;

use Siel\Acumulus\Helpers\Event as BaseEvent;

/**
 * Event implements {@see \Siel\Acumulus\Helpers\Event} for WHMCS.
 */
class Event extends BaseEvent
{
    protected function getEventName(string $methodName): string
    {
        return str_replace('trigger', 'Acumulus', $methodName);
    }

    protected function triggerEvent(string $eventName, array $args): void
    {
        run_hook($eventName, $args);
    }
}
