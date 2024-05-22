<?php
/**
 * @noinspection PhpUndefinedClassInspection \Joomla\CMS\Application\CMSApplicationInterface is J4 only
 * @noinspection PhpDeprecationInspection @todo: Method 'triggerEvent' is deprecated (in J4).
 */

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\Helpers;

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
    /**
     * @throws \Exception
     */
    public function triggerInvoiceCreateBefore(Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        PluginHelper::importPlugin('acumulus');
        $this->getCMSApplication()->triggerEvent('onAcumulusInvoiceCreateBefore', [$invoiceSource, $localResult]);
    }

    /**
     * @throws \Exception
     */
    public function triggerInvoiceCollectAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        PluginHelper::importPlugin('acumulus');
        $this->getCMSApplication()->triggerEvent('onAcumulusInvoiceCollectAfter', [$invoice, $invoiceSource, $localResult]);
    }

    /**
     * @throws \Exception
     */
    public function triggerInvoiceSendBefore(Invoice $invoice, InvoiceAddResult $localResult): void
    {
        PluginHelper::importPlugin('acumulus');
        $this->getCMSApplication()->triggerEvent('onAcumulusInvoiceSendBefore', [$invoice, $localResult]);
    }

    /**
     * @throws \Exception
     */
    public function triggerInvoiceSendAfter(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $result): void
    {
        PluginHelper::importPlugin('acumulus');
        $this->getCMSApplication()->triggerEvent('onAcumulusInvoiceSendAfter', [$invoice, $invoiceSource, $result]);
    }

    /**
     * Description.
     *
     * @return \Joomla\CMS\Application\CMSApplicationInterface|\Joomla\CMS\Application\BaseApplication
     *   Description.
     *
     * @throws \Exception
     *
     * @noinspection PhpReturnDocTypeMismatchInspection J3 vc J4
     */
    private function getCMSApplication()
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return Factory::getApplication();
    }
}
