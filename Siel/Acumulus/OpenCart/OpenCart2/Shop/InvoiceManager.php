<?php
namespace Siel\Acumulus\OpenCart\OpenCart2\Shop;

use Siel\Acumulus\Invoice\Source as Source;
use Siel\Acumulus\OpenCart\Helpers\Registry;
use Siel\Acumulus\OpenCart\Shop\InvoiceManager as BaseInvoiceManager;
use Siel\Acumulus\Invoice\Result;

class InvoiceManager extends BaseInvoiceManager
{
    /**
     * {@inheritdoc}
     *
     * This OpenCart 2 override triggers the 'acumulus.invoice.created' event.
     */
    protected function triggerInvoiceCreated(array &$invoice, Result $localResult, Source $invoiceSource)
    {
        $args = array('invoice' => &$invoice, 'source' => $invoiceSource);
        $this->getEvent()->trigger('acumulus.invoice.created', $args);
    }

    /**
     * {@inheritdoc}
     *
     * This OpenCart 2 override triggers the 'acumulus.invoice.completed' event.
     */
    protected function triggerInvoiceCompleted(array &$invoice, Result $localResult, Source $invoiceSource)
    {
        $args = array('invoice' => &$invoice, 'source' => $invoiceSource, 'localResult' => $localResult);
        $this->getEvent()->trigger('acumulus.invoice.completed', $args);
    }

    /**
     * {@inheritdoc}
     *
     * This OpenCart 2 override triggers the 'acumulus.invoice.sent' event.
     */
    protected function triggerInvoiceSent(array $invoice, Source $invoiceSource, Result $result)
    {
        $args = array('invoice' => $invoice, 'source' => $invoiceSource, 'result' => $result);
        $this->getEvent()->trigger('acumulus.invoice.sent', $args);
    }

    /**
     * @return \Event
     */
    private function getEvent()
    {
        return Registry::getInstance()->event;
    }
}
