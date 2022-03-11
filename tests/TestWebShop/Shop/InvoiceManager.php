<?php
namespace Siel\Acumulus\TestWebShop\Shop;

use DateTime;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;
use Siel\Acumulus\Invoice\Result;

/**
 * Implements the TestWebShop specific parts of the invoice manager.
 *
 * @todo:
 * - Define the connection between this library and TestWebShop's database
 *   (e.g. OpenCart, PrestaShop) or model architecture (e.g. Magento).
 * - Implement the retrieval methods getInvoiceSourcesByIdRange(),
 *   getInvoiceSourcesByReferenceRange() and getInvoiceSourcesByDateRange().
 *   The 2nd one, only when TestWebShop has references that differ from the
 *   (internal) ID.
 * - Implement the methods triggerInvoiceCreated(), triggerInvoiceSendBefore(),
 *   and triggerInvoiceSendAfter(). NOTE: follow TestWebShop's naming practices
 *   regarding events.
 *
 * SECURITY REMARKS
 * ----------------
 * @todo: document why this class is considered safe. Below is sample text from the PrestaShop module, so do not leave as is.
 * In TestWebShop, querying orders and order slips is done via available methods
 * on \Order or via self constructed queries. In the latter case, this class has
 * to take care of sanitizing itself.
 * - Numbers are cast by using numeric formatters (like %u, %d, %f) with
 *   sprintf().
 * - Strings are escaped using pSQL(), unless they are hard coded or are
 *   internal variables.
 */
class InvoiceManager extends BaseInvoiceManager
{
    /**
     * {@inheritdoc}
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);
        // @todo: Define the connection between this library and TestWebShop's database (e.g. OpenCart, PrestaShop) or model architecture (e.g. Magento).
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByIdRange(
        string $invoiceSourceType,
        string $InvoiceSourceIdFrom,
        string $InvoiceSourceIdTo
    ): array {
        // @todo
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByReferenceRange(
        string $invoiceSourceType,
        string $invoiceSourceReferenceFrom,
        string $invoiceSourceReferenceTo
    ): array {
        // @todo: implement if TestWebShop has order/refund references (external facing) that differ from the (internal) ID. Otherwise remove this method.
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByDateRange(string $invoiceSourceType, DateTime $dateFrom, DateTime $dateTo): array
    {
        // @todo
        return [];
    }

    /**
     * {@inheritdoc}
     *
     * This TestWebShop override executes the 'actionAcumulusInvoiceCreated' hook.
     */
    protected function triggerInvoiceCreated(array &$invoice, Source $invoiceSource, Result $localResult)
    {
        // @todo: adapt to the way TestWebShop triggers events (and passes parameters (by value and reference) to the event handlers).
        Hook::exec('actionAcumulusInvoiceCreated', array('invoice' => &$invoice, 'source' => $invoiceSource, 'localResult' => $localResult));
    }

    /**
     * {@inheritdoc}
     *
     * This TestWebShop override executes the 'actionAcumulusInvoiceSendBefore' hook.
     */
    protected function triggerInvoiceSendBefore(array &$invoice, Source $invoiceSource, Result $localResult)
    {
        // @todo: adapt to the way TestWebShop triggers events (and passes parameters (by value and reference) to the event handlers).
        Hook::exec('actionAcumulusInvoiceSendBefore', array('invoice' => &$invoice, 'source' => $invoiceSource, 'localResult' => $localResult));
    }

    /**
     * {@inheritdoc}
     *
     * This TestWebShop override executes the 'actionAcumulusInvoiceSentAfter' hook.
     */
    protected function triggerInvoiceSendAfter(array $invoice, Source $invoiceSource, Result $result)
    {
        // @todo: adapt to the way TestWebShop triggers events (and passes parameters (by value) to the event handlers).
        Hook::exec('actionAcumulusInvoiceSendAfter', array('invoice' => $invoice, 'source' => $invoiceSource, 'result' => $result));
    }
}
