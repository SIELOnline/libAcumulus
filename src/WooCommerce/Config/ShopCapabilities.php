<?php
namespace Siel\Acumulus\WooCommerce\Config;

use Siel\Acumulus\Config\ShopCapabilities as ShopCapabilitiesBase;
use Siel\Acumulus\Config\Config;
use WC_Tax;

/**
 * Defines the WooCommerce web shop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     */
    protected function getTokenInfoSource(): array
    {
        $source = [
            'date_created',
            'date_modified',
            'discount_total',
            'discount_tax',
            'shipping_total',
            'shipping_tax',
            'shipping_method',
            'cart_tax',
            'total',
            'total_tax',
            'subtotal',
            'used_coupons',
            'item_count',
            'version',
        ];

        return [
            'class' => 'WC_Abstract_Order',
            'file' => 'wp-content/plugins/woocommerce/includes/abstracts/abstract-wc-order.php',
            'properties' => $source,
            'properties-more' => true,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenInfoRefund(): array
    {
        $refund = [
            'amount',
            'reason',
        ];

        return [
            'more-info' => $this->t('refund_only'),
            'class' => 'WC_Order_Refund',
            'file' => 'wp-content/plugins/woocommerce/includes/class-wc-order-refund.php',
            'properties' => $refund,
            'properties-more' => true,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenInfoOrder(): array
    {
        $order = [
            'order_number',
            'order_key',
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
            'payment_method',
            'payment_method_title',
            'transaction_id',
            'checkout_payment_url',
            'checkout_order_received_url',
            'cancel_order_url',
            'view_order_url',
            'customer_id',
            'customer_ip_address',
            'customer_user_agent',
            'customer_note',
            'date_completed',
            'date_paid',
            'created_via',
        ];

        return [
            'more-info' => $this->t('original_order_for_refund'),
            'class' => 'WC_Order',
            'file' => 'wp-content/plugins/woocommerce/includes/class-wc-order.php',
            'properties' => $order,
            'properties-more' => true,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenInfoShopProperties(): array
    {
        $meta = [
            'vat_number (With EU VAT plugin only)',
        ];
        $result = [
            'meta' => [
                'table' => 'postmeta',
                'additional-info' => $this->t('see_post_meta'),
                'properties' => $meta,
                'properties-more' => true,
            ],
            'order_meta' => [
                'table' => 'postmeta',
                'additional-info' => $this->t('meta_original_order_for_refund'),
                'properties' => [
                    $this->t('see_above'),
                ],
                'properties-more' => false,
            ],
            'item' => [
                'class' => 'WC_Abstract_Order::expand_item_meta()',
                'file' => 'wp-content/plugins/woocommerce/includes/abstracts/abstract-wc-order.php',
                'additional-info' => $this->t('invoice_lines_only'),
                'properties' => [
                    'name',
                    'type',
                    'qty',
                    'tax_class',
                    'product_id',
                    'variation_id',
                ],
                'properties-more' => true,
            ],
            'product' => [
                'class' => 'WC_Product',
                'file' => 'wp-content/plugins/woocommerce/includes/abstracts/abstract-wc-product.php',
                'additional-info' => $this->t('invoice_lines_only'),
                'properties' => [
                    'title',
                    'type',
                    'width',
                    'length',
                    'height',
                    'weight',
                    'price',
                    'regular_price',
                    'sale_price',
                    'product_image_gallery',
                    'sku',
                    'stock',
                    'total_stock',
                    'downloadable',
                    'virtual',
                    'sold_individually',
                    'tax_status',
                    'tax_class',
                    'manage_stock',
                    'stock_status',
                    'backorders',
                    'featured',
                    'visibility',
                    'variation_id',
                    'shipping_class',
                    'shipping_class_id',
                ],
                'properties-more' => true,
            ],
        ];
        if (function_exists('is_wc_booking_product')) {
            $result['booking'] = [
                'class' => 'WC_Booking',
                'file' => 'wp-content/plugins/woocommerce-bookings/includes/data-objects/class-wc-booking.php',
                'additional-info' => $this->t('invoice_lines_only'),
                'properties' => [
                    'id',
                    'cost',
                    'start_date',
                    'end_date',
                    'google_calendar_event_id',
                    'person_counts',
                    'persons',
                    'persons_total',
                    'resource_id',
                    'product_id',
                    'status',
                    'is_all_day',
                ],
                'properties-more' => true,
            ];
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getShopDefaults(): array
    {
        return [
            // Customer defaults.
            //legacy: 'contactYourId' => '[customer_user]', // WC_Abstract_order
            'contactYourId' => '[customer_id]', // WC_Abstract_order
            'companyName1' => '[billing_company]', // WC_Abstract_order
            'fullName' => '[billing_first_name+billing_last_name]', // WC_Abstract_order
            'address1' => '[billing_address_1]', // WC_Abstract_order
            'address2' => '[billing_address_2]', // WC_Abstract_order
            'postalCode' => '[billing_postcode]', // WC_Abstract_order
            'city' => '[billing_city]', // WC_Abstract_order
            // The EU VAT Number plugin allows customers to indicate their VAT-
            // number with which they can apply for the reversed VAT scheme. The
            // vat number is stored under the '_vat_number' meta key, though
            // older versions did so under the 'VAT Number' key.
            // See http://docs.woothemes.com/document/eu-vat-number-2/
            'vatNumber' => '[vat_number|VAT Number]', // Post meta
            'telephone' => '[billing_phone]', // WC_Abstract_order
            'email' => '[billing_email]', // WC_Abstract_order

            // Invoice lines defaults.
            'itemNumber' => '[sku]',
            'productName' => '[name]',
            'costPrice' => '[cost_price]',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getShopOrderStatuses(): array
    {
        $result = [];
        $orderStatuses = wc_get_order_statuses();
        foreach ($orderStatuses as $key => $label) {
            if (substr($key, 0, strlen('wc-')) === 'wc-') {
                $key = substr($key, strlen('wc-'));
            }
            $result[$key] = $label;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override removes the 'Use invoice #' option as WC does not have
     * separate invoices.
     */
    public function getInvoiceNrSourceOptions(): array
    {
        $result = parent::getInvoiceNrSourceOptions();
        unset($result[Config::InvoiceNrSource_ShopInvoice]);
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override removes the 'Use invoice date' option as WC does not have
     * separate invoices.
     */
    public function getDateToUseOptions(): array
    {
        $result = parent::getDateToUseOptions();
        unset($result[Config::InvoiceDate_InvoiceCreate]);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethods(): array
    {
        $result = [];
        /** @noinspection PhpUndefinedFieldInspection */
        $paymentGateways = wc()->payment_gateways->payment_gateways();
        foreach ($paymentGateways as $id => $paymentGateway) {
            if (isset($paymentGateway->enabled) && $paymentGateway->enabled === 'yes') {
                $result[$id] = $paymentGateway->title;
            }
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getVatClasses(): array
    {
        // Standard tax class is not stored in table wc_tax_rate_classes.
        $labels = WC_Tax::get_tax_classes();
        $keys =  WC_Tax::get_tax_class_slugs();
        return ['standard' => $this->t('Standaard')] + array_combine($keys, $labels);
    }

    /**
     * {@inheritdoc}
     */
    public function getLink(string $linkType): string
    {
        switch ($linkType) {
            case 'register':
            case 'activate':
            case 'batch':
                return admin_url("admin.php?page=acumulus_$linkType");
            case 'config':
            case 'advanced':
                return admin_url("options-general.php?page=acumulus_$linkType");
            case 'logo':
                return home_url('wp-content/plugins/acumulus/siel-logo.svg');
            case 'pro-support-image':
                return home_url('wp-content/plugins/acumulus/pro-support-woocommerce.png');
            case 'pro-support-link':
                return 'https://pay.siel.nl/?p=3t0EasGQCcX0lPlraqMiGkTxFRmRo3zicBbhMtmD69bGozBl';
        }
        return parent::getLink($linkType);
    }

    public function hasOrderList(): bool
    {
        return true;
    }

}
