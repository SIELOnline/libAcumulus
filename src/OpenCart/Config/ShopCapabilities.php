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
                // @todo: fields that come from the Order, because, if it comes from
                //   Source, it is not shop specific and defined in
                //   Mappings::getShopIndependentDefaults().
            ],
            DataType::Customer => [
                // Customer defaults.
                Fld::ContactYourId => '[source::getSource()::customer_id]', // Order
                //Fld::VatNumber => '', // OpenCart core does not provide a vat number field.
                Fld::Telephone => '[source::getSource()::telephone|source::getSource()::fax]', // Order (fax = OC3)
                Fld::Fax => '[source::getSource()::fax]', // Order in OC3
                Fld::Email => '[source::getSource()::email]', // Order
            ],
            AddressType::Invoice => [ // address_invoice instanceof Address, comes from Order object
                Fld::CompanyName1 => '[source::getSource()::payment_company]',
                Fld::FullName => '[source::getSource()::payment_firstname+source::getSource()::payment_lastname|source::getSource()::firstname+source::getSource()::lastname]',
                Fld::Address1 => '[source::getSource()::payment_address1]',
                Fld::Address2 => '[source::getSource()::payment_address2]',
                Fld::PostalCode => '[source::getSource()::payment_postcode]',
                Fld::City => '[source::getSource()::payment_city]',
                Meta::ShopCountryId => '[source::getSource()::payment_country_id]',
                Meta::ShopCountryName => '[source::getSource()::payment_country]',
            ],
            AddressType::Shipping => [ // address_shipping instanceof Address, comes from Order object
                Fld::CompanyName1 => '[source::getSource()::shipping_company]',
                Fld::FullName => '[source::getSource()::shipping_firstname+source::getSource()::shipping_lastname|source::getSource()::firstname+source::getSource()::lastname]',
                Fld::Address1 => '[source::getSource()::shipping_address1]',
                Fld::Address2 => '[source::getSource()::shipping_address2]',
                Fld::PostalCode => '[source::getSource()::shipping_postcode]',
                Fld::City => '[source::getSource()::shipping_city]',
                Meta::ShopCountryId => '[source::getSource()::shipping_country_id]',
                Meta::ShopCountryName => '[source::getSource()::shipping_country]',
            ],
            EmailAsPdfType::Invoice => [
                Fld::EmailTo => '[source::getSource()::email]',
            ],
            LineType::Item => [ // item: table order_product; product: table product
                Meta::Id => '[item::order_product_id]',
                Fld::ItemNumber => '[product::sku|product::upc|product::ean|product::jan|product::isbn|product::mpn]',
                Fld::Product => '[item::name+"("&item::model&")"]',
                Meta::ProductId => '[product::product_id]',
                Fld::Quantity => '[item::quantity]',
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

    public function getLink(string $linkType): string
    {
        $registry = $this->getRegistry();
        switch ($linkType) {
            case 'settings':
            case 'config':
            case 'register':
            case 'activate':
            case 'mappings':
            case 'advanced':
            case 'batch':
            case 'invoice':
                return $registry->getRouteUrl($linkType);
            case 'fiscalAddressSetting':
                return $registry->getRouteUrl('tax_class', 'localisation', '');
            case 'logo':
                return $registry->getFileUrl('view/image/acumulus/siel-logo.png');
            case 'pro-support-image':
                return $registry->getFileUrl('view/image/acumulus/pro-support-opencart.png');
            case 'pro-support-link':
                return 'https://pay.siel.nl/?p=0nKmWpoNV0wtqeac43dqc5YUAcaHFJkldwy1alKD1G3EJHmC';
        }
        return parent::getLink($linkType);
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

    /**
     * OpenCart switched to the new creation process!
     *
     * Note: in case of severe errors during the creation process: return false to revert
     * to the old "tried and tested" code.
     */
    public function usesNewCode(): bool
    {
        //return false; // Emergency revert: remove the // at the beginning of this line!
        return true;
    }
}
