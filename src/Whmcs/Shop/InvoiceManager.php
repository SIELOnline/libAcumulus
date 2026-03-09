<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Shop;

use DateTimeInterface;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;

use Traversable;
use WHMCS\Database\Capsule;

use function count;
use function sprintf;
use function strlen;

/**
 * Implements the WHMCS specific parts of the invoice manager.
 */
class InvoiceManager extends BaseInvoiceManager
{
    public function getInvoiceSourcesByIdRange(string $sourceType, int $idFrom, int $idTo): array
    {
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
    }

    public function getInvoiceSourcesByDateRange(string $sourceType, DateTimeInterface $dateFrom, DateTimeInterface $dateTo): array
    {
        switch ($sourceType) {
            case Source::Order:
                $tableName = 'tblorders';
                $dateField = 'date';
                break;
            case Source::CreditNote:
                $tableName = 'tbl';
                $dateField = 'date';
                break;
            case Source::Invoice:
                $tableName = 'tblinvoices';
                $dateField = 'updated_at';
                break;
            default:
                $this->getLog()->error('InvoiceManager::getInvoiceSourcesByDateRange(%s): unknown Source type', $sourceType);
                return [];
        }
        $records = Capsule::table($tableName)
            ->whereBetween($dateField, [$dateFrom, $dateTo])
            ->orderBy($dateField)
            ->get();
        return $this->records2Sources($records, $sourceType);
    }

    /**
     * Helper method to get a list of Sources given a set of query arguments.
     *
     * @return \Siel\Acumulus\Invoice\Source[]
     */
    protected function records2Sources(iterable $records, string $invoiceSourceType): array
    {
        return $this->getSourcesByIdsOrSources($invoiceSourceType, $records);
    }
}
