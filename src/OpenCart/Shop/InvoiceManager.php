<?php
/**
 * @noinspection SqlDialectInspection
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

namespace Siel\Acumulus\OpenCart\Shop;

use DateTime;
use DB;
use Event;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Invoice\Result;
use Siel\Acumulus\Invoice\Source as Source;
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
    /** @var array */
    protected $tableInfo;

    /**
     * {@inheritdoc}
     */
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
     * Helper method to get the db object.
     */
    protected function getDb(): DB
    {
        return Registry::getInstance()->db;
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByIdRange(string $invoiceSourceType, string $InvoiceSourceIdFrom, string $InvoiceSourceIdTo): array
    {
        $key = $this->tableInfo[$invoiceSourceType]['key'];
        /** @var \stdClass $result  (documentation error in DB) */
        $result = $this->getDb()->query(sprintf(
            "SELECT `%s` FROM `%s` WHERE `%s` BETWEEN %u AND %u",
            $key,
            $this->tableInfo[$invoiceSourceType]['table'],
            $key,
            $InvoiceSourceIdFrom,
            $InvoiceSourceIdTo
        ));
        return $this->getSourcesByIdsOrSources($invoiceSourceType, $this->getCol($result->rows, $key));
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByDateRange(string $invoiceSourceType, DateTime $dateFrom, DateTime $dateTo): array
    {
        $key = $this->tableInfo[$invoiceSourceType]['key'];
        /** @var \stdClass $result  (documentation error in DB) */
        $result = $this->getDb()->query(sprintf(
            "SELECT `%s` FROM `%s` WHERE `date_modified` BETWEEN '%s' AND '%s'",
            $key,
            $this->tableInfo[$invoiceSourceType]['table'],
            $this->getSqlDate($dateFrom),
            $this->getSqlDate($dateTo)
        ));
        return $this->getSourcesByIdsOrSources($invoiceSourceType, $this->getCol($result->rows, $key));
    }

    /**
     * {@inheritdoc}
     *
     * This OpenCart override triggers the 'acumulus.invoice.created' event.
     */
    protected function triggerInvoiceCreated(array &$invoice, Source $invoiceSource, Result $localResult)
    {
	    $route = 'model/' . Registry::getInstance()->getLocation() . '/invoiceCreated/after';
        $args = ['invoice' => &$invoice, 'source' => $invoiceSource, 'localResult' => $localResult];
	    $this->getEvent()->trigger($route, [&$route, $args]);
    }

    /**
     * {@inheritdoc}
     *
     * This OpenCart override triggers the 'acumulus.invoice.completed' event.
     */
    protected function triggerInvoiceSendBefore(array &$invoice, Source $invoiceSource, Result $localResult)
    {
	    $route = 'model/' . Registry::getInstance()->getLocation() . '/invoiceSend/before';
        $args = ['invoice' => &$invoice, 'source' => $invoiceSource, 'localResult' => $localResult];
	    $this->getEvent()->trigger($route, [&$route, $args]);
    }

    /**
     * {@inheritdoc}
     *
     * This OpenCart override triggers the 'acumulus.invoice.sent' event.
     */
    protected function triggerInvoiceSendAfter(array $invoice, Source $invoiceSource, Result $result)
    {
	    $route = 'model/' . Registry::getInstance()->getLocation() . '/invoiceSend/after';
        $args = ['invoice' => $invoice, 'source' => $invoiceSource, 'result' => $result];
	    $this->getEvent()->trigger($route, [&$route, $args]);
    }

    /**
     * Wrapper around the event class instance.
     */
    private function getEvent(): Event
    {
        return Registry::getInstance()->event;
    }
}
