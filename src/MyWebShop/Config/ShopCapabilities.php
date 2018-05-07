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
     * @inheritDoc
     */
    protected function getTokenInfoSource()
    {
        // @todo: fill in the common properties of your order and refund class.
        // @todo: If MyWebShop does not support refunds, fill in the properties of your Order class.
        $source = array(
            'date_created',
            'date_modified',
            'shipping_method',
            'total',
            'subtotal',
            'used_coupons',
            'item_count',
        );

        // @todo: complete the class and file name.
        return array(
            'class' => '/MyWebShop/BaseOrder',
            'file' => '.../BaseOrder.php',
            'properties' => $source,
            'properties-more' => true,
        );
    }

    /**
     * @inheritDoc
     */
    protected function getTokenInfoRefund()
    {
        // @todo: fill in the properties that are unique to your Refund class (i.e. do not appear in orders),
        // @todo: remove if MyWebShop does not support refunds.
        $refund = array(
            'amount',
            'reason',
        );

        // @todo: complete the class and file name.
        return array(
            'more-info' => $this->t('refund_only'),
            'class' => '/MyWebShop/Refund',
            'file' => '.../Refund.php',
            'properties' => $refund,
            'properties-more' => true,
        );
    }

    /**
     * @inheritDoc
     */
    protected function getTokenInfoOrder()
    {
        // @todo: fill in the properties that are unique to your Order class (i.e. do not appear in refunds),
        // @todo: remove if MyWebShop does not support refunds.
        $order = array(
            'order_number',
            'billing_first_name',
            'billing_last_name',
            'billing_company',
            'billing_address_1',
            'billing_address_2',
            '...',
        );

        // @todo: complete the class and file name.
        return array(
            'more-info' => $this->t('original_order_for_refund'),
            'class' => '/MyWebShop/Order',
            'file' => '.../Order.php',
            'properties' => $order,
            'properties-more' => true,
        );
    }

    /**
     * @inheritDoc
     */
    protected function getTokenInfoShopProperties()
    {
        // @todo: define the properties of other objects that may be used to fetch info from.
        // @todo: ensure that your Creator class calls addPropertySource() to all properties defined here.
        return parent::getTokenInfo() + array(
            // @todo: complete the class and file name.
            'address_invoice' => array(
                'class' => 'Address',
                'file' => 'classes/Address.php',
                'properties' => array(
                    'country',
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
                ),
                'properties-more' => true,
            ),
            // @todo: complete the class and file name.
            'address_delivery' => array(
                'more-info' => $this->t('see_billing_address'),
                'class' => 'Address',
                'file' => 'classes/Address.php',
                'properties' => array(
                    $this->t('see_above'),
                ),
                'properties-more' => false,
            ),
            // @todo: complete the class and file name.
            'customer' => array(
                'class' => 'Customer',
                'file' => 'classes/Customer.php',
                'properties' => array(
                    'id',
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
                    'date_add',
                    'date_upd',
                    'years',
                    'days',
                    'months',
                ),
                'properties-more' => true,
            ),
            // @todo: complete the class and file name.
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
                    'product_price',
                    'product_quantity_discount',
                    'product_ean13',
                    'product_upc',
                    'product_reference',
                    'product_supplier_reference',
                    'product_weight',
                    'tax_name',
                    'tax_rate',
                    'unit_price_tax_incl',
                    'unit_price_tax_excl',
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
        // @todo: ensure that all these objects are defined in the method getTokenInfoShopProperties() above.
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
            // @todo: ensure that all these objects are defined in the method getTokenInfoShopProperties() above.
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
