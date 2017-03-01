<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use Context;
use Module;
use OrderState;
use PaymentModule;
use Siel\Acumulus\Shop\ShopCapabilities as ShopCapabilitiesBase;

/**
 * Defines the PrestaShop webshop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     */
    public function getTokenInfo()
    {
        return parent::getTokenInfo() + array(
            'source' => array(
                'class' => 'Order',
                'file' => 'classes/Order.php',
                'properties' => array(
                    'id_address_delivery',
                    'id_address_invoice',
                    'id_shop_group',
                    'id_shop',
                    'id_cart',
                    'id_currency',
                    'id_lang',
                    'id_customer',
                    'id_carrier',
                    'current_state',
                    'secure_key',
                    'payment',
                    'module',
                    'conversion_rate',
                    'recyclable = 1',
                    'gift',
                    'gift_message',
                    'mobile_theme',
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
                    'date_add',
                    'date_upd',
                    'reference',
                    'round_mode',
                    'round_type',
                ),
                'properties-more' => true,
            ),
            'address_invoice' => array(
                'class' => 'Address',
                'file' => 'classes/Address.php',
                'properties' => array(
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
                ),
                'properties-more' => true,
            ),
            'address_delivery' => array(
                'more-info' => $this->t('see_billing_address'),
                'class' => 'Address',
                'file' => 'classes/Address.php',
                'properties' => array(
                    $this->t('see_above'),
                ),
                'properties-more' => false,
            ),
            'customer' => array(
                'class' => 'Customer',
                'file' => 'classes/Customer.php',
                'properties' => array(
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
            'contactYourId' => '[id]', // Customer
            'companyName1' => '[company]', // InvoiceAddress
            'fullName' => '[first_name] [last_name]', // InvoiceAddress
            'address1' => '[address1]', // InvoiceAddress
            'address2' => '[address2]', // InvoiceAddress
            'postalCode' => '[postcode]', // InvoiceAddress
            'city' => '[city]', // InvoiceAddress
            'vatNumber' => '[vat_number]', // InvoiceAddress
            'telephone' => '[phone|phone_mobile]', // InvoiceAddress
            'email' => '[email]', // Customer
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getShopOrderStatuses()
    {
        $states = OrderState::getOrderStates((int) Context::getContext()->language->id);
        $result = array();
        foreach ($states as $state) {
            $result[$state['id_order_state']] = $state['name'];
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethods()
    {
        $paymentModules = PaymentModule::getInstalledPaymentModules();
        $result = array();
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
    public function getLink($formType)
    {
        switch ($formType) {
            case 'config':
                return Context::getContext()->link->getAdminLink('AdminModules', true) . '&module_name=acumulus&tab_module=billing_invoicing&configure=acumulus';
            case 'advanced':
                return Context::getContext()->link->getAdminLink('AdminAcumulusAdvanced', true);
            case 'batch':
                return Context::getContext()->link->getAdminLink('AdminAcumulusBatch', true);
        }
        return parent::getLink($formType);
    }
}
