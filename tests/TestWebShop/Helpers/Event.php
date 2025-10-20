<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Helpers;

use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Helpers\Event as EventInterface;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Source;

/**
 * Event implements the {@see EventInterface Event interface} for the test webshop.
 */
class Event implements EventInterface
{
    public const INVOICE_CREATE_BEFORE = 'triggerInvoiceCreateBefore';
    public const INVOICE_COLLECT_AFTER = 'triggerInvoiceCollectAfter';
    public const INVOICE_CREATE_AFTER = 'triggerInvoiceCreateAfter';
    public const INVOICE_SEND_BEFORE = 'triggerInvoiceSendBefore';
    public const INVOICE_SEND_AFTER = 'triggerInvoiceSendAfter';
    public const LINE_COLLECT_BEFORE = 'triggerLineCollectBefore';
    public const LINE_COLLECT_AFTER = 'triggerLineCollectAfter';

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

    private static function triggerHooks(string $event, ...$args): void
    {
        foreach (self::$registeredHooks[$event] ?? [] as $hook) {
            $hook(...$args);
        }
    }

    public function triggerInvoiceCreateBefore(Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        self::triggerHooks(self::INVOICE_CREATE_BEFORE, $invoiceSource, $localResult);
    }

    public function triggerLineCollectBefore(Line $line, PropertySources $propertySources): void
    {
        self::triggerHooks(self::LINE_COLLECT_BEFORE, $line, $propertySources);
    }

    public function triggerLineCollectAfter(Line $line, PropertySources $propertySources): void
    {
        self::triggerHooks(self::LINE_COLLECT_AFTER, $line, $propertySources);
    }

    public function triggerInvoiceCollectAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        self::triggerHooks(self::INVOICE_COLLECT_AFTER, $invoice, $invoiceSource, $localResult);
    }

    public function triggerInvoiceCreateAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        self::triggerHooks(self::INVOICE_CREATE_AFTER, $invoice, $invoiceSource, $localResult);
    }

    public function triggerInvoiceSendBefore(Invoice $invoice, InvoiceAddResult $localResult): void
    {
        self::triggerHooks(self::INVOICE_SEND_BEFORE, $invoice, $localResult);
    }

    public function triggerInvoiceSendAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $result): void
    {
        self::triggerHooks(self::INVOICE_SEND_AFTER, $invoice, $invoiceSource, $result);
    }
}
