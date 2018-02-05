<?php
namespace Siel\Acumulus\WooCommerce\Shop;

use DateTime;
use Siel\Acumulus\Invoice\Source as Source;
use Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;
use Siel\Acumulus\Invoice\Result;
use WP_Query;

class InvoiceManager extends BaseInvoiceManager
{
    /**
     * Helper method that converts our source type constants to a WP/WC post type.
     *
     * @param string $invoiceSourceType
     *
     * @return string
     */
    protected function sourceTypeToShopType($invoiceSourceType)
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

    /**
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByIdRange($invoiceSourceType, $InvoiceSourceIdFrom, $InvoiceSourceIdTo)
    {
        // We use our own query here as defining a range of post ids based on a
        // between does not seem to be possible with the query syntax.
        /** @var \wpdb $wpdb */
        global $wpdb;
        $key = 'ID';
        /** @noinspection SqlResolve */
        $invoiceSourceIds = $wpdb->get_col($wpdb->prepare("SELECT `$key` FROM `{$wpdb->posts}` WHERE `$key` BETWEEN %d AND %d AND `post_type` = %s",
            $InvoiceSourceIdFrom,
            $InvoiceSourceIdTo,
            $this->sourceTypeToShopType($invoiceSourceType)));
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
     *
     * If you know of other plugins, please let us know.
     */
    public function getInvoiceSourcesByReferenceRange($invoiceSourceType, $invoiceSourceReferenceFrom, $invoiceSourceReferenceTo)
    {
        // To be able to define the query we need to know under which meta key
        // the order number/reference is stored.
        // - WooCommerce Sequential Order Numbers uses _order_number.
        // - WooCommerce Sequential Order Numbers Pro uses _order_number or
        //   _order_number_formatted.
        // - WC Sequential Order Numbers uses _order_number or
        //   _order_number_formatted.
        // Both only work with orders, not refunds.
        if ($invoiceSourceType === Source::Order) {
            if (is_plugin_active('woocommerce-sequential-order-numbers/woocommerce-sequential-order-numbers.php')) {
                // Search by the order number as assigned by the plugin.
                $args = array(
                  'meta_query' => array(
                    array(
                      'key' => '_order_number',
                      'value' => array(
                        $invoiceSourceReferenceFrom,
                        $invoiceSourceReferenceTo,
                      ),
                      'compare' => 'BETWEEN',
                      'type' => 'UNSIGNED',
                    ),
                  ),
                );
                return $this->query2Sources($args, $invoiceSourceType);
            } elseif (is_plugin_active('woocommerce-sequential-order-numbers-pro/woocommerce-sequential-order-numbers.php')
              || is_plugin_active('wc-sequential-order-numbers/Sequential_Order_Numbers.php')) {
                // Search by the order number as assigned by the plugin. Note
                // that these plugins allow for text prefixes and suffixes,
                // therefore, we allow for a lexicographical or a purely numeric
                // comparison.
                if (is_numeric($invoiceSourceReferenceFrom) && is_numeric($invoiceSourceReferenceTo)) {
                    if (strlen($invoiceSourceReferenceFrom) < 6 && strlen($invoiceSourceReferenceTo) < 6) {
                        // We assume non formatted search arguments.
                        $key = '_order_number';
                    } else {
                        // Formatted numeric search arguments: e.g. yyyynnnn
                        $key = '_order_number_formatted';
                    }
                    $type = 'UNSIGNED';
                } else {
                    $key = '_order_number_formatted';
                    $type = 'CHAR';
                }
                $args = array(
                  'meta_query' => array(
                    array(
                      'key' => $key,
                      'value' => array(
                        $invoiceSourceReferenceFrom,
                        $invoiceSourceReferenceTo,
                      ),
                      'compare' => 'BETWEEN',
                      'type' => $type,
                    ),
                  ),
                );
                return $this->query2Sources($args, $invoiceSourceType);
            }
        }
        return parent::getInvoiceSourcesByReferenceRange($invoiceSourceType, $invoiceSourceReferenceFrom, $invoiceSourceReferenceTo);
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceSourcesByDateRange($invoiceSourceType, DateTime $dateFrom, DateTime $dateTo)
    {
        $args = array(
            'date_query' => array(
                array(
                    'column' => 'post_modified',
                    'after' => array(
                        'year' => $dateFrom->format('Y'),
                        'month' => $dateFrom->format('m'),
                        'day' => $dateFrom->format('d'),
                    ),
                    'before' => array(
                        'year' => $dateTo->format('Y'),
                        'month' => $dateTo->format('m'),
                        'day' => $dateTo->format('d'),
                    ),
                    'inclusive' => true,
                ),
            ),
        );
        return $this->query2Sources($args, $invoiceSourceType);
    }

    /**
     * {@inheritdoc}
     *
     * This WooCommerce override applies the 'acumulus_invoice_created' filter.
     */
    protected function triggerInvoiceCreated(array &$invoice, Source $invoiceSource, Result $localResult)
    {
        $invoice = apply_filters('acumulus_invoice_created', $invoice, $invoiceSource, $localResult);
    }

    /**
     * {@inheritdoc}
     *
     * This WooCommerce override applies the 'acumulus_invoice_send_before' filter.
     */
    protected function triggerInvoiceSendBefore(array &$invoice, Source $invoiceSource, Result $localResult)
    {
        $invoice = apply_filters('acumulus_invoice_send_before', $invoice, $invoiceSource, $localResult);
    }

    /**
     * {@inheritdoc}
     *
     * This WooCommerce override executes the 'acumulus_invoice_send_after' action.
     */
    protected function triggerInvoiceSendAfter(array $invoice, Source $invoiceSource, Result $result)
    {
        do_action('acumulus_invoice_send_after', $invoice, $invoiceSource, $result);
    }

    /**
     * Helper method to get a list of Source given a set of query arguments.
     *
     * @param array $args
     * @param string $invoiceSourceType
     * @param bool $sort
     *
     * @return \Siel\Acumulus\Invoice\Source[]
     */
    protected function query2Sources(array $args, $invoiceSourceType, $sort = true)
    {
        $this->getLog()->info('WooCommerce\InvoiceManager::query2Sources: args = %s', str_replace(array(' ', "\r", "\n", "\t"), '', var_export($args, true)));
        // Add default arguments.
        $args = $args + array(
                'fields' => 'ids',
                'posts_per_page' => -1,
                'post_type' => $this->sourceTypeToShopType($invoiceSourceType),
                'post_status' => array_keys(wc_get_order_statuses()),
            );
        $query = new WP_Query($args);
        $ids = $query->get_posts();
        if ($sort) {
            sort($ids);
        }
        return $this->getSourcesByIdsOrSources($invoiceSourceType, $ids);
    }
}
