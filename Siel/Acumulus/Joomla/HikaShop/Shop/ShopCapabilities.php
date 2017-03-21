<?php
namespace Siel\Acumulus\Joomla\HikaShop\Shop;

use Siel\Acumulus\Invoice\ConfigInterface as InvoiceConfigInterface;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Joomla\Shop\ShopCapabilities as ShopCapabilitiesBase;

/**
 * Defines the HikaShop webshop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     */
    public function getTokenInfo() {
        return parent::getTokenInfo() + array(
            'source' => array(
                'table' => array('hikashop_order'),
                'properties' => array(
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
                ),
                'properties-more' => true,
            ),
            'billing_address' => array(
                'table' => 'hikashop_address',
                'properties' => array(
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
                ),
                'properties-more' => false,
            ),
            'shipping_address' => array(
                'table' => 'hikashop_address',
                'additional-info' => $this->t('see_billing_address'),
                'properties' => array(
                    $this->t('see_above'),
                ),
                'properties-more' => false,
            ),
            'customer' => array(
                'table' => 'hikashop_customer',
                'properties' => array(
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
                ),
                'properties-more' => false,
            ),
            'item' => array(
                'table' => 'hikashop_order_product',
                'additional-info' => $this->t('invoice_lines_only'),
                'properties' => array(
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
            'contactYourId' => '[order_user_id]', // order
            'companyName1' => '[address_company]', // billing_address
            // @todo: hoe kan een klant dit (en vat#) invullen?
            'fullName' => '[address_firstname+address_middle_name+address_lastname|name]', // billing_address, customer
            'address1' => '[address_street]', // billing_address
            'address2' => '[address_Street2]', // billing_address
            'postalCode' => '[address_post_code]', // billing_address
            'city' => '[address_city]', // billing_address
            'vatNumber' => '[address_vat]', // billing_address
            'telephone' => '[address_telephone|address_telephone2]', // billing_address
            'fax' => '[address_telephone2|address_fax]', // billing_address
            'email' => '[user_email|email]', // customer

            // Invoice lines defaults.
            'itemNumber' => '[order_product_code]',
            'productName' => '[order_product_name]',
        );
    }

    /**
     * {@inheritdoc}
     *
     * HikaShop does not know refunds.
     */
    public function getSupportedInvoiceSourceTypes()
    {
        $result = parent::getSupportedInvoiceSourceTypes();
        unset($result[Source::CreditNote]);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getShopOrderStatuses()
    {
        /** @var \hikashopCategoryClass $class */
        $class = hikashop_get('class.category');
        $statuses = $class->loadAllWithTrans('status');

        $orderStatuses = array();
        foreach ($statuses as $state) {
            $orderStatuses[$state->category_name] = $state->translation;
        }
        return $orderStatuses;
    }

    /**
     * {@inheritdoc}
     *
     * This override removes the 'Use shop invoice number' option as HikaShop
     * does not have invoices.
     */
    public function getDateToUseOptions()
    {
        $result = parent::getDateToUseOptions();
        unset($result[InvoiceConfigInterface::InvoiceDate_InvoiceCreate]);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethods()
    {
        $result = array();
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
}
