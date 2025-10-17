<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Helpers;

use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Helpers\Event as EventInterface;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Source;

/**
 * Event implements the {@see \Siel\Acumulus\Helpers\Event} interface for OpenCart.
 */
class Event implements EventInterface
{
    public function triggerInvoiceCreateBefore(Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        $route = Registry::getInstance()->getAcumulusTrigger('invoiceCreate', 'before');
        $args = compact('invoiceSource', 'localResult');
        $this->getEvent()->trigger($route, $args);
    }

    public function triggerLineCollectBefore(Line $line, PropertySources $propertySources): void
    {
        // @error: rename to lineCollect.
        $route = Registry::getInstance()->getAcumulusTrigger('itemLineCollect', 'before');
        $args = compact('line', 'propertySources');
        $this->getEvent()->trigger($route, $args);
    }

    public function triggerLineCollectAfter(Line $line, PropertySources $propertySources): void
    {
        // @error: rename to lineCollect.
        $route = Registry::getInstance()->getAcumulusTrigger('itemLineCollect', 'after');
        $args = compact('line', 'propertySources');
        $this->getEvent()->trigger($route, $args);
    }

    public function triggerInvoiceCollectAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        $route = Registry::getInstance()->getAcumulusTrigger('invoiceCollect', 'after');
        $args = compact('invoice', 'invoiceSource', 'localResult');
        $this->getEvent()->trigger($route, $args);
    }

    public function triggerInvoiceSendBefore(Invoice $invoice, InvoiceAddResult $localResult): void
    {
        $route = Registry::getInstance()->getAcumulusTrigger('invoiceSend', 'before');
        $args = compact('invoice', 'localResult');
        $this->getEvent()->trigger($route, $args);
    }

    public function triggerInvoiceSendAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $result): void
    {
        $route = Registry::getInstance()->getAcumulusTrigger('invoiceSend', 'after');
        $args = compact('invoice', 'invoiceSource', 'result');
        $this->getEvent()->trigger($route, $args);
    }

    /**
     * Wrapper around the event class instance.
     *
     * @return \Opencart\System\Engine\Event|\Event|\Light_Event
     *   [SIEL #194403]: https://lightning.devs.mx/ defines its own event class.
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
