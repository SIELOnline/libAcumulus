<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Helpers;

use Hook;
use Siel\Acumulus\Helpers\Event as BaseEvent;

/**
 * Event implements {@see \Siel\Acumulus\Helpers\Event} for PrestaShop.
 */
class Event extends BaseEvent
{
    protected function getEventName(string $methodName): string
    {
        return str_replace('trigger', 'actionAcumulus', $methodName);
    }

    protected function triggerEvent(string $eventName, array $args): void
    {
        Hook::exec($eventName, $args);
    }
}
