<?php
/**
 * @noinspection SqlDialectInspection
 * @noinspection SqlNoDataSourceInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Shop;

use DateTime;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use WP_Query;

use function strlen;

/**
 * Implements the WooCommerce specific parts of the invoice manager.
 *
 * SECURITY REMARKS
 * ----------------
 * In WooCommerce/WordPress querying orders is done via the WordPress
 * WP_Query::get_posts() method or via self constructed queries. In the latter
 * case we use the $wpdb->prepare() method to sanitize arguments.
 */
class InvoiceManager extends BaseInvoiceManager
{
    /**
     * Helper method that converts our source type constants to a WP/WC post type.
     *
     * @param string $invoiceSourceType
     *
     * @return string
     */
    protected function sourceTypeToShopType(string $invoiceSourceType): string
    {
        switch ($invoiceSourceType) {
            case Source::Order:
                return 'shop_order';
            case Source::CreditNote:
                return 'shop_order_refund';
            default:
                $this->getLog()->error('InvoiceManager::sourceTypeToShopType(%s): unknown', $invoiceSourceType);
                return '';
        }
    }

    public function getInvoiceSourcesByIdRange(
        string $invoiceSourceType,
        string $InvoiceSourceIdFrom,
        string $InvoiceSourceIdTo
    ): array
    {
        // We use our own query here as defining a range of post ids based on a
        // between does not seem to be possible with the query syntax.
        global $wpdb;
        $key = 'ID';
        $invoiceSourceIds = $wpdb->get_col($wpdb->prepare(
            "SELECT `$key` FROM `$wpdb->posts` WHERE `$key` BETWEEN %d AND %d AND `post_type` = %s",
            $InvoiceSourceIdFrom,
            $InvoiceSourceIdTo,
            $this->sourceTypeToShopType($invoiceSourceType)
        ));
        sort($invoiceSourceIds);
        return $this->getSourcesByIdsOrSources($invoiceSourceType, $invoiceSourceIds);
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
     */
    public function getInvoiceSourcesByReferenceRange(
        string $invoiceSourceType,
        string $invoiceSourceReferenceFrom,
        string $invoiceSourceReferenceTo
    ): array
    {
        // To be able to define the query we need to know under which meta key
        // the order number/reference is stored.
        // - WooCommerce Sequential Order Numbers: _order_number.
        // - WooCommerce Sequential Order Numbers Pro: _order_number or _order_number_formatted.
        // - WC Sequential Order Numbers: _order_number or _order_number_formatted.
        // - Custom Order Numbers for WooCommerce (Pro): _alg_wc_custom_order_number.
        // All only work with orders, not refunds.
        if ($invoiceSourceType === Source::Order) {
            if (is_plugin_active('woocommerce-sequential-order-numbers/woocommerce-sequential-order-numbers.php')) {
                // Search by the order number assigned by this plugin.
                $args = [
                  'meta_query' => [
                    [
                      'key' => '_order_number',
                      'value' => [
                        $invoiceSourceReferenceFrom,
                        $invoiceSourceReferenceTo,
                      ],
                      'compare' => 'BETWEEN',
                      'type' => 'UNSIGNED',
                    ],
                  ],
                ];
                return $this->query2Sources($args, $invoiceSourceType);
            } elseif (is_plugin_active('woocommerce-sequential-order-numbers-pro/woocommerce-sequential-order-numbers.php')
              || is_plugin_active('wc-sequential-order-numbers/Sequential_Order_Numbers.php')
            ) {
                // Search by the order number assigned by this plugin. Note that
                // these plugins allow for text prefixes and suffixes.
                // Therefore, we allow for a lexicographical or a purely numeric
                // comparison.
                if (ctype_digit($invoiceSourceReferenceFrom) && ctype_digit($invoiceSourceReferenceTo)) {
                    if (strlen($invoiceSourceReferenceFrom) < 6 && strlen($invoiceSourceReferenceTo) < 6) {
                        // We assume non formatted search arguments.
                        $key = '_order_number';
                    } else {
                        // Formatted numeric search arguments: e.g. 'yyyynnnn'.
                        $key = '_order_number_formatted';
                    }
                    $type = 'UNSIGNED';
                } else {
                    $key = '_order_number_formatted';
                    $type = 'CHAR';
                }
                $args = [
                  'meta_query' => [
                    [
                      'key' => $key,
                      'value' => [
                        $invoiceSourceReferenceFrom,
                        $invoiceSourceReferenceTo,
                      ],
                      'compare' => 'BETWEEN',
                      'type' => $type,
                    ],
                  ],
                ];
                return $this->query2Sources($args, $invoiceSourceType);
            } elseif (is_plugin_active('custom-order-numbers-for-woocommerce-pro/custom-order-numbers-for-woocommerce-pro.php')
                || is_plugin_active('custom-order-numbers-for-woocommerce/custom-order-numbers-for-woocommerce.php')
            ) {
                // Search by the order number assigned by this plugin.
                $args = [
                    'meta_query' => [
                        [
                            'key' => '_alg_wc_custom_order_number',
                            'value' => [
                                $invoiceSourceReferenceFrom,
                                $invoiceSourceReferenceTo,
                            ],
                            'compare' => 'BETWEEN',
                            'type' => 'UNSIGNED',
                        ],
                    ],
                ];
                return $this->query2Sources($args, $invoiceSourceType);
            }
        }
        return parent::getInvoiceSourcesByReferenceRange(
            $invoiceSourceType,
            $invoiceSourceReferenceFrom,
            $invoiceSourceReferenceTo
        );
    }

    public function getInvoiceSourcesByDateRange(string $invoiceSourceType, DateTime $dateFrom, DateTime $dateTo): array
    {
        $args = [
            'date_query' => [
                [
                    'column' => 'post_modified',
                    'after' => [
                        'year' => $dateFrom->format('Y'),
                        'month' => $dateFrom->format('m'),
                        'day' => $dateFrom->format('d'),
                    ],
                    'before' => [
                        'year' => $dateTo->format('Y'),
                        'month' => $dateTo->format('m'),
                        'day' => $dateTo->format('d'),
                    ],
                    'inclusive' => true,
                ],
            ],
        ];
        return $this->query2Sources($args, $invoiceSourceType);
    }

    /**
     * {@inheritdoc}
     *
     * This WooCommerce override applies the 'acumulus_invoice_created' filter.
     */
    protected function triggerInvoiceCreated(?array &$invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        $invoice = apply_filters('acumulus_invoice_created', $invoice, $invoiceSource, $localResult);
    }

    /**
     * {@inheritdoc}
     *
     * This WooCommerce override applies the 'acumulus_invoice_send_before' filter.
     */
    protected function triggerInvoiceSendBefore(?array &$invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        $invoice = apply_filters('acumulus_invoice_send_before', $invoice, $invoiceSource, $localResult);
    }

    /**
     * {@inheritdoc}
     *
     * This WooCommerce override executes the 'acumulus_invoice_send_after' action.
     */
    protected function triggerInvoiceSendAfter(array $invoice, Source $invoiceSource, InvoiceAddResult $result): void
    {
        do_action('acumulus_invoice_send_after', $invoice, $invoiceSource, $result);
    }

    /**
     * Helper method to get a list of Sources given a set of query arguments.
     *
     * @param array $args
     * @param string $invoiceSourceType
     * @param bool $sort
     *
     * @return \Siel\Acumulus\Invoice\Source[]
     */
    protected function query2Sources(array $args, string $invoiceSourceType, bool $sort = true): array
    {
        $this->getLog()->info(
            'WooCommerce\InvoiceManager::query2Sources: args = %s',
            str_replace(["\r", "\n"], '', json_encode($args, Log::JsonFlags))
        );
        // Add default arguments.
        $args += [
            'fields' => 'ids',
            'posts_per_page' => -1,
            'post_type' => $this->sourceTypeToShopType($invoiceSourceType),
            'post_status' => array_keys(wc_get_order_statuses()),
        ];
        $query = new WP_Query($args);
        $ids = $query->get_posts();
        if ($sort) {
            sort($ids);
        }
        return $this->getSourcesByIdsOrSources($invoiceSourceType, $ids);
    }
}
