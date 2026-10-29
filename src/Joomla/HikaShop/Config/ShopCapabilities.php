<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection  Most parent methods are base/no-op implementations.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\HikaShop\Config;

use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Joomla\Config\ShopCapabilities as ShopCapabilitiesBase;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Meta;

/**
 * Defines the HikaShop web shop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{

    public function getDefaultShopMappings(): array
    {
        return [
            DataType::Invoice => [
                Meta::VatBreakdown => '[source::getVatBreakdown()]',
                // In HikaShop you can enter a price with or without vat, the other being
                // automatically updated. So we can not know how prices where entered.
                Meta::ProductPricesIncludeVat => null,
            ],
            DataType::Customer => [
                // Customer defaults.
                Fld::ContactYourId => '[source::getShopObject()::order_user_id]',
                Fld::VatNumber =>
                    '[source::getShopObject()::billing_address::address_vat|source::getShopObject()::shipping_address::address_vat]',
                Fld::Telephone =>
                    '[source::getShopObject()::billing_address::address_telephone' .
                    '|source::getShopObject()::shipping_address::address_telephone]',
                Fld::Telephone2 =>
                    '[source::getShopObject()::billing_address::address_telephone2' .
                    '|source::getShopObject()::shipping_address::address_telephone2' .
                    '|source::getShopObject()::shipping_address::address_telephone]',
                Fld::Fax =>
                    '[source::getShopObject()::billing_address::address_fax|source::getShopObject()::shipping_address::address_fax]',
                Fld::Email => '[source::getShopObject()::customer::user_email|source::getShopObject()::customer::email]',
            ],
            AddressType::Invoice => [
                Fld::CompanyName1 => '[source::getShopObject()::billing_address::address_company]',
                Fld::FullName =>
                    '[source::getShopObject()::billing_address::address_firstname' .
                    '+source::getShopObject()::billing_address::address_middle_name' .
                    '+source::getShopObject()::billing_address::address_lastname' .
                    '|source::getShopObject()::customer::name]',
                Fld::Address1 => '[source::getShopObject()::billing_address::address_street1]',
                Fld::Address2 => '[source::getShopObject()::billing_address::address_street2]',
                Fld::PostalCode => '[source::getShopObject()::billing_address::address_postcode]',
                Fld::City => '[source::getShopObject()::billing_address::address_city]',
                Fld::CountryCode => '[source::getShopObject()::billing_address::address_country_code_2]',
                Meta::ShopCountryName => '[source::getShopObject()::billing_address::address_country_name]',
            ],
            AddressType::Shipping => [
                Fld::CompanyName1 => '[source::getShopObject()::shipping_address::address_company]',
                Fld::FullName =>
                    '[source::getShopObject()::shipping_address::address_firstname' .
                    '+source::getShopObject()::shipping_address::address_middle_name' .
                    '+source::getShopObject()::shipping_address::address_lastname' .
                    '|source::getShopObject()::customer::name]',
                Fld::Address1 => '[source::getShopObject()::shipping_address::address_street1]',
                Fld::Address2 => '[source::getShopObject()::shipping_address::address_street2]',
                Fld::PostalCode => '[source::getShopObject()::shipping_address::address_postcode]',
                Fld::City => '[source::getShopObject()::shipping_address::address_city]',
                Fld::CountryCode => '[source::getShopObject()::shipping_address::address_country_code_2]',
                Meta::ShopCountryName => '[source::getShopObject()::shipping_address::address_country_name_english]',
            ],
            EmailAsPdfType::Invoice => [
                Fld::EmailTo => '[source::getShopObject()::customer::user_email|source::getShopObject()::customer::email]',
            ],
            LineType::Item => [
                Fld::ItemNumber => '[item::getShopObject()::order_product_code]',
                Fld::Product => '[item::getShopObject()::order_product_name]',
                Fld::Quantity => '[item::getShopObject()::order_product_quantity]',
                Meta::ProductId => '[item::getShopObject()::product_id]',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     *
     * HikaShop does not know refunds.
     */
    public function getSupportedInvoiceSourceTypes(): array
    {
        $result = parent::getSupportedInvoiceSourceTypes();
        unset($result[Source::CreditNote]);
        return $result;
    }

    public function getShopOrderStatuses(): array
    {
        /** @var \hikashopCategoryClass $class */
        $class = hikashop_get('class.category');
        $statuses = $class->loadAllWithTrans('status');

        $orderStatuses = [];
        foreach ($statuses as $status) {
            $orderStatuses[$status->category_name] = $status->translation;
        }
        return $orderStatuses;
    }

    /**
     * {@inheritdoc}
     *
     * This override removes the 'Use shop invoice number' option as HikaShop
     * does not have invoices.
     */
    public function getDateToUseOptions(): array
    {
        $result = parent::getDateToUseOptions();
        unset($result[Config::IssueDateSource_InvoiceCreate]);
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * The order_product table stores the category_namekey, while the shipping
     * and payment tables store the category_id. So which one we sue is a bit
     * arbitrary, but we use the category_namekey as id.
     */
    public function getVatClasses(): array
    {
        $result = [];
        /** @var \hikashopCategoryClass $categoryClass */
        $categoryClass = hikashop_get('class.category');
        /** @var \stdClass $category */
        $category = $categoryClass->get('tax');
        $taxClasses = $categoryClass->getChildren((int) $category->category_id, true, [], '', 0, 0);
        foreach ($taxClasses as $taxClass) {
            /** @var \stdClass $category */
            $result[$taxClass->category_namekey] = $taxClass->category_name;
        }
        return $result;
    }

    public function getPaymentMethods(): array
    {
        $result = [];
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

    public function getLink(string $linkType, mixed $parameter = null): string
    {
        return match ($linkType) {
            'fiscal-address-setting' => Route::_('index.php?option=com_hikashop&ctrl=config#main_tax'),
            'pro-support-image' => Uri::root(true) . '/administrator/components/com_acumulus/media/pro-support-hikashop.png',
            'pro-support-link' => 'https://pay.siel.nl/?p=b5TeLbPw6BtNXRioORwnUtNbpU3yhUAgXLuuEMgk5zcttHbU',
            default => parent::getLink($linkType, $parameter),
        };
    }

    public function getFiscalAddressSetting(): string
    {
        return 'tax_zone_type';
    }
}
