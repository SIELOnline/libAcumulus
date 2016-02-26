<?php
namespace Siel\Acumulus\OpenCart\OpenCart1\Shop;

use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\OpenCart\Shop\InvoiceManager as BaseInvoiceManager;

class InvoiceManager extends BaseInvoiceManager
{
    /**
     * {@inheritdoc}
     *
     * This PrestaShop override executes the 'actionAcumulusInvoiceCreated' hook.
     */
    protected function triggerInvoiceCreated(array &$invoice, BaseSource $invoiceSource)
    {
        // @todo
//        $args = array('invoice' => &$invoice, 'source' => $invoiceSource);
//        $this->getEvent()->trigger('acumulus.invoice.created', $args);
    }

    /**
     * {@inheritdoc}
     *
     * This PrestaShop override executes the 'actionAcumulusInvoiceCompleted' hook.
     */
    protected function triggerInvoiceCompleted(array &$invoice, BaseSource $invoiceSource)
    {
//        $args = array('invoice' => &$invoice, 'source' => $invoiceSource);
//        $this->getEvent()->trigger('acumulus.invoice.completed', $args);
    }

    /**
     * {@inheritdoc}
     *
     * This PrestaShop override executes the 'actionAcumulusInvoiceSent' hook.
     */
    protected function triggerInvoiceSent(array $invoice, BaseSource $invoiceSource, array $result)
    {
//        $args = array('invoice' => $invoice, 'source' => $invoiceSource, 'result' => $result);
//        $this->getEvent()->trigger('acumulus.invoice.completed', $args);
    }
}
