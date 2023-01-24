<?php
/**
 * @noinspection DuplicatedCode  Remove when extracting code common for OC3 and OC4
 */

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart4\Config;

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\ShopCapabilities as ShopCapabilitiesBase;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\OpenCart\OpenCart4\Helpers\Registry;

/**
 * Defines the OpenCart web shop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     */
    protected function getTokenInfoSource(): array
    {
        $catalogOrder = [
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
        ];
        $adminOrder = [
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
        ];
        $source = array_intersect($catalogOrder, $adminOrder);

        return [
            'file' => 'catalog/model/checkout/order.php',
            'properties' => $source,
            'properties-more' => true,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenInfoShopProperties(): array
    {
        return [
            'item' => [
                'table' => 'order_product',
                'properties' => [
                    'order_product_id',
                    'product_id',
                    'name',
                    'model',
                    'quantity',
                    'price',
                    'total',
                    'tax',
                    'reward',
                ],
            ],
            'product' => [
                'table' => ['product', 'product_description', 'url_alias'],
                'properties' => [
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
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getShopDefaults(): array
    {
        return [
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
        ];
    }

    /**
     * {@inheritdoc}
     *
     * This override unsets CreditNote as it does not support credit notes.
     */
    public function getSupportedInvoiceSourceTypes(): array
    {
        $result = parent::getSupportedInvoiceSourceTypes();
        unset($result[Source::CreditNote]);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getShopOrderStatuses(): array
    {
        /** @var \Opencart\Admin\Model\Localisation\OrderStatus $model */
        $model = $this->getRegistry()->getModel('localisation/order_status');
        $statuses = $model->getOrderStatuses();
        $result = [];
        foreach ($statuses as $status) {
            [$optionValue, $optionText] = array_values($status);
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
    public function getDateToUseOptions(): array
    {
        $result = parent::getDateToUseOptions();
        unset($result[Config::InvoiceDate_InvoiceCreate]);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethods(): array
    {
        $registry = $this->getRegistry();
        $prefix = 'payment_';
        $enabled = [];
        /** @var \Opencart\Admin\Model\Setting\Extension $model */
        $model = $registry->getModel('setting/extension');
        $extensions = $model->getExtensionsByType('payment');
        foreach ($extensions as $extension) {
            $code = $extension['code'];
            if ($registry->config->get($prefix . $code . '_status')) {
                $enabled[] = $extension;
            }
        }
        return $this->paymentMethodToOptions($enabled);
    }

    /**
     * Turns the list into a translated list of select options.
     *
     * @param array[] $extensions
     *   A list with information about the enabled payment extensions. Keys:
     *   extension-id, extension, type (= payment), code.
     *
     * @return array
     *   An array with the extensions as key and their translated name as value.
     */
    protected function paymentMethodToOptions(array $extensions): array
    {
        $results = [];
        $registry = $this->getRegistry();
        foreach ($extensions as $extension) {
            $directory = "extension/{$extension['extension']}/payment/";
            $registry->language->load($directory . $extension['code']);
            $results[$extension['code']] = $registry->language->get('heading_title');
        }
        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function getVatClasses(): array
    {
        $result = [];
        /** @var \Opencart\Admin\Model\Localisation\TaxClass $model */
        $model = $this->getRegistry()->getModel('localisation/tax_class');
        $taxClasses = $model->getTaxClasses();
        foreach ($taxClasses as $taxClass) {
            $result[$taxClass['tax_class_id']] = $taxClass['title'];
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getLink(string $linkType): string
    {
        $registry = $this->getRegistry();
        switch ($linkType) {
            case 'config':
                return $registry->getExtensionPageUrl('');
            case 'register':
            case 'activate':
            case 'advanced':
            case 'batch':
            case 'invoice':
                return $registry->getExtensionPageUrl($linkType);
            case 'logo':
                return $registry->getExtensionFileUrl('view/image/siel-logo.png');
            case 'pro-support-image':
                return $registry->getExtensionFileUrl('view/image/pro-support-opencart.png');
            case 'pro-support-link':
                return 'https://pay.siel.nl/?p=0nKmWpoNV0wtqeac43dqc5YUAcaHFJkldwy1alKD1G3EJHmC';
        }
        return parent::getLink($linkType);
    }

    /**
     * Wrapper around Registry::getInstance().
     */
    protected function getRegistry(): Registry
    {
        return Registry::getInstance();
    }
}
