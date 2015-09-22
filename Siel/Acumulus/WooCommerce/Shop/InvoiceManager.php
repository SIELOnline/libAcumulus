<?php
namespace Siel\Acumulus\WooCommerce\Shop;

use DateTime;
use \Siel\Acumulus\Invoice\Source as BaseSource;
use \Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;
use \Siel\Acumulus\WooCommerce\Invoice\Source;
use WP_Query;

class InvoiceManager extends BaseInvoiceManager {

  protected function sourceTypeToShopType($invoiceSourceType) {
    switch ($invoiceSourceType) {
      case Source::Order:
        return 'shop_order';
      case Source::CreditNote:
        return 'shop_order_refund';
      default:
        $this->config->getLog()->error('InvoiceManager::sourceTypeToShopType(%s): unknown', $invoiceSourceType);
        return '';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getInvoiceSourcesByIdRange($invoiceSourceType, $InvoiceSourceIdFrom, $InvoiceSourceIdTo) {
    // We use our own query here as defining a range of pots ids based on a
    // between does not seem to be possible with the query syntax.
    global $wpdb;
    $key = 'ID';
    $invoiceSourceIds = $wpdb->get_col($wpdb->prepare("SELECT `$key` FROM `{$wpdb->posts}` WHERE `$key` BETWEEN %u AND %u AND `post_type` = %s",
      $InvoiceSourceIdFrom, $InvoiceSourceIdTo, $this->sourceTypeToShopType($invoiceSourceType)));
    return Source::invoiceSourceIdsToSources($invoiceSourceType, $invoiceSourceIds);
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
  public function getInvoiceSourcesByReferenceRange($invoiceSourceType, $InvoiceSourceReferenceFrom, $InvoiceSourceReferenceTo) {
    // To be able to define the query we need to know under which meta key the
    // order number/reference is stored.
    // - WooCommerce Sequential Order Numbers (Pro) uses the key order_number.
    // - WC Sequential Order Numbers uses the keys order_number_formatted and
    //   order_number.
    // Both only work with orders, not refunds.
    if ((is_plugin_active('woocommerce-sequential-order-numbers/woocommerce-sequential-order-numbers.php') || is_plugin_active('woocommerce-sequential-order-numbers-pro/woocommerce-sequential-order-numbers-pro.php')) && $invoiceSourceType === Source::Order) {
      // Search for the order by the order number as assigned by the plugin.
      $args = array(
        'numberposts' => -1,
        'post_type'  => $this->sourceTypeToShopType($invoiceSourceType),
        'post_status' => 'publish',
        'fields'      => 'ids',
        'meta_query' => array(
          array(
            'key' => '_order_number',
            'value' => array($InvoiceSourceReferenceFrom, $InvoiceSourceReferenceTo),
            'compare' => 'BETWEEN',
          ),
        ),
      );
      $query = new WP_Query($args);
      $posts = $query->get_posts();
      sort($posts);
      return Source::invoiceSourceIdsToSources($invoiceSourceType, $posts);
    }
    else if (is_plugin_active('wc-sequential-order-numbers/Sequential_Order_Numbers.php') && $invoiceSourceType === Source::Order) {
      // This plugin has not been tested yet. It will probably give problems as
      // on installing it does not add the used meta keys retrospectively. So
      // I think this will only work for orders added after installation of this
      // plugin. But then, that is a bit the nature of the idea of sequential
      // order numbers: it should be used as of the beginning...
      $args = array(
        'numberposts' => -1,
        'post_type'  => $this->sourceTypeToShopType($invoiceSourceType),
        'post_status' => 'publish',
        'fields'      => 'ids',
        'meta_query' => array(
          array(
            'key' => '_order_number_formatted',
            'value' => array($InvoiceSourceReferenceFrom, $InvoiceSourceReferenceTo),
            'compare' => 'BETWEEN',
          ),
        ),
      );
      $query = new WP_Query($args);
      $posts = $query->get_posts();
      return Source::invoiceSourceIdsToSources($invoiceSourceType, $posts);
    }
    return parent::getInvoiceSourcesByReferenceRange($invoiceSourceType, $InvoiceSourceReferenceFrom, $InvoiceSourceReferenceTo);
  }

  /**
   * {@inheritdoc}
   */
  public function getInvoiceSourcesByDateRange($invoiceSourceType, DateTime $dateFrom, DateTime $dateTo) {
    $args = array(
      'numberposts' => -1,
      'post_type'  => $this->sourceTypeToShopType($invoiceSourceType),
      'post_status' => 'publish',
      'date_query' => array(
        array(
          'column' => 'post_modified',
          'after'    => array(
            'year'  => $dateFrom->format('Y'),
            'month' => $dateFrom->format('m'),
            'day'   => $dateFrom->format('d'),
          ),
          'before'    => array(
            'year'  => $dateTo->format('Y'),
            'month' => $dateTo->format('m'),
            'day'   => $dateTo->format('d'),
          ),
          'inclusive' => true,
        ),
      ),
    );
    $query = new WP_Query($args);
    $posts = $query->get_posts();
    return Source::invoiceSourceIdsToSources($invoiceSourceType, $posts);
  }

  /**
   * {@inheritdoc}
   *
   * This WooCommerce override applies the 'acumulus_invoice_created' filter.
   */
  protected function triggerInvoiceCreated(array &$invoice, BaseSource $invoiceSource) {
    $invoice = apply_filters('acumulus_invoice_created', $invoice, $invoiceSource);
  }

  /**
   * {@inheritdoc}
   *
   * This WooCommerce override applies the 'acumulus_invoice_completed' filter.
   */
  protected function triggerInvoiceCompleted(array &$invoice, BaseSource $invoiceSource) {
    $invoice = apply_filters('acumulus_invoice_completed', $invoice, $invoiceSource);
  }

  /**
   * {@inheritdoc}
   *
   * This WooCommerce override executes the 'acumulus_invoice_sent' action.
   */
  protected function triggerInvoiceSent(array $invoice, BaseSource $invoiceSource, array $result) {
    do_action('acumulus_invoice_sent', $invoice, $invoiceSource, $result);
  }

}
