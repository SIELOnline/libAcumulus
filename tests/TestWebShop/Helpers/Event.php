<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Helpers;

use Siel\Acumulus\Helpers\Event as BaseEvent;

/**
 * Event implements {@see Event} for the test webshop.
 */
class Event extends BaseEvent
{
    /**
     * @var callable[][]
     *   Keys:
     *   - 1st level: string: name of event (method name).
     *   - 2nd level: numeric index.
     *   Values:
     *   - 2nd level: callable: the hook itself.
     */
    public static array $registeredHooks = [];

    public static function registerHook(string $event, callable $hook): void
    {
        self::$registeredHooks[$event][] = $hook;
    }

    public static function unregisterHook(string $event, callable $hook): void
    {
        foreach (self::$registeredHooks[$event] as $index => $registeredHook) {
            if ($hook === $registeredHook) {
                unset(self::$registeredHooks[$event][$index]);
                return;
            }
        }
    }

    protected function getEventName(string $methodName): string
    {
        return $methodName;
    }

    protected function triggerEvent(string $eventName, array $args): void
    {
        foreach (self::$registeredHooks[$eventName] ?? [] as $hook) {
            $hook(...array_values($args));
        }
    }
}
