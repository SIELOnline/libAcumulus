<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Shop;

use Automattic\WooCommerce\Utilities\OrderUtil;
use DateTimeInterface;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;

use function count;
use function strlen;

/**
 * Implements the WooCommerce specific parts of the invoice manager.
 *
 * SECURITY REMARKS
 * ----------------
 * In WooCommerce/WordPress querying orders is done via the WooCommerce
 * {@see wc_get_orders()} function or the {@see \WC_Order_Query} class.
 * Escaping and sanitizing (or using placeholders) is done by these features.
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
                $this->getLog()->error('InvoiceManager::sourceTypeToShopType(%s): unknown Source type', $invoiceSourceType);
                return '';
        }
    }

    public function getInvoiceSourcesByIdRange(string $invoiceSourceType, int $invoiceSourceIdFrom, int $invoiceSourceIdTo): array
    {
        if (OrderUtil::custom_orders_table_usage_is_enabled()) {
            // HPOS usage is enabled.
            $args = [
                'field_query' => [
                    [
                        'field' => 'id',
                        'compare' => 'BETWEEN',
                        'value' => [
                            $invoiceSourceIdFrom,
                            $invoiceSourceIdTo,
                        ],
                    ],
                ],
            ];
        } else {
            // Traditional CPT-based orders are in use. So far for compatibility:
            // searching on a range of ids differs in WP_Query.
            $args = [
                'post__in' => range($invoiceSourceIdFrom, $invoiceSourceIdTo),
            ];
        }
        return $this->query2Sources($args, $invoiceSourceType);
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
     * To be able to define the query we need to know under which meta key
     * the order number/reference is stored.
     * - WooCommerce Sequential Order Numbers: _order_number.
     * - WooCommerce Sequential Order Numbers Pro: _order_number or _order_number_formatted.
     * - WC Sequential Order Numbers: _order_number or _order_number_formatted.
     * - Custom Order Numbers for WooCommerce (Pro): _alg_wc_custom_order_number.
     */
    public function getInvoiceSourcesByReferenceRange(string $sourceType, string $from, string $to, bool $fallbackToId): array
    {
        $args = null;
        // All only work with orders, not refunds.
        if ($sourceType === Source::Order) {
            if (is_plugin_active('woocommerce-sequential-order-numbers/woocommerce-sequential-order-numbers.php')) {
                // Search by the order number assigned by this plugin.
                $args = [
                    'meta_query' => [
                        [
                            'key' => '_order_number',
                            'value' => [
                                $from,
                                $to,
                            ],
                            'compare' => 'BETWEEN',
                            'type' => 'UNSIGNED',
                        ],
                    ],
                ];
            } elseif (is_plugin_active('woocommerce-sequential-order-numbers-pro/woocommerce-sequential-order-numbers.php')
                || is_plugin_active('wc-sequential-order-numbers/Sequential_Order_Numbers.php')
            ) {
                // Search by the order number assigned by this plugin. Note that
                // these plugins allow for text prefixes and suffixes.
                // Therefore, we allow for a lexicographical or a purely numeric
                // comparison.
                if (ctype_digit($from) && ctype_digit($to)) {
                    if (strlen($from) < 6 && strlen($to) < 6) {
                        // We assume non formatted search arguments.
                        $key = '_order_number';
                    } else {
                        // Formatted numeric search arguments: e.g. 'yyyynnnn'.
                        $key = '_order_number_formatted';
                    }
                    $sourceType = 'UNSIGNED';
                } else {
                    $key = '_order_number_formatted';
                    $sourceType = 'CHAR';
                }
                $args = [
                    'meta_query' => [
                        [
                            'key' => $key,
                            'compare' => 'BETWEEN',
                            'value' => [
                                $from,
                                $to,
                            ],
                            'type' => $sourceType,
                        ],
                    ],
                ];
            } elseif (is_plugin_active('custom-order-numbers-for-woocommerce-pro/custom-order-numbers-for-woocommerce-pro.php')
                || is_plugin_active('custom-order-numbers-for-woocommerce/custom-order-numbers-for-woocommerce.php')
            ) {
                // Search by the order number assigned by this plugin.
                $args = [
                    'meta_query' => [
                        [
                            'key' => '_alg_wc_custom_order_number',
                            'compare' => 'BETWEEN',
                            'value' => [
                                $from,
                                $to,
                            ],
                            'type' => 'UNSIGNED',
                        ],
                    ],
                ];
            }
        }
        $result = isset($args) ? $this->query2Sources($args, $sourceType) : [];
        return count($result) > 0 ? $result : parent::getInvoiceSourcesByReferenceRange($sourceType, $from, $to, $fallbackToId);
    }

    public function getInvoiceSourcesByDateRange(string $sourceType, DateTimeInterface $dateFrom, DateTimeInterface $dateTo): array
    {
        $args = [
            'date_modified' => sprintf('%d...%d', $dateFrom->getTimestamp(), $dateTo->getTimestamp()),
        ];
        return $this->query2Sources($args, $sourceType);
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
        // Add default arguments.
        $args += [
            'type' => $this->sourceTypeToShopType($invoiceSourceType),
            // @todo: unclear why this line was added:
            //   contra: it will restrict to current possible statuses
            //   possible pro: will it ignore orders that are still just a 'cart'?
            //'status' => array_keys(wc_get_order_statuses()),
            'limit' => -1,
        ];
        if ($sort) {
            $args += [
                'orderby' => 'id',
                'order' => 'ASC',
            ];
        }
        $orders = wc_get_orders($args);
        return $this->getSourcesByIdsOrSources($invoiceSourceType, $orders);
    }
}
