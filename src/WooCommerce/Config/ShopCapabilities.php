<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection  Most parent methods are base/no-op implementations.
 */

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
use Siel\Acumulus\WooCommerce\Product\Product;
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
     * number in the order meta:
     * - WooCommerce EU VAT assistent: 'vat_number', or in older versions
     *   'VAT Number'. Note that this plugin is no longer supported (mid 2022).
     * - WooCommerce EU VAT number: _vat_number, see
     *   http://docs.woothemes.com/document/eu-vat-number-2/.
     * - current WooCommerce EU VAT number: billing_vat_number
     */
    public function getDefaultShopMappings(): array
    {
        // WooCommerce: The properties for both addresses are always filled.
        return [
            DataType::Invoice => [
            ],
            DataType::Customer => [
                // Customer defaults.
                //legacy: 'contactYourId' => '[customer_user]', // WC_Abstract_order
                Fld::ContactYourId => '[source::getOrder()::getShopObject()::get_customer_id()]',
                Fld::VatNumber => '[source::getOrder()::getShopObject()::get_meta(_billing_eu_vat_number)' // eu-vat-for-woocommerce
                    . '|source::getOrder()::getShopObject()::get_meta(_billing_vat_number)' // @todo: which plugin?
                    . '|source::getOrder()::getShopObject()::get_meta(_vat_number)' // @todo: which plugin?
                    . '|source::getOrder()::getShopObject()::get_meta(vat_number)' // EU Vat Assistant
                    . '|source::getOrder()::getShopObject()::get_meta(VAT Number)]',  // WooCommerce EU/UK VAT Compliance
                Fld::Telephone => '[source::getOrder()::getShopObject()::get_billing_phone()]',
                Fld::Telephone2 => '[source::getOrder()::getShopObject()::get_shipping_phone()]',
                Fld::Email => '[source::getOrder()::getShopObject()::get_billing_email()]',
            ],
            AddressType::Invoice => [
                Fld::CompanyName1 => '[source::getOrder()::getShopObject()::get_billing_company()]',
                Fld::FullName =>
                    '[source::getOrder()::getShopObject()::get_billing_first_name()+source::getOrder()::getShopObject()::get_billing_last_name()]',
                Fld::Address1 => '[source::getOrder()::getShopObject()::get_billing_address_1()]',
                Fld::Address2 => '[source::getOrder()::getShopObject()::get_billing_address_2()]',
                Fld::PostalCode => '[source::getOrder()::getShopObject()::get_billing_postcode()]',
                Fld::City => '[source::getOrder()::getShopObject()::get_billing_city()]',
                Fld::CountryCode => '[source::getOrder()::getShopObject()::get_billing_country()]',
            ],
            AddressType::Shipping => [
                Fld::CompanyName1 => '[source::getOrder()::getShopObject()::get_shipping_company()]',
                Fld::FullName =>
                    '[source::getOrder()::getShopObject()::get_shipping_first_name()+source::getOrder()::getShopObject()::get_shipping_last_name()]',
                Fld::Address1 => '[source::getOrder()::getShopObject()::get_shipping_address_1()]',
                Fld::Address2 => '[source::getOrder()::getShopObject()::get_shipping_address_2()]',
                Fld::PostalCode => '[source::getOrder()::getShopObject()::get_shipping_postcode()]',
                Fld::City => '[source::getOrder()::getShopObject()::get_shipping_city()]',
                Fld::CountryCode => '[source::getOrder()::getShopObject()::get_shipping_country()]',
            ],
            EmailAsPdfType::Invoice => [
                Fld::EmailTo => '[source::getOrder()::getShopObject()::get_billing_email()]',
            ],
            // Property sources for LineType::Item:
            // - source: Source
            // - item: Item,
            // - item::getShopObject(): WC_Order_Item_product
            // - product (or item::getProduct()): Product
            // - product::getShopObject(): ?WC_Product
            LineType::Item => [
                Fld::ItemNumber => '[product::getShopObject()::get_sku()|product::getShopObject()::get_global_unique_id()|"#".product::getShopObject()::get_id()]',
                Fld::Product => '[item::getShopObject()::get_name()]',
                // In refunds, the quantity will be negative and prices will be positive,
                // so no further need for us to correct with sign (unless quantity appears
                // to be 0).
                Fld::Quantity => '[item::getShopObject()::get_quantity()|source::getSign()]',
                Fld::UnitPrice => '[item::getShopObject()::unit_price_tax_excl]',
                Meta::UnitPriceInc => '[item::getShopObject()::unit_price_tax_incl]',
            ],
        ];
    }

    public function getShopOrderStatuses(): array
    {
        $result = [];
        $orderStatuses = wc_get_order_statuses();
        foreach ($orderStatuses as $key => $label) {
            if (str_starts_with($key, 'wc-')) {
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
        $keys = WC_Tax::get_tax_class_slugs();
        return ['standard' => $this->t('Standaard')] + array_combine($keys, $labels);
    }

    public function hasStockManagement(): bool
    {
        return true;
    }

    public function getProductMatchShopFields(): array
    {
        return [
            '[product::getShopObject()::get_sku()]' => __('SKU', 'woocommerce'),
            '[product::getShopObject()::get_global_unique_id()]' => __('GTIN, UPC, EAN or ISBN.', 'woocommerce'),
            '[product::getShopObject()::get_name()]' => $this->t('field_productName'),
//            '[product::getShopObject()::get_meta(' . Product::$acumulusProductIdField . ')]' => __('Custom Field', 'woocommerce'),
        ];
    }

    public function getDefaultShopConfig(): array
    {
        return [
            'productMatchShopField' => array_key_first($this->getProductMatchShopFields()),
            'productMatchAcumulusField' => Fld::ProductSku,
        ];
    }

    public function getLink(string $linkType): string
    {
        return match ($linkType) {
            'register', 'activate', 'batch' => admin_url("admin.php?page=acumulus_$linkType"),
            'settings', 'mappings' => admin_url("options-general.php?page=acumulus_$linkType"),
            'fiscal-address-setting' => admin_url('admin.php?page=wc-settings&tab=tax'),
            'logo' => home_url('wp-content/plugins/acumulus/siel-logo.svg'),
            'pro-support-image' => home_url('wp-content/plugins/acumulus/pro-support-woocommerce.png'),
            'pro-support-link' => 'https://pay.siel.nl/?p=3t0EasGQCcX0lPlraqMiGkTxFRmRo3zicBbhMtmD69bGozBl',
            default => parent::getLink($linkType),
        };
    }

    public function hasOrderList(): bool
    {
        return true;
    }

    public function getFiscalAddressSetting(): string
    {
        return 'woocommerce_tax_based_on';
    }
}
