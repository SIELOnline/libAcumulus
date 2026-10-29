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
use Siel\Acumulus\Shop\ConfigFormTranslations;
use WC_Tax;

use function function_exists;
use function strlen;

/**
 * Defines the WooCommerce web shop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     *
     * WooCommerce core does not support entering a VAT number. However, various
     * plugins exist that do allow so, and that also allow for the reversed VAT
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
        // WooCommerce: The properties for both addresses are always filled, though I did
        // receive some messages indicating that address fields were not sent to Acumulus.
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
                $result[$id] = $paymentGateway->title ?? $id;
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
        // 'field_productName' comes from the config form translation and these are not
        // always loaded when we arrive here: @todo: why is that: only with a unit test?
        $this->translator->add(new ConfigFormTranslations());

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

    public function getLink(string $linkType, mixed $parameter = null): string
    {
        return match ($linkType) {
            'register', 'activate', 'batch' => admin_url("admin.php?page=acumulus_$linkType"),
            'settings', 'mappings' => admin_url("options-general.php?page=acumulus_$linkType"),
            'fiscal-address-setting' => admin_url('admin.php?page=wc-settings&tab=tax'),
            'logo' => home_url('wp-content/plugins/acumulus/Acumulus-Online-Boekhouden_icon_150.png'),
            'pro-support-image' => home_url('wp-content/plugins/acumulus/pro-support-woocommerce.png'),
            'pro-support-link' => 'https://pay.siel.nl/?p=3t0EasGQCcX0lPlraqMiGkTxFRmRo3zicBbhMtmD69bGozBl',
            'product' =>  admin_url("post.php?post=$parameter&action=edit"),
            default => parent::getLink($linkType, $parameter),
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
