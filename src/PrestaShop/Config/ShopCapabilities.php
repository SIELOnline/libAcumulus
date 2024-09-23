<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Config;

use Context;
use Module;
use OrderState;
use PaymentModule;
use Siel\Acumulus\Config\ShopCapabilities as ShopCapabilitiesBase;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Meta;
use TaxRulesGroup;

/**
 * Defines the PrestaShop web shop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    protected function getTokenInfoSource(): array
    {
        $source = [
            'id',
            'id_shop',
            'date_add',
            'date_upd',
            'id_currency',
            'conversion_rate',
        ];
        return [
            'class' => ['Order', 'OrderSlip'],
            'file' => ['classes/Order.php', 'classes/OrderSlip.php'],
            'properties' => $source,
            'properties-more' => true,
        ];
    }

    protected function getTokenInfoRefund(): array
    {
        $refund = [
            'id_order',
            'total_products_tax_excl',
            'total_products_tax_incl',
            'total_shipping_tax_excl',
            'total_shipping_tax_incl',
            'amount',
            'shipping_cost',
            'shipping_cost_amount',
            'partial',
        ];

        return [
            'class' => 'OrderSlip',
            'file' => 'classes/OrderSlip.php',
            'properties' => $refund,
            'properties-more' => true,
        ];

    }

    protected function getTokenInfoOrder(): array
    {
        $order = [
            'current_state',
            'secure_key',
            'payment',
            'recyclable',
            'gift',
            'gift_message',
            'shipping_number',
            'total_discounts',
            'total_discounts_tax_incl',
            'total_discounts_tax_excl',
            'total_paid',
            'total_paid_tax_incl',
            'total_paid_tax_excl',
            'total_paid_real',
            'total_products',
            'total_products_wt',
            'total_shipping',
            'total_shipping_tax_incl',
            'total_shipping_tax_excl',
            'carrier_tax_rate',
            'total_wrapping',
            'total_wrapping_tax_incl',
            'total_wrapping_tax_excl',
            'invoice_number',
            'delivery_number',
            'invoice_date',
            'delivery_date',
            'valid',
            'reference',
        ];

        return [
            'class' => 'Order',
            'file' => 'classes/Order.php',
            'properties' => $order,
            'properties-more' => true,
        ];
    }

    protected function getTokenInfoShopProperties(): array
    {
        return [
            'address_invoice' => [
                'class' => 'Address',
                'file' => 'classes/Address.php',
                'properties' => [
                    'id_customer',
                    'id_manufacturer',
                    'id_supplier',
                    'id_warehouse',
                    'id_country',
                    'id_state',
                    'country',
                    'alias',
                    'company',
                    'lastname',
                    'firstname',
                    'address1',
                    'address2',
                    'postcode',
                    'city',
                    'other',
                    'phone',
                    'phone_mobile',
                    'vat_number',
                    'dni',
                ],
                'properties-more' => true,
            ],
            'address_delivery' => [
                'more-info' => $this->t('see_billing_address'),
                'class' => 'Address',
                'file' => 'classes/Address.php',
                'properties' => [
                    $this->t('see_above'),
                ],
                'properties-more' => false,
            ],
            'customer' => [
                'class' => 'Customer',
                'file' => 'classes/Customer.php',
                'properties' => [
                    'id',
                    'id_shop',
                    'id_shop_group',
                    'secure_key',
                    'note',
                    'id_gender',
                    'id_default_group',
                    'id_lang',
                    'lastname',
                    'firstname',
                    'birthday',
                    'email',
                    'newsletter',
                    'ip_registration_newsletter',
                    'newsletter_date_add',
                    'optin',
                    'website',
                    'company',
                    'siret',
                    'ape',
                    'outstanding_allow_amount',
                    'show_public_prices',
                    'id_risk',
                    'max_payment_days',
                    'passwd',
                    'last_passwd_gen',
                    'active',
                    'is_guest',
                    'deleted',
                    'date_add',
                    'date_upd',
                    'years',
                    'days',
                    'months',
                    'geoloc_id_country',
                    'geoloc_id_state',
                    'geoloc_postcode',
                    'logged',
                    'id_guest',
                    'groupBox',
                ],
                'properties-more' => true,
            ],
            'item' => [
                'class' => 'OrderDetail',
                'file' => 'classes/order/OrderDetail.php',
                'properties' => [
                    'product_id',
                    'product_attribute_id',
                    'product_name',
                    'product_quantity',
                    'product_quantity_in_stock',
                    'product_quantity_return',
                    'product_quantity_refunded',
                    'product_quantity_reinjected',
                    'product_price',
                    'reduction_percent',
                    'reduction_amount',
                    'reduction_amount_tax_incl',
                    'reduction_amount_tax_excl',
                    'group_reduction',
                    'product_quantity_discount',
                    'product_ean13',
                    'product_upc',
                    'product_reference',
                    'product_supplier_reference',
                    'product_weight',
                    'tax_name',
                    'tax_rate',
                    'tax_computation_method',
                    'id_tax_rules_group',
                    'ecotax',
                    'ecotax_tax_rate',
                    'discount_quantity_applied',
                    'download_hash',
                    'download_nb',
                    'download_deadline',
                    'unit_price_tax_incl',
                    'unit_price_tax_excl',
                    'total_price_tax_incl',
                    'total_price_tax_excl',
                    'total_shipping_price_tax_excl',
                    'total_shipping_price_tax_incl',
                    'purchase_supplier_price',
                    'original_product_price',
                    'original_wholesale_price',
                ],
                'properties-more' => true,
            ],
        ];
    }

    public function getDefaultShopMappings(): array
    {
        // PrestaShop: both addresses are always filled.
        return [
            DataType::Invoice => [
                // In PrestaShop you can enter a price with or without vat, the other being
                // automatically updated. So we can not know how prices where entered.
                Meta::PricesIncludeVat => null,
            ],
            DataType::Customer => [
                // Customer defaults.
                Fld::ContactYourId => '[source::getShopObject()::id_customer]', // Order|OrderSlip
                Fld::VatNumber => '[address_invoice::vat_number' // Address
                    . '|address_shipping::vat_number]', // Address
                Fld::Telephone => '[address_invoice::phone|address_invoice::phone_mobile'
                    . '|address_shipping::phone|address_shipping::phone_mobile]', // Address
                Fld::Telephone2 => '[address_shipping::phone|address_shipping::phone_mobile'
                    . '|address_invoice::phone|address_invoice::phone_mobile]', // Address
                Fld::Email => '[source::getOrder()::getShopObject()::getCustomer()::email]', // Customer
                Fld::Website => '[source::getOrder()::getShopObject()::getCustomer()::website]', // Customer
                Fld::Mark => '[source::getOrder()::getShopObject()::getCustomer()::note]', // Customer (but not used?)
            ],
            AddressType::Invoice => [ // address_invoice instanceof Address
                Fld::CompanyName1 => '[address_invoice::company]',
                Fld::FullName => '[address_invoice::firstname+address_invoice::lastname]',
                Fld::Address1 => '[address_invoice::address1]',
                Fld::Address2 => '[address_invoice::address2]',
                Fld::PostalCode => '[address_invoice::postcode]',
                Fld::City => '[address_invoice::city]',
                Meta::ShopCountryId => '[address_invoice::id_country]',
            ],
            AddressType::Shipping => [ // address_shipping instanceof Address
                Fld::CompanyName1 => '[address_shipping::company]',
                Fld::FullName => '[address_shipping::firstname+address_shipping::lastname]',
                Fld::Address1 => '[address_shipping::address1]',
                Fld::Address2 => '[address_shipping::address2]',
                Fld::PostalCode => '[address_shipping::postcode]',
                Fld::City => '[address_shipping::city]',
                Meta::ShopCountryId => '[address_shipping::id_country]',
            ],
            EmailAsPdfType::Invoice => [
                Fld::EmailTo => '[source::getOrder()::getShopObject()::getCustomer()::email]',
            ],
            LineType::Item => [ // item instanceof Item
                Meta::ProductId => '[item::getShopObject()::product_id]',
                Fld::ItemNumber => '[item::getShopObject()::product_reference'
                    . '|item::getShopObject()::product_ean13'
                    . '|item::getShopObject()::product_isbn'
                    . '|item::getShopObject()::product_upc'
                    . '|item::getShopObject()::product_mpn'
                    . '|item::getShopObject()::product_supplier_reference]',
                Fld::Product => '[item::getShopObject()::product_name]',
                Fld::Quantity => '[item::getShopObject()::product_quantity]',
                Fld::CostPrice => '[sign*item::getShopObject()::purchase_supplier_price]',
                Fld::UnitPrice => '[sign*item::getShopObject()::unit_price_tax_excl]',
                Meta::UnitPriceInc => '[sign*item::getShopObject()::unit_price_tax_incl]',
                Meta::LineAmount => '[sign*item::getShopObject()::total_price_tax_excl]',
                Meta::LineAmountInc => '[sign*item::getShopObject()::total_price_tax_incl]',
                Fld::VatRate => '[item::getShopObject()::rate]',  //@todo: not set for orderSlips: add logic, also to set Meta::VatRateSource
            ],
        ];
    }

    public function getShopOrderStatuses(): array
    {
        $statuses = OrderState::getOrderStates(Context::getContext()->language->id);
        $result = [];
        foreach ($statuses as $status) {
            $result[$status['id_order_state']] = $status['name'];
        }
        return $result;
    }

    public function getPaymentMethods(): array
    {
        $paymentModules = PaymentModule::getInstalledPaymentModules();
        $result = [];
        foreach($paymentModules as $paymentModule)
        {
            $module = Module::getInstanceById($paymentModule['id_module']);
            $result[$module->name] = $module->displayName;
        }
        return $result;
    }

    public function getVatClasses(): array
    {
        $result = [];
        /** @var array[] $taxClasses */
        $taxClasses = TaxRulesGroup::getTaxRulesGroups();
        foreach ($taxClasses as $taxClass) {
            $result[$taxClass['id_tax_rules_group']] = $taxClass['name'];
        }
        return $result;
    }

    public function getLink(string $linkType): string
    {
        switch ($linkType) {
            case 'register':
            case 'activate':
            case 'mappings':
            case 'batch':
            case 'invoice':
                $action = ucfirst($linkType);
                return Context::getContext()->link->getAdminLink("AdminAcumulus$action");
            case 'settings':
                return Context::getContext()->link->getAdminLink('AdminModules', true, [], ['configure' => 'acumulus']);
            case 'fiscal-address-setting':
                return Context::getContext()->link->getAdminLink('AdminTaxes') . '#form';
            case 'logo':
                return  __PS_BASE_URI__ . 'modules/acumulus/views/img/siel-logo.svg';
            case 'pro-support-image':
                return  __PS_BASE_URI__ . 'modules/acumulus/views/img/pro-support-prestashop.png';
            case 'pro-support-link':
                return 'https://pay.siel.nl/?p=KL9pPSIIdMzqIieXB0FA56mTgyOvlEfUEosWM3ODsTZODa0P';
        }
        return parent::getLink($linkType);
    }

    public function getFiscalAddressSetting(): string
    {
        return 'PS_TAX_ADDRESS_TYPE'; // 'id_address_invoice' or 'id_address_delivery'
    }
}
