<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection OC3 has many double class definitions
 * @noinspection PhpUndefinedClassInspection Mix of OC4 and OC3 classes
 * @noinspection PhpUndefinedNamespaceInspection Mix of OC4 and OC3 classes
 */

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Config;

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\ShopCapabilities as ShopCapabilitiesBase;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;
use Siel\Acumulus\OpenCart\Helpers\Registry;

/**
 * Defines the OpenCart web shop specific capabilities.
 */
abstract class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * Wrapper around Registry::getInstance().
     */
    protected function getRegistry(): Registry
    {
        return Registry::getInstance();
    }

    protected function getTokenInfoSource(): array
    {
        $catalogOrder = [
            'order_id',
            'transaction_id',
            'invoice_no',
            'invoice_prefix',
            'store_id',
            'store_name',
            'store_url',
            'customer_id',
            'customer_group_id',
            'firstname',
            'lastname',
            'email',
            'telephone',
            'custom_field',
            'payment_firstname',
            'payment_lastname',
            'payment_company',
            'payment_address_1',
            'payment_address_2',
            'payment_postcode',
            'payment_city',
            'payment_zone_id',
            'payment_zone',
            'payment_country_id',
            'payment_country',
            'payment_iso_code_2',
            'payment_iso_code_3',
            'payment_address_format',
            'payment_custom_field',
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
            'shipping_custom_field',
            'comment',
            'total',
            'order_status_id',
            'order_status',
            'affiliate_id',
            'commission',
            'marketing_id',
            'tracking',
            'language_id',
            'language_code',
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
        $adminOrder = [
            'order_id',
            'invoice_no',
            'invoice_prefix',
            'store_id',
            'store_name',
            'store_url',
            'customer_id',
            'customer (object)',
            'customer_group_id (OC4?)',
            'firstname',
            'lastname',
            'email',
            'telephone',
            'custom_field',
            'payment_firstname',
            'payment_lastname',
            'payment_company',
            'payment_company_id',
            'payment_tax_id (OC3?)',
            'payment_address_1',
            'payment_address_2',
            'payment_postcode',
            'payment_city',
            'payment_zone_id',
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
            'order_status',
            'affiliate_id',
            'affiliate (object)',
            'language_id',
            'language_code',
            'language_filename (OC3?)',
            'language_directory (OC3?)',
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
                    'variant',
                    'override',
                    'quantity',
                    'stock_status_id',
                    'image',
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
                    'date_added',
                    'date_modified',
                    'langauge_id',
                    'name',
                    'description',
                    'tag',
                    'meta_title',
                    'meta_description',
                    'meta_keyword',
                ],
            ],
        ];
    }

    public function getDefaultShopMappings(): array
    {
        // OpenCart: both addresses are always filled.
        return [
            DataType::Invoice => [
                Meta::VatBreakdown => '[source::getVatBreakdown()]',
                Meta::PricesIncludeVat => false,
            ],
            DataType::Customer => [
                // Customer defaults.
                Fld::ContactYourId => '[source::getShopObject()::customer_id]', // Order
                //Fld::VatNumber => '', // OpenCart core does not provide a vat number field.
                // @todo: we had this as default (in OC3): 'vatNumber' => '[payment_tax_id]', // Order
                //   where did this come from?
                Fld::Telephone => '[source::getShopObject()::telephone]', // Order
                Fld::Fax => '[source::getShopObject()::fax]', // Order in OC3
                Fld::Email => '[source::getShopObject()::email]', // Order
            ],
            AddressType::Invoice => [ // address_invoice instanceof Address, comes from Order object
                Fld::CompanyName1 => '[source::getShopObject()::payment_company]',
                Fld::FullName => '[source::getShopObject()::payment_firstname+source::getShopObject()::payment_lastname|source::getShopObject()::firstname+source::getShopObject()::lastname]',
                Fld::Address1 => '[source::getShopObject()::payment_address_1]',
                Fld::Address2 => '[source::getShopObject()::payment_address_2]',
                Fld::PostalCode => '[source::getShopObject()::payment_postcode]',
                Fld::City => '[source::getShopObject()::payment_city]',
                Meta::ShopCountryId => '[source::getShopObject()::payment_country_id]',
                Meta::ShopCountryName => '[source::getShopObject()::payment_country]',
            ],
            AddressType::Shipping => [ // address_shipping instanceof Address, comes from Order object
                Fld::CompanyName1 => '[source::getShopObject()::shipping_company]',
                Fld::FullName => '[source::getShopObject()::shipping_firstname+source::getShopObject()::shipping_lastname|source::getShopObject()::firstname+source::getShopObject()::lastname]',
                Fld::Address1 => '[source::getShopObject()::shipping_address_1]',
                Fld::Address2 => '[source::getShopObject()::shipping_address_2]',
                Fld::PostalCode => '[source::getShopObject()::shipping_postcode]',
                Fld::City => '[source::getShopObject()::shipping_city]',
                Meta::ShopCountryId => '[source::getShopObject()::shipping_country_id]',
                Meta::ShopCountryName => '[source::getShopObject()::shipping_country]',
            ],
            EmailAsPdfType::Invoice => [
                Fld::EmailTo => '[source::getShopObject()::email]',
            ],
            LineType::Item => [ // item: table order_product; product: table product
                Fld::ItemNumber => '[product::sku|product::upc|product::ean|product::jan|product::isbn|product::mpn]',
                Fld::Product => '[item::getShopObject()::name+"("&item::getShopObject()::model&")"]',
                Fld::Quantity => '[item::getShopObject()::quantity]',
            ],
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

    public function getShopOrderStatuses(): array
    {
        /** @var \Opencart\Admin\Model\Localisation\OrderStatus|\ModelLocalisationOrderStatus $model */
        $model = $this->getRegistry()->getModel('localisation/order_status');
        $statuses = $model->getOrderStatuses();
        $result = [];
        foreach ($statuses as $status) {
            $optionValue = $status['order_status_id'];
            $optionText = $status['name'];
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
        unset($result[Config::IssueDateSource_InvoiceCreate]);
        return $result;
    }

    public function getVatClasses(): array
    {
        $result = [];
        /** @var \Opencart\Admin\Model\Localisation\TaxClass|\ModelLocalisationTaxClass $model */
        $model = $this->getRegistry()->getModel('localisation/tax_class');
        $taxClasses = $model->getTaxClasses();
        foreach ($taxClasses as $taxClass) {
            $result[$taxClass['tax_class_id']] = $taxClass['title'];
        }
        return $result;
    }

    /**
     * Turns the list into a translated list of select options.
     *
     * @param array[] $extensions
     *   A list with the enabled payment extensions.
     *
     * @return array
     *   An array with the extension code as key and their translated name as
     *   value.
     */
    abstract protected function paymentMethodToOptions(array $extensions): array;

    public function getLink(string $linkType, mixed $parameter = null): string
    {
        $registry = $this->getRegistry();
        return match ($linkType) {
            'settings', 'register', 'activate', 'mappings', 'batch', 'invoice' => $registry->getRouteUrl($linkType),
            'fiscal-address-setting' => $registry->getRouteUrl('tax_class', 'localisation', ''),
            'logo' => $registry->getFileUrl('view/image/acumulus/siel-logo.png'),
            'pro-support-image' => $registry->getFileUrl('view/image/acumulus/pro-support-opencart.png'),
            'pro-support-link' => 'https://pay.siel.nl/?p=0nKmWpoNV0wtqeac43dqc5YUAcaHFJkldwy1alKD1G3EJHmC',
            default => parent::getLink($linkType, $parameter),
        };
    }

    /**
     * {@inheritDoc}
     *
     * In OpenCart this is not a setting but a property per tax rate per tax class. This
     * property can have one of the following values: 'shipping', 'payment', or 'store'.
     *
     * @todo: So, this cannot for all possible situations unambiguously be mapped to the
     *   way Acumulus handles this. so, we may have to dynamically determine this when
     *   collecting an invoice.
     */
    public function getFiscalAddressSetting(): string
    {
        return AddressType::Store;
    }
}
