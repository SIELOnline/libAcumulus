<?php
namespace Siel\Acumulus\OpenCart\OpenCart1\Shop;

use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\OpenCart\Shop\InvoiceManager as BaseInvoiceManager;

/**
 * This OpenCart 1 override allows you to insert your event handler code using
 * VQMOD.
 */
class InvoiceManager extends BaseInvoiceManager
{
    /**
     * {@inheritdoc}
     */
    protected function triggerInvoiceCreated(array &$invoice, BaseSource $invoiceSource)
    {
        // VQMOD: insert your 'acumulus.invoice.created' event code here.
        // END VQMOD: insert your 'acumulus.invoice.created' event code here.
    }

    /**
     * {@inheritdoc}
     */
    protected function triggerInvoiceCompleted(array &$invoice, BaseSource $invoiceSource)
    {
        // VQMOD: insert your 'acumulus.invoice.completed' event code here.
        // END VQMOD: insert your 'acumulus.invoice.completed' event code here.
    }

    /**
     * {@inheritdoc}
     */
    protected function triggerInvoiceSent(array $invoice, BaseSource $invoiceSource, array $result)
    {
        // VQMOD: insert your 'acumulus.invoice.sent' event code here.
        // END VQMOD: insert your 'acumulus.invoice.sent' event code here.
    }
}
