<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Config;

use Siel\Acumulus\Config\ShopCapabilities as ShopCapabilitiesBase;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Meta;
use WC_Tax;

use function function_exists;
use function strlen;

/**
 * Defines the WooCommerce web shop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
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
     *
     * WooCommerce core does not support entering a VAT number. However, various
     * plugins exists that do allow so, and that also allow for the reversed VAT
     * and EU-VAT schemes. These plugins use different keys to store the vat
     * number in the order post meta:
     * - WooCommerce EU VAT assistent: 'vat_number', or in older versions
     *   'VAT Number'. Note that this plugin is no longer supported (mid 2022).
     * - WooCommerce EU VAT number: _vat_number, see
     *   http://docs.woothemes.com/document/eu-vat-number-2/.
     * - current WooCommerce EU VAT number: billing_vat_number
     */
    public function getDefaultShopConfig(): array
    {
        return [
            // @legacy: old way of storing mappings.
            //legacy: 'contactYourId' => '[customer_user]', // WC_Abstract_order
            'contactYourId' => '[customer_id]', // WC_Abstract_order
            'companyName1' => '[billing_company]', // WC_Abstract_order
            'fullName' => '[billing_first_name+billing_last_name]', // WC_Abstract_order
            'address1' => '[billing_address_1]', // WC_Abstract_order
            'address2' => '[billing_address_2]', // WC_Abstract_order
            'postalCode' => '[billing_postcode]', // WC_Abstract_order
            'city' => '[billing_city]', // WC_Abstract_order
            'vatNumber' => '[billing_eu_vat_number|billing_vat_number|_vat_number|vat_number|VAT Number]', // Post meta
            'telephone' => '[billing_phone]', // WC_Abstract_order
            'email' => '[billing_email]', // WC_Abstract_order

            // Invoice lines defaults.
            'itemNumber' => '[sku]',
            'productName' => '[name]',
            'costPrice' => '[cost_price]',
        ];
    }

    public function getDefaultShopMappings(): array
    {
        // WooCommerce: The properties for both addresses are always filled.
        return [
            DataType::Invoice => [
                // @todo: fields that come from the Order or its metadata, because, if it
                //   comes from Source, it is not shop specific.
            ],
            DataType::Customer => [
                // Customer defaults.
                //legacy: 'contactYourId' => '[customer_user]', // WC_Abstract_order
                Fld::ContactYourId => '[source::getOrder()::getSource()::get_customer_id()]',
                Fld::VatNumber => '[source::getOrder()::getSource()::get_meta(_billing_eu_vat_number)' // eu-vat-for-woocommerce
                    . '|source::getOrder()::getSource()::get_meta(_billing_vat_number)' // @todo: which plugin?
                    . '|source::getOrder()::getSource()::get_meta(_vat_number)' // @todo: which plugin?
                    . '|source::getOrder()::getSource()::get_meta(vat_number)' // EU Vat Assistant
                    . '|source::getOrder()::getSource()::get_meta(VAT Number)]',  // WooCommerce EU/UK VAT Compliance
                Fld::Telephone => '[source::getOrder()::getSource()::get_billing_phone()]',
                Fld::Telephone2 => '[source::getOrder()::getSource()::get_shipping_phone()]',
                Fld::Email => '[source::getOrder()::getSource()::get_billing_email()]',
            ],
            AddressType::Invoice => [
                Fld::CompanyName1 => '[source::getOrder()::getSource()::get_billing_company()]',
                Fld::FullName =>
                    '[source::getOrder()::getSource()::get_billing_first_name()+source::getOrder()::getSource()::get_billing_last_name()]',
                Fld::Address1 => '[source::getOrder()::getSource()::get_billing_address_1()]',
                Fld::Address2 => '[source::getOrder()::getSource()::get_billing_address_2()]',
                Fld::PostalCode => '[source::getOrder()::getSource()::get_billing_postcode()]',
                Fld::City => '[source::getOrder()::getSource()::get_billing_city()]',
                Fld::CountryCode => '[source::getOrder()::getSource()::get_billing_country()]',
            ],
            AddressType::Shipping => [
                Fld::CompanyName1 => '[source::getOrder()::getSource()::get_shipping_company()]',
                Fld::FullName =>
                    '[source::getOrder()::getSource()::get_shipping_first_name()+source::getOrder()::getSource()::get_shipping_last_name()]',
                Fld::Address1 => '[source::getOrder()::getSource()::get_shipping_address_1()]',
                Fld::Address2 => '[source::getOrder()::getSource()::get_shipping_address_2()]',
                Fld::PostalCode => '[source::getOrder()::getSource()::get_shipping_postcode()]',
                Fld::City => '[source::getOrder()::getSource()::get_shipping_city()]',
                Fld::CountryCode => '[source::getOrder()::getSource()::get_shipping_country()]',
            ],
            EmailAsPdfType::Invoice => [
                Fld::EmailTo => '[source::getOrder()::getSource()::get_billing_email()]',
            ],
            LineType::Item => [
                Meta::Id => '[item::get_id()]',
                Fld::ItemNumber => '[product::get_sku()]',
                Fld::Product => '[item::get_name()]',
                Meta::ProductId => '[product::get_id()]',
                Fld::Quantity => '[item::get_quantity()]',
            ],
        ];
    }
    public function getShopOrderStatuses(): array
    {
        $result = [];
        $orderStatuses = wc_get_order_statuses();
        foreach ($orderStatuses as $key => $label) {
            // PHP8: str_starts_with()
            if (strncmp($key, 'wc-', 3) === 0) {
                $key = substr($key, strlen('wc-'));
            }
            /** @noinspection OffsetOperationsInspection */
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
        unset($result[Config::IssueDateSource_InvoiceCreate]);
        return $result;
    }

    public function getPaymentMethods(): array
    {
        $result = [];
        /** @noinspection PhpUndefinedFieldInspection */
        $paymentGateways = WC()->payment_gateways->payment_gateways();
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

    public function getLink(string $linkType): string
    {
        switch ($linkType) {
            case 'register':
            case 'activate':
            case 'batch':
                return admin_url("admin.php?page=acumulus_$linkType");
            /* @legacy: old way of showing settings. */
            case 'config':
            /* @legacy: old way of showing field references. */
            case 'advanced':
            case 'settings':
            case 'mappings':
                return admin_url("options-general.php?page=acumulus_$linkType");
            case 'fiscal-address-setting':
                return admin_url('admin.php?page=wc-settings&tab=tax');
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

    public function getFiscalAddressSetting(): string
    {
        return 'woocommerce_tax_based_on';
    }

    /**
     * WooCommerce switched to the new creation process!
     *
     * Note: in case of severe errors during the creation process: return false to revert
     * to the old "tried and tested" code.
     */
    public function usesNewCode(): bool
    {
        //return false; // Emergency revert: remove the // at the beginning of this line!
        return true;
    }
}
