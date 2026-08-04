<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Helpers;

use Siel\Acumulus\Helpers\Event as BaseEvent;
use Siel\Whmcs\Acumulus\Acumulus;

/**
 * Event implements {@see \Siel\Acumulus\Helpers\Event} for WHMCS.
 *
 * Hooks from addons should contain the addon name as to distinghuish them from other
 * hooks. So, we replace 'trigger' (in the hook method name) with 'Acumulus'. E.g. method
 * {@see Event::triggerInvoiceSendBefore()} will run hook 'AcumulusInvoiceSendBefore'.
 */
class Event extends BaseEvent
{
    protected function getEventName(string $methodName): string
    {
        return str_replace('trigger', Acumulus::Name, $methodName);
    }

    protected function triggerEvent(string $eventName, array $args): void
    {
        run_hook($eventName, $args);
    }
}
