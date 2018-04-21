<?php
namespace Siel\Acumulus\MyWebShop\Config;

use Siel\Acumulus\Config\ShopCapabilities as ShopCapabilitiesBase;

/**
 * Defines the MyWebShop webshop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     */
    public function getShopEnvironment()
    {
        // @todo: adapt by retrieving your module, webshop, and - optionally - CMS version.
        $environment = array(
            'moduleVersion' => 'todo',
            'shopName' => 'MyWebShop',
            'shopVersion' => 'todo' . ' (MyCMS: ' . 'todo' . ')',
        );
        return $environment;
    }

    /**
     * {@inheritdoc}
     */
    public function getTokenInfo()
    {
        // @t
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
            'item' => array(
                'class' => 'OrderDetail',
                'file' => 'classes/order/OrderDetail.php',
                'properties' => array(
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
        // @todo: fill in the appropriate property names, remove a line when no appropriate default exists.
        // @todo: ensure that your Creator class calls addPropertySource() to add all objects necessary.
        // @todo: ensure that all these objects are defined in the method getTokenInfo() above.
        return array(
            // Customer defaults.
            'contactYourId' => '[id]',
            'companyName1' => '[company1]',
            'companyName2' => '[company2]',
            'vatNumber' => '[vat_number]',
            'fullName' => '[firstname+lastname]',
            'salutation' => 'Dear [firstname+lastname]',
            'address1' => '[address1]',
            'address2' => '[address2]',
            'postalCode' => '[postcode]',
            'city' => '[city]',
            'telephone' => '[phone|phone_mobile]',
            'fax' => '[phone_mobile]',
            'email' => '[email]',
            'mark' => '',

            // Invoice defaults.
            // @todo: remove this line when it equals the default as set in Config::getKeyInfo().
            'description' => '[invoiceSource::type] [invoiceSource::reference]',
            'descriptionText' => '',
            'invoiceNotes' => '',

            // Invoice lines defaults.
            // @todo: ensure that your Creator class calls addPropertySource() and removePropertySource per item line to add all objects necessary.
            // @todo: ensure that all these objects are defined in the method getTokenInfo() above.
            'itemNumber' => '[product_reference|product_supplier_reference|product_ean13|product_upc]',
            'productName' => '[product_name]',
            'nature' => '',
            'costPrice' => '[purchase_supplier_price]',
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getShopOrderStatuses()
    {
        // @todo: adapt to MyWebshop's way of retrieving the list of order states.
        $states = OrderState::getOrderStates($this->translator->getLanguage());
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
        // @todo: adapt to MyWebshop's way of retrieving the list of (active) payment methods.
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
        // @todo: adapt to MyWebshop's way of creating links.
        switch ($formType) {
            case 'config':
                return Context::getContext()->link->getAdminLink('AdminModules', true, array(), array('configure' => 'acumulus'));
            case 'advanced':
                return Context::getContext()->link->getAdminLink('AdminAcumulusAdvanced', true);
            case 'batch':
                return Context::getContext()->link->getAdminLink('AdminAcumulusBatch', true);
        }
        return parent::getLink($formType);
    }
}
