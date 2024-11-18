<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Shop;

use DateTimeInterface;
use Db;
use Hook;
use Order;
use OrderSlip;
use RuntimeException;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;

use function count;
use function sprintf;

/**
 * Implements the PrestaShop specific parts of the invoice manager.
 *
 * SECURITY REMARKS
 * ----------------
 * In PrestaShop querying orders and order slips is done via available methods
 * on \Order or via self constructed queries. In the latter case, this class has
 * to take care of sanitizing itself.
 * - Numbers are cast by using numeric formatters (like %u, %d, %f) with
 *   sprintf().
 * - Strings are escaped using pSQL(), unless they are hard coded or are
 *   internal variables.
 */
class InvoiceManager extends BaseInvoiceManager
{
    protected string $orderTableName;
    protected string $orderSlipTableName;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->orderTableName = _DB_PREFIX_ . Order::$definition['table'];
        $this->orderSlipTableName = _DB_PREFIX_ . OrderSlip::$definition['table'];
    }

    public function getInvoiceSourcesByIdRange(string $sourceType, int $idFrom, int $idTo): array
    {
        switch ($sourceType) {
            case Source::Order:
                $key = pSQL(Order::$definition['primary']);
                /** @noinspection PhpUnhandledExceptionInspection */
                $ids = Db::getInstance()->executeS(
                    sprintf(
                        'SELECT `%s` FROM `%s` WHERE `%s` BETWEEN %u AND %u',
                        $key,
                        pSQL($this->orderTableName),
                        $key,
                        $idFrom,
                        $idTo
                    )
                );
                return $this->getSourcesByIdsOrSources($sourceType, array_column($ids, $key));
            case Source::CreditNote:
                $key = pSQL(OrderSlip::$definition['primary']);
                /** @noinspection PhpUnhandledExceptionInspection */
                $ids = Db::getInstance()->executeS(
                    sprintf(
                        'SELECT `%s` FROM `%s` WHERE `%s` BETWEEN %u AND %u',
                        $key,
                        pSQL($this->orderSlipTableName),
                        $key,
                        $idFrom,
                        $idTo
                    )
                );
                return $this->getSourcesByIdsOrSources($sourceType, array_column($ids, $key));
        }
        return [];
    }

    public function getInvoiceSourcesByReferenceRange(string $sourceType, string $referenceFrom, string $referenceTo, bool $fallbackToId): array
    {
        switch ($sourceType) {
            case Source::Order:
                $key = Order::$definition['primary'];
                /** @noinspection PhpUnhandledExceptionInspection */
                $ids = Db::getInstance()->executeS(
                    sprintf(
                        "SELECT `%s` FROM `%s` WHERE `%s` BETWEEN '%s' AND '%s'",
                        pSQL($key),
                        $this->orderTableName,
                        'reference',
                        pSQL($referenceFrom),
                        pSQL($referenceTo)
                    )
                );
                $result = $this->getSourcesByIdsOrSources($sourceType, array_column($ids, $key));
                break;
            case Source::CreditNote:
                $result = $this->getInvoiceSourcesByIdRange($sourceType, (int) $referenceFrom, (int) $referenceTo);
                break;
            default:
                throw new RuntimeException('Unknown invoice source type');
        }
        return count($result) > 0 ? $result : parent::getInvoiceSourcesByReferenceRange($sourceType, $referenceFrom, $referenceTo, $fallbackToId);
    }

    public function getInvoiceSourcesByDateRange(string $sourceType, DateTimeInterface $dateFrom, DateTimeInterface $dateTo): array
    {
        $dateFromStr = $dateFrom->format('c');
        $dateToStr = $dateTo->format('c');
        switch ($sourceType) {
            case Source::Order:
                $ids = Order::getOrdersIdByDate($dateFromStr, $dateToStr);
                return $this->getSourcesByIdsOrSources($sourceType, $ids);
            case Source::CreditNote:
                $ids = OrderSlip::getSlipsIdByDate($dateFrom, $dateTo);
                return $this->getSourcesByIdsOrSources($sourceType, $ids);
        }
        return [];
    }
}
