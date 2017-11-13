<?php
namespace Siel\Acumulus\OpenCart\Shop;

use DateTime;
use Siel\Acumulus\Helpers\ContainerInterface;
use Siel\Acumulus\Invoice\Result;
use Siel\Acumulus\Invoice\Source as Source;
use Siel\Acumulus\OpenCart\Helpers\Registry;
use Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;

class InvoiceManager extends BaseInvoiceManager
{
    /** @var array */
    protected $tableInfo;

    /** @var string */
    protected $orderTableName;

    /** @var string */
    protected $returnTableName;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->tableInfo = array(
            Source::Order => array(
                'table' => DB_PREFIX . 'order',
                'key' => 'order_id',
            ),
            Source::CreditNote => array(
                'table' => DB_PREFIX . 'return',
                'key' => 'return_id',
            ),
        );
    }

    /** @noinspection PhpUndefinedNamespaceInspection */
    /** @noinspection PhpUndefinedClassInspection */
    /**
     * Helper method to get the db object.
     *
     * @return \DBMySQLi|\DB\MySQLi
     */
    protected function getDb()
    {
        return Registry::getInstance()->db;
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByIdRange($invoiceSourceType, $InvoiceSourceIdFrom, $InvoiceSourceIdTo)
    {
        $key = $this->tableInfo[$invoiceSourceType]['key'];
        $table = $this->tableInfo[$invoiceSourceType]['table'];
        $query = sprintf("SELECT `%s` FROM `%s` WHERE `%s` BETWEEN %u AND %u", $key, $table, $key, $InvoiceSourceIdFrom, $InvoiceSourceIdTo);
        $result = $this->getDb()->query($query);
        return $this->getSourcesByIdsOrSources($invoiceSourceType, $this->getCol($result->rows, $key));
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByDateRange($invoiceSourceType, DateTime $dateFrom, DateTime $dateTo)
    {
        $dateFrom = $this->getSqlDate($dateFrom);
        $dateTo = $this->getSqlDate($dateTo);
        $dateField = 'date_modified';
        $key = $this->tableInfo[$invoiceSourceType]['key'];
        $table = $this->tableInfo[$invoiceSourceType]['table'];
        $query = sprintf("SELECT `%s` FROM `%s` WHERE `%s` BETWEEN '%s' AND '%s'", $key, $table, $dateField, $dateFrom, $dateTo);
        $result = $this->getDb()->query($query);
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
        $args = array('invoice' => &$invoice, 'source' => $invoiceSource, 'localResult' => $localResult);
	    $this->getEvent()->trigger($route, array(&$route, $args));
    }

    /**
     * {@inheritdoc}
     *
     * This OpenCart override triggers the 'acumulus.invoice.completed' event.
     */
    protected function triggerInvoiceSendBefore(array &$invoice, Source $invoiceSource, Result $localResult)
    {
	    $route = 'model/' . Registry::getInstance()->getLocation() . '/invoiceSend/before';
        $args = array('invoice' => &$invoice, 'source' => $invoiceSource, 'localResult' => $localResult);
	    $this->getEvent()->trigger($route, array(&$route, $args));
    }

    /**
     * {@inheritdoc}
     *
     * This OpenCart override triggers the 'acumulus.invoice.sent' event.
     */
    protected function triggerInvoiceSendAfter(array $invoice, Source $invoiceSource, Result $result)
    {
	    $route = 'model/' . Registry::getInstance()->getLocation() . '/invoiceSend/after';
        $args = array('invoice' => $invoice, 'source' => $invoiceSource, 'result' => $result);
	    $this->getEvent()->trigger($route, array(&$route, $args));
    }

    /**
     * Wrapper around the event class instance.
     *
     * @return \Event
     */
    private function getEvent()
    {
        return Registry::getInstance()->event;
    }
}
