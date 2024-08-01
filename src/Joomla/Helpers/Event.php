<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\Helpers;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Helpers\Event as EventInterface;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Source;
use Siel\Joomla\Component\Acumulus\Administrator\Extension\AcumulusComponent;

/**
 * Event implements the Event interface for Joomla.
 */
class Event implements EventInterface
{
    /**
     * @throws \Exception
     */
    public function triggerInvoiceCreateBefore(Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        $this->triggerEvent('onAcumulusInvoiceCreateBefore', compact('invoiceSource', 'localResult'));
    }

    /**
     * @throws \Exception
     */
    public function triggerInvoiceCollectAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        $this->triggerEvent('onAcumulusInvoiceCollectAfter', compact('invoice', 'invoiceSource', 'localResult'));
    }

    /**
     * @throws \Exception
     */
    public function triggerInvoiceSendBefore(Invoice $invoice, InvoiceAddResult $localResult): void
    {
        $this->triggerEvent('onAcumulusInvoiceSendBefore', compact('invoice', 'localResult'));
    }

    /**
     * @throws \Exception
     */
    public function triggerInvoiceSendAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $result): void
    {
        $this->triggerEvent('onAcumulusInvoiceSendAfter', compact('invoice', 'invoiceSource', 'result'));
    }

    /**
     * @throws \Exception
     */
    private function triggerEvent(string $eventName, array $params): void
    {
        PluginHelper::importPlugin('acumulus');
        $params['subject'] = $this->getAcumulusComponent();
        $event = AbstractEvent::create($eventName, $params);
        // @todo: in Joomla 6 interface CMSApplicationInterface will no longer extend EventAwareInterface.
        $this->getCMSApplication()->getDispatcher()->dispatch($eventName, $event);
    }

    /**
     * @throws \Exception
     */
    private function getAcumulusComponent(): AcumulusComponent
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getCMSApplication()->bootComponent('acumulus');
    }

    /**
     * @throws \Exception
     */
    private function getCMSApplication(): CMSApplicationInterface
    {
        return Factory::getApplication();
    }
}
