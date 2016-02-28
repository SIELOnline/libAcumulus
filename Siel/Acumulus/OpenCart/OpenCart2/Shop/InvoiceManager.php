<?php
namespace Siel\Acumulus\OpenCart\OpenCart2\Shop;

use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\OpenCart\Helpers\Registry;
use Siel\Acumulus\OpenCart\Shop\InvoiceManager as BaseInvoiceManager;

class InvoiceManager extends BaseInvoiceManager
{
    /**
     * {@inheritdoc}
     *
     * This OpenCart 2 override triggers the 'acumulus.invoice.created' event.
     */
    protected function triggerInvoiceCreated(array &$invoice, BaseSource $invoiceSource)
    {
        $args = array('invoice' => &$invoice, 'source' => $invoiceSource);
        $this->getEvent()->trigger('acumulus.invoice.created', $args);
    }

    /**
     * {@inheritdoc}
     *
     * This OpenCart 2 override triggers the 'acumulus.invoice.completed' event.
     */
    protected function triggerInvoiceCompleted(array &$invoice, BaseSource $invoiceSource)
    {
        $args = array('invoice' => &$invoice, 'source' => $invoiceSource);
        $this->getEvent()->trigger('acumulus.invoice.completed', $args);
    }

    /**
     * {@inheritdoc}
     *
     * This OpenCart 2 override triggers the 'acumulus.invoice.sent' event.
     */
    protected function triggerInvoiceSent(array $invoice, BaseSource $invoiceSource, array $result)
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
