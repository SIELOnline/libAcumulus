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
    $table = $wpdb->posts;
    $key = 'ID';
    $invoiceSourceIds = $wpdb->get_col($wpdb->prepare("SELECT `%s` FROM `%s` WHERE `%s` BETWEEN %u AND %u AND `post_type` = '%s'",
      $key, $table, $key, $InvoiceSourceIdFrom, $InvoiceSourceIdTo, $this->sourceTypeToShopType($invoiceSourceType)));
    return Source::invoiceSourceIdsToSources($invoiceSourceType, $invoiceSourceIds);
  }

  /**
   * {@inheritdoc}
   */
  public function getInvoiceSourcesByReferenceRange($invoiceSourceType, $InvoiceSourceReferenceFrom, $InvoiceSourceReferenceTo) {
    // To be able to define the query we need to know under which meta key the
    // order number/reference is stored. We support the "WooCommerce Sequential
    // Order Numbers (Pro)" plugins. If you know of other plugins that use
    // another key, please let us know.
    // @todo: test this with the free version.
    if (is_plugin_active('woocommerce-sequential-order-numbers') || is_plugin_active('woocommerce-sequential-order-numbers-pro')) {
      // Search for the order by the order number as assigned by the plugin.
      $args = array(
        'numberposts' => -1,
        'post_type'  => $this->sourceTypeToShopType($invoiceSourceType),
        'post_status' => 'publish',
        'meta_query' => array(
          array(
            'key' => 'order_number',
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
