<?php
namespace Siel\Acumulus\PrestaShop\Config;

use Context;
use Module;
use OrderState;
use PaymentModule;
use Siel\Acumulus\Config\ShopCapabilities as ShopCapabilitiesBase;
use TaxRulesGroup;

/**
 * Defines the PrestaShop webshop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     */
    public function getShopEnvironment()
    {
        $environment = [
            //'moduleVersion' => Acumulus::$module_version,
            'moduleVersion' => Module::getInstanceByName('acumulus')->version,
            'shopName' => $this->shopName,
            'shopVersion' => _PS_VERSION_,
        ];
        return $environment;
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenInfoSource()
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

    /**
     * {@inheritdoc}
     */
    protected function getTokenInfoRefund()
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

    /**
     * {@inheritdoc}
     */
    protected function getTokenInfoOrder()
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

    /**
     * {@inheritdoc}
     */
    protected function getTokenInfoShopProperties()
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

    /**
     * {@inheritdoc}
     */
    public function getShopDefaults()
    {
        return [
            'contactYourId' => '[id]', // Customer
            'companyName1' => '[company]', // InvoiceAddress
            'fullName' => '[firstname+lastname]', // InvoiceAddress
            'address1' => '[address1]', // InvoiceAddress
            'address2' => '[address2]', // InvoiceAddress
            'postalCode' => '[postcode]', // InvoiceAddress
            'city' => '[city]', // InvoiceAddress
            'vatNumber' => '[vat_number]', // InvoiceAddress
            'telephone' => '[phone|phone_mobile]', // InvoiceAddress
            'email' => '[email]', // Customer

            // Invoice lines defaults.
            'itemNumber' => '[product_reference|product_supplier_reference|product_ean13|product_upc]',
            'productName' => '[product_name]',
            'costPrice' => '[purchase_supplier_price]',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getShopOrderStatuses()
    {
        $statuses = OrderState::getOrderStates((int) Context::getContext()->language->id);
        $result = [];
        foreach ($statuses as $status) {
            $result[$status['id_order_state']] = $status['name'];
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethods()
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

    /**
     * {@inheritdoc}
     */
    public function getVatClasses()
    {
        $result = [];
        /** @var \stdClass[] $taxClasses */
        $taxClasses = TaxRulesGroup::getTaxRulesGroups();
        foreach ($taxClasses as $taxClass) {
            $result[$taxClass['id_tax_rules_group']] = $taxClass['name'];
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getLink($linkType)
    {
        switch ($linkType) {
            case 'config':
                // Does not work in PS1.6.
                return Context::getContext()->link->getAdminLink('AdminModules', true, [], ['configure' => 'acumulus']);
            case 'advanced':
                return Context::getContext()->link->getAdminLink('AdminAcumulusAdvanced', true);
            case 'batch':
                return Context::getContext()->link->getAdminLink('AdminAcumulusBatch', true);
            case 'register':
                return Context::getContext()->link->getAdminLink('AdminAcumulusRegister', true);
            case 'invoice':
                return Context::getContext()->link->getAdminLink('AdminAcumulusInvoice', true);
            case 'logo':
                return  __PS_BASE_URI__ . 'modules/acumulus/views/img/siel-logo.svg';
        }
        return parent::getLink($linkType);
    }
}
