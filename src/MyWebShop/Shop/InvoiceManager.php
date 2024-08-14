<?php

declare(strict_types=1);

namespace Siel\Acumulus\MyWebShop\Shop;

use DateTime;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;
use Siel\Acumulus\Invoice\InvoiceAddResult;

/**
 * Implements the MyWebShop specific parts of the invoice manager.
 *
 * @todo: from the following list:
 * - Define the connection between this library and MyWebShop's database
 *   (e.g. OpenCart, PrestaShop) or model architecture (e.g. Magento).
 * - Implement the retrieval methods getInvoiceSourcesByIdRange(),
 *   getInvoiceSourcesByReferenceRange() and getInvoiceSourcesByDateRange().
 *   The 2nd one, only when MyWebShop has references that differ from the
 *   (internal) ID.
 * - Implement the methods triggerInvoiceCreated(), triggerInvoiceSendBefore(),
 *   and triggerInvoiceSendAfter(). NOTE: follow MyWebShop's naming practices
 *   regarding events.
 *
 * SECURITY REMARKS
 * ----------------
 * @todo: document why this class is considered safe. Below is sample text from the PrestaShop module, so do not leave as is.
 * In MyWebShop, querying orders and order slips is done via available methods
 * on \Order or via self constructed queries. In the latter case, this class has
 * to take care of sanitizing itself.
 * - Numbers are cast by using numeric formatters (like %u, %d, %f) with
 *   sprintf().
 * - Strings are escaped using pSQL(), unless they are hard coded or are
 *   internal variables.
 */
class InvoiceManager extends BaseInvoiceManager
{
    public function __construct(Container $container)
    {
        parent::__construct($container);
        // @todo: Define the connection between this library and MyWebShop's database (e.g. OpenCart, PrestaShop) or model architecture (e.g. Magento).
    }

    public function getInvoiceSourcesByIdRange(string $invoiceSourceType, int $invoiceSourceIdFrom, int $invoiceSourceIdTo): array
    {
        // @todo: provide implementation.
    }

    public function getInvoiceSourcesByReferenceRange(
        string $invoiceSourceType,
        string $invoiceSourceReferenceFrom,
        string $invoiceSourceReferenceTo
    ): array
    {
        // @todo: implement if MyWebShop has order/refund references (external
        //   facing) that differ from the (internal) ID. Otherwise remove this
        //   method.
    }

    public function getInvoiceSourcesByDateRange(string $invoiceSourceType, DateTime $dateFrom, DateTime $dateTo): array
    {
        // @todo: provide implementation.
    }
}
