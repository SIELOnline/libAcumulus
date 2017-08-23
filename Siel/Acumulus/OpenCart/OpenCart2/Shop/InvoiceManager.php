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
        $this->getEvent()->trigger('model/' . Registry::getInstance()->getLocation() . '/invoiceCreated/after', $args);
    }

    /**
     * {@inheritdoc}
     *
     * This OpenCart 2 override triggers the 'acumulus.invoice.completed' event.
     */
    protected function triggerInvoiceCompleted(array &$invoice, Result $localResult, Source $invoiceSource)
    {
        $args = array('invoice' => &$invoice, 'localResult' => $localResult, 'source' => $invoiceSource);
        $this->getEvent()->trigger('model/' . Registry::getInstance()->getLocation() . '/invoiceSend/before', $args);
    }

    /**
     * {@inheritdoc}
     *
     * This OpenCart 2 override triggers the 'acumulus.invoice.sent' event.
     */
    protected function triggerInvoiceSent(array $invoice, Source $invoiceSource, Result $result)
    {
        $args = array('invoice' => $invoice, 'source' => $invoiceSource, 'result' => $result);
        $this->getEvent()->trigger('model/' . Registry::getInstance()->getLocation() . '/invoiceSend/after', $args);
    }

    /**
     * @return \Event
     */
    private function getEvent()
    {
        return Registry::getInstance()->event;
    }
}
