<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use DateTime;
use Db;
use Hook;
use Order;
use OrderSlip;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;
use Siel\Acumulus\Invoice\Result;

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
    /** @var string */
    protected $orderTableName;

    /** @var string */
    protected $orderSlipTableName;

    /**
     * {@inheritdoc}
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->orderTableName = _DB_PREFIX_ . Order::$definition['table'];
        $this->orderSlipTableName = _DB_PREFIX_ . OrderSlip::$definition['table'];
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByIdRange($invoiceSourceType, $InvoiceSourceIdFrom, $InvoiceSourceIdTo)
    {
        switch ($invoiceSourceType) {
            case Source::Order:
                $key = pSql(Order::$definition['primary']);
                /** @noinspection PhpUnhandledExceptionInspection */
                $ids = Db::getInstance()->executeS(sprintf(
                    "SELECT `%s` FROM `%s` WHERE `%s` BETWEEN %u AND %u",
                    $key,
                    pSql($this->orderTableName),
                    $key,
                    $InvoiceSourceIdFrom,
                    $InvoiceSourceIdTo
                ));
                return $this->getSourcesByIdsOrSources($invoiceSourceType, $this->getCol($ids, $key));
            case Source::CreditNote:
                $key = pSql(OrderSlip::$definition['primary']);
                /** @noinspection PhpUnhandledExceptionInspection */
                $ids = Db::getInstance()->executeS(sprintf(
                "SELECT `%s` FROM `%s` WHERE `%s` BETWEEN %u AND %u",
                    $key,
                    pSql($this->orderSlipTableName),
                    $key,
                    $InvoiceSourceIdFrom,
                    $InvoiceSourceIdTo
                ));
                return $this->getSourcesByIdsOrSources($invoiceSourceType, $this->getCol($ids, $key));
        }
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByReferenceRange($invoiceSourceType, $invoiceSourceReferenceFrom, $invoiceSourceReferenceTo)
    {
        switch ($invoiceSourceType) {
            case Source::Order:
                $key = Order::$definition['primary'];
                /** @noinspection PhpUnhandledExceptionInspection */
                $ids = Db::getInstance()->executeS(sprintf("SELECT `%s` FROM `%s` WHERE `%s` BETWEEN '%s' AND '%s'",
                        pSql($key),
                        $this->orderTableName,
                        'reference',
                        pSQL($invoiceSourceReferenceFrom),
                        pSQL($invoiceSourceReferenceTo)
                    )
                );
                return $this->getSourcesByIdsOrSources($invoiceSourceType, $this->getCol($ids, $key));
            case Source::CreditNote:
                return $this->getInvoiceSourcesByIdRange($invoiceSourceType, $invoiceSourceReferenceFrom, $invoiceSourceReferenceTo);
        }
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByDateRange($invoiceSourceType, DateTime $dateFrom, DateTime $dateTo)
    {
        $dateFrom = $dateFrom->format('c');
        $dateTo = $dateTo->format('c');
        switch ($invoiceSourceType) {
            case Source::Order:
                $ids = Order::getOrdersIdByDate($dateFrom, $dateTo);
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
    protected function triggerInvoiceCreated(array &$invoice, Source $invoiceSource, Result $localResult)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        Hook::exec('actionAcumulusInvoiceCreated', ['invoice' => &$invoice, 'source' => $invoiceSource, 'localResult' => $localResult]);
    }

    /**
     * {@inheritdoc}
     *
     * This PrestaShop override executes the 'actionAcumulusInvoiceSendBefore' hook.
     */
    protected function triggerInvoiceSendBefore(array &$invoice, Source $invoiceSource, Result $localResult)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        Hook::exec('actionAcumulusInvoiceSendBefore', ['invoice' => &$invoice, 'source' => $invoiceSource, 'localResult' => $localResult]);
    }

    /**
     * {@inheritdoc}
     *
     * This PrestaShop override executes the 'actionAcumulusInvoiceSentAfter' hook.
     */
    protected function triggerInvoiceSendAfter(array $invoice, Source $invoiceSource, Result $result)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        Hook::exec('actionAcumulusInvoiceSendAfter', ['invoice' => $invoice, 'source' => $invoiceSource, 'result' => $result]);
    }
}
