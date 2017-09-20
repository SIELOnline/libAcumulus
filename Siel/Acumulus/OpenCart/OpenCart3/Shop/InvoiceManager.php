<?php
namespace Siel\Acumulus\OpenCart\OpenCart3\Shop;

use Siel\Acumulus\Invoice\Source as Source;
use Siel\Acumulus\OpenCart\Helpers\Registry;
use Siel\Acumulus\OpenCart\Shop\InvoiceManager as BaseInvoiceManager;
use Siel\Acumulus\Invoice\Result;

class InvoiceManager extends BaseInvoiceManager
{
    /**
     * {@inheritdoc}
     *
     * This OpenCart 3 override triggers the 'acumulus.invoice.created' event.
     */
    protected function triggerInvoiceCreated(array &$invoice, Source $invoiceSource, Result $localResult)
    {
        $args = array('invoice' => &$invoice, 'source' => $invoiceSource, 'localResult' => $localResult);
        $this->getEvent()->trigger('model/' . Registry::getInstance()->getLocation() . '/invoiceCreated/after', $args);
    }

    /**
     * {@inheritdoc}
     *
     * This OpenCart 3 override triggers the 'acumulus.invoice.completed' event.
     */
    protected function triggerInvoiceSendBefore(array &$invoice, Source $invoiceSource, Result $localResult)
    {
        $args = array('invoice' => &$invoice, 'source' => $invoiceSource, 'localResult' => $localResult);
        $this->getEvent()->trigger('model/' . Registry::getInstance()->getLocation() . '/invoiceSend/before', $args);
    }

    /**
     * {@inheritdoc}
     *
     * This OpenCart 3 override triggers the 'acumulus.invoice.sent' event.
     */
    protected function triggerInvoiceSendAfter(array $invoice, Source $invoiceSource, Result $result)
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
