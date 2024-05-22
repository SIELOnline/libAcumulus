<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Helpers;

use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Helpers\Event as EventInterface;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Source;

/**
 * Event implements the Event interface for OpenCart.
 */
class Event implements EventInterface
{
    public function triggerInvoiceCreateBefore(Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        $route = Registry::getInstance()->getAcumulusTrigger('invoiceCreate', 'before');
        $args = ['source' => $invoiceSource, 'localResult' => $localResult];
        $this->getEvent()->trigger($route, $args);
    }

    public function triggerInvoiceCollectAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        $route = Registry::getInstance()->getAcumulusTrigger('invoiceCollect', 'after');
        $args = ['invoice' => $invoice, 'source' => $invoiceSource, 'localResult' => $localResult];
        $this->getEvent()->trigger($route, $args);
    }

    public function triggerInvoiceSendBefore(Invoice $invoice, InvoiceAddResult $localResult): void
    {
        $route = Registry::getInstance()->getAcumulusTrigger('invoiceSend', 'before');
        $args = ['invoice' => $invoice, 'localResult' => $localResult];
        $this->getEvent()->trigger($route, $args);
    }

    public function triggerInvoiceSendAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $result): void
    {
        $route = Registry::getInstance()->getAcumulusTrigger('invoiceSend', 'after');
        $args = ['invoice' => $invoice, 'source' => $invoiceSource, 'result' => $result];
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
