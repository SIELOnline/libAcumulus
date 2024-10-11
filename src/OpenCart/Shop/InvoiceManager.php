<?php
/**
 * @noinspection PhpUndefinedClassInspection Mix of OC4 and OC3 classes
 * @noinspection PhpUndefinedNamespaceInspection Mix of OC4 and OC3 classes
 */

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Shop;

use DateTimeInterface;
use Siel\Acumulus\Helpers\Container;
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

    public function getInvoiceSourcesByIdRange(string $sourceType, int $idFrom, int $idTo): array
    {
        $key = $this->tableInfo[$sourceType]['key'];
        /** @var \stdClass $result (documentation error in DB) */
        $result = $this->getDb()->query(
            sprintf(
                'SELECT `%s` FROM `%s` WHERE `%s` BETWEEN %u AND %u',
                $key,
                $this->tableInfo[$sourceType]['table'],
                $key,
                $idFrom,
                $idTo
            )
        );
        return $this->getSourcesByIdsOrSources($sourceType, array_column($result->rows, $key));
    }

    public function getInvoiceSourcesByDateRange(string $sourceType, DateTimeInterface $dateFrom, DateTimeInterface $dateTo): array
    {
        $key = $this->tableInfo[$sourceType]['key'];
        /** @var \stdClass $result (documentation error in class DB) */
        $result = $this->getDb()->query(
            sprintf(
                "SELECT `%s` FROM `%s` WHERE `date_modified` BETWEEN '%s' AND '%s'",
                $key,
                $this->tableInfo[$sourceType]['table'],
                $this->getSqlDate($dateFrom),
                $this->getSqlDate($dateTo)
            )
        );
        return $this->getSourcesByIdsOrSources($sourceType, array_column($result->rows, $key));
    }

    /**
     * Wrapper method to get {@see Registry::$db}.
     *
     * @return \Opencart\System\Library\DB|\DB
     */
    protected function getDb()
    {
        return $this->getRegistry()->db;
    }

    /**
     * Wrapper method that returns the OpenCart registry class.
     */
    protected function getRegistry(): Registry
    {
        return Registry::getInstance();
    }
}
