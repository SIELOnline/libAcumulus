<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection  Most parent methods are base/no-op implementations.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\HikaShop\Config;

use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Joomla\Config\ShopCapabilities as ShopCapabilitiesBase;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Meta;

/**
 * Defines the HikaShop web shop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    protected function getTokenInfoSource(): array
    {
        $source = [
            'order_id',
            'order_billing_address_id',
            'order_shipping_address_id',
            'order_user_id',
            'order_status',
            'order_type',
            'order_number',
            'order_created',
            'order_modified',
            'order_invoice_id',
            'order_invoice_number',
            'order_invoice_created',
            'order_currency_id',
            'order_currency_info',
            'order_full_price',
            'order_discount_code',
            'order_discount_price',
            'order_discount_tax',
            'order_payment_id',
            'order_payment_method',
            'order_payment_price',
            'order_payment_tax',
            'order_shipping_id',
            'order_shipping_method',
            'order_shipping_price',
            'order_shipping_tax',
            'order_partner_id',
            'order_partner_price',
            'order_partner_paid',
            'order_partner_currency_id',
            'order_ip',
            'order_site_id',
            'comment',
            'deliverydate',
            'order_lang',
            'order_token',
        ];

        return [
            'table' => 'hikashop_order',
            'properties' => $source,
            'properties-more' => true,
        ];
    }

    protected function getTokenInfoShopProperties(): array
    {
        return [
            'billing_address' => [
                'table' => 'hikashop_address',
                'properties' => [
                    'address_id',
                    'address_user_id',
                    'address_title',
                    'address_firstname',
                    'address_middle_name',
                    'address_lastname',
                    'address_company',
                    'address_street',
                    'address_street2',
                    'address_post_code',
                    'address_city',
                    'address_telephone',
                    'address_telephone2',
                    'address_fax',
                    'address_state',
                    'address_country',
                    'address_published',
                    'address_vat',
                    'address_default',
                    'address_type',
                ],
                'properties-more' => false,
            ],
            'shipping_address' => [
                'table' => 'hikashop_address',
                'additional-info' => $this->t('see_billing_address'),
                'properties' => [
                    $this->t('see_above'),
                ],
                'properties-more' => false,
            ],
            'customer' => [
                'table' => 'hikashop_customer',
                'properties' => [
                    'user_id',
                    'user_cms_id',
                    'user_email',
                    'user_partner_email',
                    'user_params',
                    'user_partner_id',
                    'user_partner_price',
                    'user_partner_paid',
                    'user_created_ip',
                    'user_unpaid_amount',
                    'user_partner_currency_id',
                    'user_created',
                    'user_currency_id',
                    'user_partner_activated',
                ],
                'properties-more' => false,
            ],
            'item' => [
                'table' => 'hikashop_order_product',
                'additional-info' => $this->t('invoice_lines_only'),
                'properties' => [
                    'product_id',
                    'order_product_quantity',
                    'order_product_name',
                    'order_product_code',
                    'order_product_price',
                    'order_product_tax',
                    'order_product_options',
                    'order_product_option_parent_id',
                    'order_product_tax_info',
                    'order_product_wishlist_id',
                    'order_product_wishlist_product_id',
                    'order_product_shipping_id',
                    'order_product_shipping_method',
                    'order_product_shipping_price',
                    'order_product_shipping_tax',
                    'order_product_shipping_params'
                ],
                'properties-more' => true,
            ],
        ];
    }

    public function getDefaultShopMappings(): array
    {
        return [
            DataType::Invoice => [
                Meta::VatBreakdown => '[source::getVatBreakdown()]',
                // In HikaShop you can enter a price with or without vat, the other being
                // automatically updated. So we can not know how prices where entered.
                Meta::PricesIncludeVat => null,
            ],
            DataType::Customer => [
                // Customer defaults.
                Fld::ContactYourId => '[source::getSource()::order_user_id]',
                Fld::VatNumber => '[source::getSource()::billing_address::address_vat|source::getSource()::shipping_address::address_vat]',
                Fld::Telephone =>
                    '[source::getSource()::billing_address::address_telephone|source::getSource()::shipping_address::address_telephone]',
                Fld::Telephone2 =>
                    '[source::getSource()::billing_address::address_telephone2' .
                    '|source::getSource()::shipping_address::address_telephone2' .
                    '|source::getSource()::shipping_address::address_telephone]',
                Fld::Fax => '[source::getSource()::billing_address::address_fax|source::getSource()::shipping_address::address_fax]',
                Fld::Email => '[source::getSource()::customer::user_email|source::getSource()::customer::email]',
            ],
            AddressType::Invoice => [
                Fld::CompanyName1 => '[source::getSource()::billing_address::address_company]',
                Fld::FullName =>
                    '[source::getSource()::billing_address::address_firstname' .
                    '+source::getSource()::billing_address::address_middle_name' .
                    '+source::getSource()::billing_address::address_lastname' .
                    '|source::getSource()::customer::name]',
                Fld::Address1 => '[source::getSource()::billing_address::address_street1]',
                Fld::Address2 => '[source::getSource()::billing_address::address_street2]',
                Fld::PostalCode => '[source::getSource()::billing_address::address_postcode]',
                Fld::City => '[source::getSource()::billing_address::address_city]',
                Fld::CountryCode => '[source::getSource()::billing_address::address_country_code_2]',
                Meta::ShopCountryName => '[source::getSource()::billing_address::address_country_name]',
            ],
            AddressType::Shipping => [
                Fld::CompanyName1 => '[source::getSource()::shipping_address::address_company]',
                Fld::FullName =>
                    '[source::getSource()::shipping_address::address_firstname' .
                    '+source::getSource()::shipping_address::address_middle_name' .
                    '+source::getSource()::shipping_address::address_lastname' .
                    '|source::getSource()::customer::name]',
                Fld::Address1 => '[source::getSource()::shipping_address::address_street1]',
                Fld::Address2 => '[source::getSource()::shipping_address::address_street2]',
                Fld::PostalCode => '[source::getSource()::shipping_address::address_postcode]',
                Fld::City => '[source::getSource()::shipping_address::address_city]',
                Fld::CountryCode => '[source::getSource()::shipping_address::address_country_code_2]',
                Meta::ShopCountryName => '[source::getSource()::shipping_address::address_country_name_english]',
            ],
            EmailAsPdfType::Invoice => [
                Fld::EmailTo => '[source::getSource()::customer::user_email|source::getSource()::customer::email]',
            ],
            LineType::Item => [
                Fld::ItemNumber => '[item::getShopObject()::order_product_code]',
                Fld::Product => '[item::getShopObject()::order_product_name]',
                Fld::Quantity => '[item::getShopObject()::order_product_quantity]',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     *
     * HikaShop does not know refunds.
     */
    public function getSupportedInvoiceSourceTypes(): array
    {
        $result = parent::getSupportedInvoiceSourceTypes();
        unset($result[Source::CreditNote]);
        return $result;
    }

    public function getShopOrderStatuses(): array
    {
        /** @var \hikashopCategoryClass $class */
        $class = hikashop_get('class.category');
        $statuses = $class->loadAllWithTrans('status');

        $orderStatuses = [];
        foreach ($statuses as $status) {
            $orderStatuses[$status->category_name] = $status->translation;
        }
        return $orderStatuses;
    }

    /**
     * {@inheritdoc}
     *
     * This override removes the 'Use shop invoice number' option as HikaShop
     * does not have invoices.
     */
    public function getDateToUseOptions(): array
    {
        $result = parent::getDateToUseOptions();
        unset($result[Config::IssueDateSource_InvoiceCreate]);
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * The order_product table stores the category_namekey, while the shipping
     * and payment tables store the category_id. So which one we sue is a bit
     * arbitrary, but we use the category_namekey as id.
     */
    public function getVatClasses(): array
    {
        $result = [];
        /** @var \hikashopCategoryClass $categoryClass */
        $categoryClass = hikashop_get('class.category');
        /** @var \stdClass $category */
        $category = $categoryClass->get('tax');
        $taxClasses = $categoryClass->getChildren((int) $category->category_id, true, [], '', 0, 0);
        foreach ($taxClasses as $taxClass) {
            /** @var \stdClass $category */
            $result[$taxClass->category_namekey] = $taxClass->category_name;
        }
        return $result;
    }

    public function getPaymentMethods(): array
    {
        $result = [];
        /** @var \hikashopPluginsClass $pluginClass */
        $pluginClass = hikashop_get('class.plugins');
        $paymentPlugins = $pluginClass->getMethods('payment');
        foreach ($paymentPlugins as $paymentPlugin) {
            if (!empty($paymentPlugin->enabled) && !empty($paymentPlugin->payment_published)) {
                $result[$paymentPlugin->payment_id] = $paymentPlugin->payment_name;
            }
        }
        return $result;
    }

    public function getLink(string $linkType): string
    {
        switch ($linkType) {
            case 'fiscal-address-setting':
                return Route::_('index.php?option=com_hikashop&ctrl=config#main_tax');
            case 'pro-support-image':
                return Uri::root(true) . '/administrator/components/com_acumulus/media/pro-support-hikashop.png';
            case 'pro-support-link':
                return 'https://pay.siel.nl/?p=b5TeLbPw6BtNXRioORwnUtNbpU3yhUAgXLuuEMgk5zcttHbU';
        }
        return parent::getLink($linkType);
    }

    public function getFiscalAddressSetting(): string
    {
        return 'tax_zone_type';
    }
}
