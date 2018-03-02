<?php
namespace Siel\Acumulus\OpenCart\Config;

use Siel\Acumulus\PluginConfig;
use Siel\Acumulus\Config\ShopCapabilities as ShopCapabilitiesBase;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\OpenCart\Helpers\Registry;

/**
 * Defines the OpenCart webshop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     */
    public function getShopEnvironment()
    {
        $environment = array(
            // Module has same version as library.
            'moduleVersion' => PluginConfig::Version,
            'shopName' => $this->shopName,
            'shopVersion' => VERSION,
        );
        return $environment;
    }

    /**
     * {@inheritdoc}
     */
    public function getTokenInfo()
    {
        $catalogOrder = array(
            'order_id',
            'invoice_no',
            'invoice_prefix',
            'store_id',
            'store_name',
            'store_url',
            'customer_id',
            'firstname',
            'lastname',
            'telephone',
            'fax',
            'email',
            'payment_firstname',
            'payment_lastname',
            'payment_company',
            'payment_company_id',
            'payment_tax_id',
            'payment_address_1',
            'payment_address_2',
            'payment_postcode',
            'payment_city',
            'payment_zone_id',
            'payment_zone',
            'payment_zone_code',
            'payment_country_id',
            'payment_country',
            'payment_iso_code_2',
            'payment_iso_code_3',
            'payment_address_format',
            'payment_method',
            'payment_code',
            'shipping_firstname',
            'shipping_lastname',
            'shipping_company',
            'shipping_address_1',
            'shipping_address_2',
            'shipping_postcode',
            'shipping_city',
            'shipping_zone_id',
            'shipping_zone',
            'shipping_zone_code',
            'shipping_country_id',
            'shipping_country',
            'shipping_iso_code_2',
            'shipping_iso_code_3',
            'shipping_address_format',
            'shipping_method',
            'shipping_code',
            'comment',
            'total',
            'order_status_id',
            'order_status',
            'language_id',
            'language_code',
            'language_filename',
            'language_directory',
            'currency_id',
            'currency_code',
            'currency_value',
            'ip',
            'forwarded_ip',
            'user_agent',
            'accept_language',
            'date_modified',
            'date_added',
        );
        $adminOrder = array(
            'amazon_order_id',
            'order_id',
            'invoice_no',
            'invoice_prefix',
            'store_id',
            'store_name',
            'store_url',
            'customer_id',
            'customer',
            'customer_group_id',
            'firstname',
            'lastname',
            'telephone',
            'fax',
            'email',
            'payment_firstname',
            'payment_lastname',
            'payment_company',
            'payment_company_id',
            'payment_tax_id',
            'payment_address_1',
            'payment_address_2',
            'payment_postcode',
            'payment_city',
            'payment_zone_id',
            'payment_zone',
            'payment_zone_code',
            'payment_country_id',
            'payment_country',
            'payment_iso_code_2',
            'payment_iso_code_3',
            'payment_address_format',
            'payment_method',
            'payment_code',
            'shipping_firstname',
            'shipping_lastname',
            'shipping_company',
            'shipping_address_1',
            'shipping_address_2',
            'shipping_postcode',
            'shipping_city',
            'shipping_zone_id',
            'shipping_zone',
            'shipping_zone_code',
            'shipping_country_id',
            'shipping_country',
            'shipping_iso_code_2',
            'shipping_iso_code_3',
            'shipping_address_format',
            'shipping_method',
            'shipping_code',
            'comment',
            'total',
            'reward',
            'order_status_id',
            'affiliate_id',
            'affiliate_firstname',
            'affiliate_lastname',
            'commission',
            'language_id',
            'language_code',
            'language_filename',
            'language_directory',
            'currency_id',
            'currency_code',
            'currency_value',
            'ip',
            'forwarded_ip',
            'user_agent',
            'accept_language',
            'date_added',
            'date_modified',
        );
        return parent::getTokenInfo() + array(
            'source' => array(
                'file' => 'catalog/model/checkout/order.php',
                'properties' => array_intersect($catalogOrder, $adminOrder),
                'properties-more' => true,
            ),
            'item' => array(
                'table' => 'order_product',
                'properties' => array(
                    'order_product_id',
                    'product_id',
                    'name',
                    'model',
                    'quantity',
                    'price',
                    'total',
                    'tax',
                    'reward',
                ),
            ),
            'product' => array(
                'table' => array('product', 'product_description', 'url_alias'),
                'properties' => array(
                    'product_id',
                    'model',
                    'sku',
                    'upc',
                    'ean',
                    'jan',
                    'isbn',
                    'mpn',
                    'location',
                    'quantity',
                    'stock_status_id',
                    'manufacturer_id',
                    'shipping',
                    'price',
                    'points',
                    'tax_class_id',
                    'date_available',
                    'weight',
                    'weight_class_id',
                    'length',
                    'width',
                    'height',
                    'length_class_id',
                    'subtract',
                    'minimum',
                    'status',
                    'viewed',
                    'language_id',
                    'name',
                    'description',
                    'tag',
                    'meta_title',
                    'meta_description',
                    'meta_keyword',
                    'description',
                ),
            ),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getShopDefaults()
    {
        return array(
            'contactYourId' => '[customer_id]', // Order
            'companyName1' => '[payment_company]', // Order
            'fullName' => '[firstname+lastname]', // Order
            'address1' => '[payment_address_1]', // Order
            'address2' => '[payment_address_2]', // Order
            'postalCode' => '[payment_postcode]', // Order
            'city' => '[payment_city]', // Order
            'vatNumber' => '[payment_tax_id]', // Order
            'telephone' => '[telephone]', // Order
            'fax' => '[fax]', // Order
            'email' => '[email]', // Order

            // Invoice lines defaults.
            'itemNumber' => '[sku|upc|ean|jan|isbn|mpn]',
            'productName' => '[name+"("&model&")"]',
        );
    }

    /**
     * {@inheritdoc}
     *
     * This default implementation returns order and credit note. Override if
     * the specific shop supports other types or does not support credit notes.
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
        Registry::getInstance()->load->model('localisation/order_status');
        $states = Registry::getInstance()->model_localisation_order_status->getOrderStatuses();
        $result = array();
        foreach ($states as $state) {
            list($optionValue, $optionText) = array_values($state);
            $result[$optionValue] = $optionText;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override removes the 'Use shop invoice date' option as OpenCart
     * does not store the creation date of the invoice.
     */
    public function getDateToUseOptions()
    {
        $result = parent::getDateToUseOptions();
        unset($result[PluginConfig::InvoiceDate_InvoiceCreate]);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethods()
    {
        $prefix = Registry::getInstance()->isOc3() ? 'payment_' : '';
        $enabled = array();
        $extensions = Registry::getInstance()->getExtensionModel()->getInstalled('payment');
        foreach ($extensions as $extension) {
            if ((bool) Registry::getInstance()->config->get($prefix . $extension . '_status')) {
                $enabled[] = $extension;
            }
        }
        return $this->paymentMethodToOptions($enabled);
    }

    /**
     * Turns the list into a translated list of options for a select.
     *
     * @param array $extensions
     *
     * @return array
     *   an array with the extensions as key and their translated name as value.
     */
    protected function paymentMethodToOptions(array $extensions)
    {
        $results = array();
        $directory = !Registry::getInstance()->isOc1() ? 'extension/payment/' : 'payment/';
        foreach ($extensions as $extension) {
            Registry::getInstance()->language->load($directory . $extension);
            $results[$extension] = Registry::getInstance()->language->get('heading_title');
        }
        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function getLink($formType)
    {
        $registry = Registry::getInstance();
        switch ($formType) {
            case 'config':
                return $registry->getLink($registry->getLocation());
            case 'advanced':
                return $registry->getLink($registry->getLocation() . '/advanced');
            case 'batch':
                return $registry->getLink($registry->getLocation() . '/batch');
        }
        return parent::getLink($formType);
    }
}
