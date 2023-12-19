<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Shop;

use DateTime;
use Db;
use Hook;
use Order;
use OrderSlip;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;
use Siel\Acumulus\Invoice\InvoiceAddResult;

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

    public function getInvoiceSourcesByIdRange(string $invoiceSourceType, int $invoiceSourceIdFrom, int $invoiceSourceIdTo): array
    {
        switch ($invoiceSourceType) {
            case Source::Order:
                $key = pSQL(Order::$definition['primary']);
                /** @noinspection PhpUnhandledExceptionInspection */
                $ids = Db::getInstance()->executeS(sprintf(
                    'SELECT `%s` FROM `%s` WHERE `%s` BETWEEN %u AND %u',
                    $key,
                    pSQL($this->orderTableName),
                    $key,
                    $invoiceSourceIdFrom,
                    $invoiceSourceIdTo
                ));
                return $this->getSourcesByIdsOrSources($invoiceSourceType, array_column($ids, $key));
            case Source::CreditNote:
                $key = pSQL(OrderSlip::$definition['primary']);
                /** @noinspection PhpUnhandledExceptionInspection */
                $ids = Db::getInstance()->executeS(sprintf(
                'SELECT `%s` FROM `%s` WHERE `%s` BETWEEN %u AND %u',
                    $key,
                    pSQL($this->orderSlipTableName),
                    $key,
                    $invoiceSourceIdFrom,
                    $invoiceSourceIdTo
                ));
                return $this->getSourcesByIdsOrSources($invoiceSourceType, array_column($ids, $key));
        }
        return [];
    }

    public function getInvoiceSourcesByReferenceRange(string $invoiceSourceType, string $invoiceSourceReferenceFrom, string $invoiceSourceReferenceTo): array
    {
        switch ($invoiceSourceType) {
            case Source::Order:
                $key = Order::$definition['primary'];
                /** @noinspection PhpUnhandledExceptionInspection */
                $ids = Db::getInstance()->executeS(sprintf("SELECT `%s` FROM `%s` WHERE `%s` BETWEEN '%s' AND '%s'",
                        pSQL($key),
                        $this->orderTableName,
                        'reference',
                        pSQL($invoiceSourceReferenceFrom),
                        pSQL($invoiceSourceReferenceTo)
                    )
                );
                return $this->getSourcesByIdsOrSources($invoiceSourceType, array_column($ids, $key));
            case Source::CreditNote:
                return $this->getInvoiceSourcesByIdRange($invoiceSourceType, (int) $invoiceSourceReferenceFrom, (int) $invoiceSourceReferenceTo);
        }
        return [];
    }

    public function getInvoiceSourcesByDateRange(string $invoiceSourceType, DateTime $dateFrom, DateTime $dateTo): array
    {
        $dateFromStr = $dateFrom->format('c');
        $dateToStr = $dateTo->format('c');
        switch ($invoiceSourceType) {
            case Source::Order:
                $ids = Order::getOrdersIdByDate($dateFromStr, $dateToStr);
                return $this->getSourcesByIdsOrSources($invoiceSourceType, $ids);
            case Source::CreditNote:
                $ids = OrderSlip::getSlipsIdByDate($dateFrom, $dateTo);
                return $this->getSourcesByIdsOrSources($invoiceSourceType, $ids);
        }
        return [];
    }

    /**
     * {@inheritdoc}
     *
     * This PrestaShop override executes the 'actionAcumulusInvoiceCreated' hook.
     */
    protected function triggerInvoiceCreated(?array &$invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        Hook::exec('actionAcumulusInvoiceCreated', ['invoice' => &$invoice, 'source' => $invoiceSource, 'localResult' => $localResult]);
    }

    /**
     * {@inheritdoc}
     *
     * This PrestaShop override executes the 'actionAcumulusInvoiceSendBefore' hook.
     */
    protected function triggerInvoiceSendBefore(?array &$invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        Hook::exec('actionAcumulusInvoiceSendBefore', ['invoice' => &$invoice, 'source' => $invoiceSource, 'localResult' => $localResult]);
    }

    /**
     * {@inheritdoc}
     *
     * This PrestaShop override executes the 'actionAcumulusInvoiceSentAfter' hook.
     */
    protected function triggerInvoiceSendAfter(array $invoice, Source $invoiceSource, InvoiceAddResult $result): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        Hook::exec('actionAcumulusInvoiceSendAfter', ['invoice' => $invoice, 'source' => $invoiceSource, 'result' => $result]);
    }
}
