<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Helpers;

use Siel\Acumulus\Helpers\Event as BaseEvent;

use function strlen;

/**
 * Event implements {@see \Siel\Acumulus\Helpers\Event} for OpenCart.
 */
class Event extends BaseEvent
{
    protected function getEventName(string $methodName): string
    {
        return lcfirst(substr($methodName, strlen('trigger')));
    }

    protected function triggerEvent(string $eventName, array $args): void
    {
        $moment = str_ends_with($eventName,'Before') ? 'before' : 'after';
        $eventName = substr($eventName, -strlen($moment));
        $route = Registry::getInstance()->getAcumulusTrigger($eventName, $moment);
        $this->getEvent()->trigger($route, $args);
    }

    /**
     * Wrapper around the event class instance.
     *
     * @return \Opencart\System\Engine\Event|\Event|\Light_Event
     *   [SIEL #194403]: https://lightning.devs.mx/ defines its own event class.
     *
     * @noinspection PhpUndefinedClassInspection \Light_Event is from a 3rd party module.
     * @noinspection PhpMissingReturnTypeInspection: return type differs for OC3 and OC4.
     */
    protected function getEvent()
    {
        return $this->getRegistry()->event;
    }

    /**
     * Wrapper method that returns the OpenCart registry class.
     */
    protected function getRegistry(): Registry
    {
        return Registry::getInstance();
    }
}
