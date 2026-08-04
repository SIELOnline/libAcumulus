<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Shop;

use DateTimeInterface;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;
use WHMCS\Database\Capsule;

/**
 * Implements the WHMCS specific parts of the invoice manager.
 */
class InvoiceManager extends BaseInvoiceManager
{
    protected static array $sourceTypeTableInfo = [
        Source::Order => [
            'table' => 'tblorders',
            'idField' => 'id',
            'refField' => 'ordernum',
            'dateField' => 'date',
        ],
        Source::CreditNote => [ // @todo: add support for credit notes.
            'table' => 'tblinvoices',
            'idField' => 'id',
            'refField' => 'invoicenum',
            'dateField' => 'date_refunded',
        ],
        Source::Invoice => [
            'table' => 'tblinvoices',
            'idField' => 'id',
            'refField' => 'invoicenum',
            'dateField' => 'date',
        ],
    ];

    public function getInvoiceSourcesByIdRange(string $sourceType, int $idFrom, int $idTo): array
    {
        $tableInfo = static::$sourceTypeTableInfo[$sourceType];
        $records = Capsule::table($tableInfo['table'])
            ->whereBetween($tableInfo['idField'], [$idFrom, $idTo])
            ->orderBy($tableInfo['idField'])
            ->get();
        return $this->getSourcesByIdsOrSources($sourceType, $records);
    }

    /**
     * {@inheritdoc}
     *
     * We support:
     * - "WooCommerce Sequential Order Numbers (Pro)", see
     *   https://wordpress.org/plugins/woocommerce-sequential-order-numbers/ and
     *   http://docs.woothemes.com/document/sequential-order-numbers/.
     * - "WC Sequential Order Numbers", see
     *   https://wordpress.org/plugins/wc-sequential-order-numbers/ and
     *   http://plugins.dualcube.com/product/wc-sequential-order-numbers/.
     * - "Custom Order Numbers for WooCommerce (pro)", see
     *   https://wordpress.org/plugins/custom-order-numbers-for-woocommerce and
     *   https://wpfactory.com/item/custom-order-numbers-woocommerce/.
     *
     * If you know of other plugins, please let us know.
     *
     * These plugins mostly only store the number part, not the prefix, suffix
     * or date part. If so, you will have to search for the number part only.
     *
     * To be able to define the query we need to know under which meta-key
     * the order number/reference is stored.
     * - WooCommerce Sequential Order Numbers: _order_number.
     * - WooCommerce Sequential Order Numbers Pro: _order_number or _order_number_formatted.
     * - WC Sequential Order Numbers: _order_number or _order_number_formatted.
     * - Custom Order Numbers for WooCommerce (Pro): _alg_wc_custom_order_number.
     */
    public function getInvoiceSourcesByReferenceRange(string $sourceType, string $referenceFrom, string $referenceTo, bool $fallbackToId): array
    {
        $results = [];
        $tableInfo = static::$sourceTypeTableInfo[$sourceType];
        if (!empty($tableInfo['refField'])) {
            $records = Capsule::table($tableInfo['table'])
                ->whereBetween($tableInfo['refField'], [$referenceFrom, $referenceTo])
                ->orderBy($tableInfo['refField'])
                ->get();
            $results = $this->getSourcesByIdsOrSources($sourceType, $records);
        }
        if (empty($results) && $fallbackToId) {
            $results = parent::getInvoiceSourcesByReferenceRange($sourceType, $referenceFrom, $referenceTo, $fallbackToId);
        }
        return $results;
    }

    public function getInvoiceSourcesByDateRange(string $sourceType, DateTimeInterface $dateFrom, DateTimeInterface $dateTo): array
    {
        $tableInfo = static::$sourceTypeTableInfo[$sourceType];
        $records = Capsule::table($tableInfo['table'])
            ->whereBetween($tableInfo['dateField'], [$dateFrom, $dateTo])
            ->orderBy($tableInfo['dateField'])
            ->get();
        return $this->getSourcesByIdsOrSources($sourceType, $records);
    }
}
