<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\Helpers;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Helpers\Event as EventInterface;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Source;

/**
 * Event implements the Event interface for Joomla.
 */
class Event implements EventInterface
{
    public function triggerInvoiceCreateBefore(Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        PluginHelper::importPlugin('acumulus');
        /** @noinspection PhpUndefinedMethodInspection  defined on EventAwareInterface which is implemented by CMSApplicationInterface */
        $this->getCMSApplication()->triggerEvent('onAcumulusInvoiceCreateBefore', [$invoiceSource, $localResult]);
    }

    public function triggerInvoiceCollectAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        PluginHelper::importPlugin('acumulus');
        /** @noinspection PhpUndefinedMethodInspection  defined on EventAwareInterface which is implemented by CMSApplicationInterface */
        $this->getCMSApplication()->triggerEvent('onAcumulusInvoiceCollectAfter', [$invoice, $invoiceSource, $localResult]);
    }

    public function triggerInvoiceSendBefore(Invoice $invoice, InvoiceAddResult $localResult): void
    {
        PluginHelper::importPlugin('acumulus');
        /** @noinspection PhpUndefinedMethodInspection  defined on EventAwareInterface which is implemented by CMSApplicationInterface */
        $this->getCMSApplication()->triggerEvent('onAcumulusInvoiceSendBefore', [$invoice, $localResult]);
    }

    public function triggerInvoiceSendAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $result): void
    {
        PluginHelper::importPlugin('acumulus');
        /** @noinspection PhpUndefinedMethodInspection  defined on EventAwareInterface which is implemented by CMSApplicationInterface */
        $this->getCMSApplication()->triggerEvent('onAcumulusInvoiceSendAfter', [$invoice, $invoiceSource, $result]);
    }

    private function getCMSApplication(): CMSApplicationInterface
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return Factory::getApplication();
    }
}
