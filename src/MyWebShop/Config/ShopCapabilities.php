<?php
namespace Siel\Acumulus\MyWebShop\Config;

use Siel\Acumulus\Config\ShopCapabilities as ShopCapabilitiesBase;

/**
 * Defines the MyWebShop web shop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * @inheritDoc
     */
    protected function getTokenInfoSource(): array
    {
        // @todo: fill in the common properties of your order and refund class.
        // @todo: If MyWebShop does not support refunds, fill in the properties of your Order class.
        $source = [
            'date_created',
            'date_modified',
            'shipping_method',
            'total',
            'subtotal',
            'used_coupons',
            'item_count',
        ];

        // @todo: complete the class and file name.
        return [
            'class' => '/MyWebShop/BaseOrder',
            'file' => '.../BaseOrder.php',
            'properties' => $source,
            'properties-more' => true,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getTokenInfoRefund(): array
    {
        // @todo: fill in the properties that are unique to your Refund class (i.e. do not appear in orders),
        // @todo: remove if MyWebShop does not support refunds.
        $refund = [
            'amount',
            'reason',
        ];

        // @todo: complete the class and file name.
        return [
            'more-info' => $this->t('refund_only'),
            'class' => '/MyWebShop/Refund',
            'file' => '.../Refund.php',
            'properties' => $refund,
            'properties-more' => true,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getTokenInfoOrder(): array
    {
        // @todo: fill in the properties that are unique to your Order class (i.e. do not appear in refunds),
        // @todo: remove if MyWebShop does not support refunds.
        $order = [
            'order_number',
            'billing_first_name',
            'billing_last_name',
            'billing_company',
            'billing_address_1',
            'billing_address_2',
            '...',
        ];

        // @todo: complete the class and file name.
        return [
            'more-info' => $this->t('original_order_for_refund'),
            'class' => '/MyWebShop/Order',
            'file' => '.../Order.php',
            'properties' => $order,
            'properties-more' => true,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getTokenInfoShopProperties(): array
    {
        // @todo: define the properties of other objects that may be used to fetch info from.
        // @todo: ensure that your Creator class calls addPropertySource() to all properties defined here.
        return parent::getTokenInfo() + [
            // @todo: complete the class and file name.
            'address_invoice' => [
                'class' => 'Address',
                'file' => 'classes/Address.php',
                'properties' => [
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
                ],
                'properties-more' => true,
            ],
            // @todo: complete the class and file name.
            'address_delivery' => [
                'more-info' => $this->t('see_billing_address'),
                'class' => 'Address',
                'file' => 'classes/Address.php',
                'properties' => [
                    $this->t('see_above'),
                ],
                'properties-more' => false,
            ],
            // @todo: complete the class and file name.
            'customer' => [
                'class' => 'Customer',
                'file' => 'classes/Customer.php',
                'properties' => [
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
                ],
                'properties-more' => true,
            ],
            // @todo: complete the class and file name.
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
                ],
                'properties-more' => true,
            ],
            ];
    }

    /**
     * {@inheritdoc}
     */
    public function getShopDefaults(): array
    {
        // @todo: fill in the appropriate property names, remove a line when no appropriate default exists.
        // @todo: ensure that all these objects are defined in the method getTokenInfoShopProperties() above.
        return [
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
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getShopOrderStatuses(): array
    {
        // @todo: adapt to MyWebShop's way of retrieving the list of order statuses.
        $statuses = OrderState::getOrderStates($this->translator->getLanguage());
        $result = [];
        foreach ($statuses as $status) {
            $result[$status['id_order_state']] = $status['name'];
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethods(): array
    {
        // @todo: adapt to MyWebShop's way of retrieving the list of (active) payment methods.
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
        // @todo: adapt to MyWebShop's way of retrieving the list of (active) tax classes.
        $result = [];
        $taxClasses = TaxRulesGroup::getTaxRulesGroups();
        foreach ($taxClasses as $taxClass) {
            $result[$taxClass['id']] = $taxClass['name'];
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getLink($linkType): string
    {
        // @todo: adapt to MyWebShop's way of creating links.
        switch ($linkType) {
            case 'config':
                return Context::getContext()->link->getAdminLink('AdminModules', true, [], ['configure' => 'acumulus']);
            case 'advanced':
                return Context::getContext()->link->getAdminLink('AdminAcumulusAdvanced', true);
            case 'batch':
                return Context::getContext()->link->getAdminLink('AdminAcumulusBatch', true);
        }
        return parent::getLink($linkType);
    }
}
