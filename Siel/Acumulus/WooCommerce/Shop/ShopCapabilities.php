<?php
namespace Siel\Acumulus\WooCommerce\Shop;

use Siel\Acumulus\Invoice\ConfigInterface as InvoiceConfigInterface;
use Siel\Acumulus\Shop\ShopCapabilities as ShopCapabilitiesBase;

/**
 * Defines the WooCommerce webshop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     */
    public function getTokenInfo() {
        $order = array(
            'billing_first_name',
            'billing_last_name',
            'billing_company',
            'billing_address_1',
            'billing_address_2',
            'billing_city',
            'billing_state',
            'billing_postcode',
            'billing_country',
            'billing_phone',
            'billing_email',
            'shipping_first_name',
            'shipping_last_name',
            'shipping_company',
            'shipping_address_1',
            'shipping_address_2',
            'shipping_city',
            'shipping_state',
            'shipping_postcode',
            'shipping_country',
            'cart_discount',
            'cart_discount_tax',
            'shipping_method_title',
            'customer_user',
            'order_key',
            'order_discount',
            'order_tax',
            'order_shipping_tax',
            'order_shipping',
            'order_total',
            'order_currency',
            'payment_method',
            'payment_method_title',
            'customer_ip_address',
            'customer_user_agent',
        );
        $refund = array(
            'reason (refund only)',
            'date (refund only)',
        );
        $meta = array(
            'vat_number (With EU VAT plugin only)',
        );
        return array(
            'source' => array(
                'class' => 'WC_Abstract_Order',
                'file' => 'wp-content/plugins/woocommerce/includes/abstracts/abstract-wc-order.php',
                'properties' => array_merge($order, $refund),
                'properties-more' => true,
            ),
            'meta' => array(
                'more-info' => $this->t('see_post_meta'),
                'table' => 'postmeta',
                'properties' => $meta,
                'properties-more' => true,
            ),
            'order' => array(
                'more-info' => $this->t('original_order_for_refund'),
                'properties' => array(
                    $this->t('see_above'),
                ),
                'properties-more' => true,
            ),
            'order_meta' => array(
                'more-info' => $this->t('meta_original_order_for_refund'),
                'table' => 'postmeta',
                'properties' => array(
                    $this->t('see_above'),
                ),
                'properties-more' => true,
            ),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getShopDefaults()
    {
        return array(
            'contactYourId' => '[customer_user]', // WC_Abstact_order
            'companyName1' => '[billing_company]', // WC_Abstact_order
            'fullName' => '[billing_first_name] [billing_last_name]', // WC_Abstact_order
            'address1' => '[billing_address_1]', // WC_Abstact_order
            'address2' => '[billing_address_2]', // WC_Abstact_order
            'postalCode' => '[billing_postcode]', // WC_Abstact_order
            'city' => '[billing_city]', // WC_Abstact_order
            // The EU VAT Number plugin allows customers to indicate their VAT
            // number with which they can apply for the reversed VAT scheme. The vat
            // number is stored under the '_vat_number' meta key, though older
            // versions did so under the 'VAT Number' key.
            // See http://docs.woothemes.com/document/eu-vat-number-2/
            'vatNumber' => '[VAT Number|vat_number]', // Post neta
            'telephone' => '[billing_phone]', // WC_Abstact_order
            'email' => '[billing_email]', // WC_Abstact_order
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getShopOrderStatuses()
    {
        $result = array();
        $orderStatuses = wc_get_order_statuses();
        foreach ($orderStatuses as $key => $label) {
            $result[substr($key, strlen('wc-'))] = $label;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override removes the 'Use invoice #' option as WC does not have
     * separate invoices.
     */
    public function getInvoiceNrSourceOptions()
    {
        $result = parent::getInvoiceNrSourceOptions();
        unset($result[InvoiceConfigInterface::InvoiceNrSource_ShopInvoice]);
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override removes the 'Use invoice date' option as WC does not have
     * separate invoices.
     */
    public function getDateToUseOptions()
    {
        $result = parent::getDateToUseOptions();
        unset($result[InvoiceConfigInterface::InvoiceDate_InvoiceCreate]);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethods()
    {
        $result = array();
        $paymentGateways = WC()->payment_gateways->payment_gateways();
        foreach ($paymentGateways as $id => $paymentGateway) {
            if (isset($paymentGateway->enabled) && $paymentGateway->enabled === 'yes') {
                $result[$id] = $paymentGateway->title;
            }
        }
        return $result;
    }

  /**
   * {@inheritdoc}
   */
  public function getLink($formType)
  {
      switch ($formType) {
          case 'config':
              return admin_url('options-general.php?page=acumulus');
          case 'advanced':
              return admin_url('options-general.php?page=acumulus_advanced');
          case 'batch':
              return admin_url('admin.php?page=acumulus_batch');
      }
      return parent::getLink($formType);
  }
}
