<?php
/**
 * @noinspection SqlDialectInspection
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Shop;

use DateTime;
use DB;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\OpenCart\Helpers\Registry;
use Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;

/**
 * Implements the OpenCart specific parts of the invoice manager.
 *
 * SECURITY REMARKS
 * ----------------
 * In OpenCart querying orders is done via self constructed queries. So, this
 * class has to take care of sanitizing itself.
 * - Numbers are cast by using numeric formatters (like %u, %d, %f) with
 *   sprintf().
 * - Strings are escaped using the escape() method on a db instance, unless they
 *   are hard coded or internal variables.
 */
class InvoiceManager extends BaseInvoiceManager
{
    /** @var array[] */
    protected array $tableInfo;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->tableInfo = [
            Source::Order => [
                'table' => DB_PREFIX . 'order',
                'key' => 'order_id',
            ],
            Source::CreditNote => [
                'table' => DB_PREFIX . 'return',
                'key' => 'return_id',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByIdRange(string $invoiceSourceType, string $InvoiceSourceIdFrom, string $InvoiceSourceIdTo): array
    {
        $key = $this->tableInfo[$invoiceSourceType]['key'];
        /** @var \stdClass $result (documentation error in DB) */
        $result = $this->getDb()->query(
            sprintf(
                'SELECT `%s` FROM `%s` WHERE `%s` BETWEEN %u AND %u',
                $key,
                $this->tableInfo[$invoiceSourceType]['table'],
                $key,
                $InvoiceSourceIdFrom,
                $InvoiceSourceIdTo
            )
        );
        return $this->getSourcesByIdsOrSources($invoiceSourceType, array_column($result->rows, $key));
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByDateRange(string $invoiceSourceType, DateTime $dateFrom, DateTime $dateTo): array
    {
        $key = $this->tableInfo[$invoiceSourceType]['key'];
        /** @var \stdClass $result (documentation error in DB) */
        $result = $this->getDb()->query(
            sprintf(
                "SELECT `%s` FROM `%s` WHERE `date_modified` BETWEEN '%s' AND '%s'",
                $key,
                $this->tableInfo[$invoiceSourceType]['table'],
                $this->getSqlDate($dateFrom),
                $this->getSqlDate($dateTo)
            )
        );
        return $this->getSourcesByIdsOrSources($invoiceSourceType, array_column($result->rows, $key));
    }

    /**
     * {@inheritdoc}
     *
     * This OpenCart override triggers the 'acumulus.invoice.created' event.
     */
    protected function triggerInvoiceCreated(?array &$invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        $route = 'model/' . $this->getLocation() . '/invoiceCreated/after';
        $args = ['invoice' => &$invoice, 'source' => $invoiceSource, 'localResult' => $localResult];
        $this->getEvent()->trigger($route, [&$route, $args]);
    }

    /**
     * {@inheritdoc}
     *
     * This OpenCart override triggers the 'acumulus.invoice.completed' event.
     */
    protected function triggerInvoiceSendBefore(?array &$invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        $route = 'model/' . $this->getLocation() . '/invoiceSend/before';
        $args = ['invoice' => &$invoice, 'source' => $invoiceSource, 'localResult' => $localResult];
        $this->getEvent()->trigger($route, [&$route, $args]);
    }

    /**
     * {@inheritdoc}
     *
     * This OpenCart override triggers the 'acumulus.invoice.sent' event.
     */
    protected function triggerInvoiceSendAfter(array $invoice, Source $invoiceSource, InvoiceAddResult $result): void
    {
        $route = 'model/' . $this->getLocation() . '/invoiceSend/after';
        $args = ['invoice' => $invoice, 'source' => $invoiceSource, 'result' => $result];
        $this->getEvent()->trigger($route, [&$route, $args]);
    }

    /**
     * Helper method to get the db object.
     */
    protected function getDb(): DB
    {
        return $this->getRegistry()->db;
    }

    /**
     * @return string
     */
    protected function getLocation(): string
    {
        return $this->getRegistry()->getLocation();
    }

    /**
     * Wrapper around the event class instance.
     *
     * @return \Event|\Light_Event
     *   [SIEL #194403]; https://lightning.devs.mx/ defines its own handler.
     */
    private function getEvent()
    {
        return $this->getRegistry()->event;
    }

    /**
     * @return \Siel\Acumulus\OpenCart\Helpers\Registry
     *
     */
    protected function getRegistry(): Registry
    {
        return Registry::getInstance();
    }
}
