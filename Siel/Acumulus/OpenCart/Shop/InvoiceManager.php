<?php
namespace Siel\Acumulus\OpenCart\Shop;

use DateTime;
use Siel\Acumulus\OpenCart\Helpers\Registry;
use Siel\Acumulus\PrestaShop\Invoice\Source;
use Siel\Acumulus\Shop\Config;
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
    public function __construct(Config $config)
    {
        parent::__construct($config);
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
}
