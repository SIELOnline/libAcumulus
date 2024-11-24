<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\Helpers;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Helpers\Event as EventInterface;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Source;
use Siel\Joomla\Component\Acumulus\Administrator\Extension\AcumulusComponent;

/**
 * Event implements the {@see \Siel\Acumulus\Helpers\Event} interface for Joomla.
 */
class Event implements EventInterface
{
    public function triggerInvoiceCreateBefore(Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        $this->triggerEvent('onAcumulusInvoiceCreateBefore', compact('invoiceSource', 'localResult'));
    }

    public function triggerLineCollectBefore(Line $line, PropertySources $propertySources): void
    {
        $this->triggerEvent('onAcumulusLineCollectBefore', compact('line', 'propertySources'));
    }

    public function triggerLineCollectAfter(Line $line, PropertySources $propertySources): void
    {
        $this->triggerEvent('onAcumulusLineCollectAfter', compact('line', 'propertySources'));
    }

    public function triggerInvoiceCollectAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        $this->triggerEvent('onAcumulusInvoiceCollectAfter', compact('invoice', 'invoiceSource', 'localResult'));
    }

    public function triggerInvoiceSendBefore(Invoice $invoice, InvoiceAddResult $localResult): void
    {
        $this->triggerEvent('onAcumulusInvoiceSendBefore', compact('invoice', 'localResult'));
    }

    public function triggerInvoiceSendAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $result): void
    {
        $this->triggerEvent('onAcumulusInvoiceSendAfter', compact('invoice', 'invoiceSource', 'result'));
    }

    private function triggerEvent(string $eventName, array $params): void
    {
        PluginHelper::importPlugin('acumulus');
        $params['subject'] = $this->getAcumulusComponent();
        $event = AbstractEvent::create($eventName, $params);
        // @todo: in Joomla 6 interface CMSApplicationInterface will no longer extend
        //   EventAwareInterface. Replacement is not yet clear to me.
        $this->getCMSApplication()->getDispatcher()->dispatch($eventName, $event);
    }

    private function getAcumulusComponent(): AcumulusComponent
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getCMSApplication()->bootComponent('acumulus');
    }

    private function getCMSApplication(): CMSApplicationInterface
    {
        /** @noinspection PhpUnhandledExceptionInspection  won't fail, application has started. */
        return Factory::getApplication();
    }
}
