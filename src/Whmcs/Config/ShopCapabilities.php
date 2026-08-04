<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection  Most parent methods are base/no-op implementations.
 * @noinspection SpellCheckingInspection To many compound words in array keys.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Config;

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\ShopCapabilities as ShopCapabilitiesBase;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Whmcs\Helpers\LocalApiTrait;
use WHMCS\Billing\Tax;

/**
 * Defines the WooCommerce web shop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    use LocalApiTrait;

    public function getDefaultShopMappings(): array
    {
        // @todo: implement.
        return [
            DataType::Invoice => [
            ],
            DataType::Customer => [
                // Customer defaults.
                Fld::ContactYourId => '[source::getOrder()::...]',
                Fld::VatNumber => '[source::getOrder()::getShopObject()::...]',
                Fld::Telephone => '[source::getOrder()::getShopObject()::get_billing_phone()]',
                Fld::Telephone2 => '[source::getOrder()::getShopObject()::get_shipping_phone()]',
                Fld::Email => '[source::getOrder()::getShopObject()::get_billing_email()]',
            ],
            AddressType::Invoice => [
                Fld::CompanyName1 => '[source::getOrder()::getShopObject()::get_billing_company()]',
                Fld::FullName =>
                    '[source::getOrder()::getShopObject()::get_billing_first_name()+source::getOrder()::getShopObject()::get_billing_last_name()]',
                Fld::Address1 => '[source::getOrder()::getShopObject()::get_billing_address_1()]',
                Fld::Address2 => '[source::getOrder()::getShopObject()::get_billing_address_2()]',
                Fld::PostalCode => '[source::getOrder()::getShopObject()::get_billing_postcode()]',
                Fld::City => '[source::getOrder()::getShopObject()::get_billing_city()]',
                Fld::CountryCode => '[source::getOrder()::getShopObject()::get_billing_country()]',
            ],
            AddressType::Shipping => [
                Fld::CompanyName1 => '[source::getOrder()::getShopObject()::get_shipping_company()]',
                Fld::FullName =>
                    '[source::getOrder()::getShopObject()::get_shipping_first_name()+source::getOrder()::getShopObject()::get_shipping_last_name()]',
                Fld::Address1 => '[source::getOrder()::getShopObject()::get_shipping_address_1()]',
                Fld::Address2 => '[source::getOrder()::getShopObject()::get_shipping_address_2()]',
                Fld::PostalCode => '[source::getOrder()::getShopObject()::get_shipping_postcode()]',
                Fld::City => '[source::getOrder()::getShopObject()::get_shipping_city()]',
                Fld::CountryCode => '[source::getOrder()::getShopObject()::get_shipping_country()]',
            ],
            EmailAsPdfType::Invoice => [
                Fld::EmailTo => '[source::getOrder()::getShopObject()::get_billing_email()]',
            ],
            // Property sources for LineType::Item:
            // - source: Source
            // - item: Item,
            // - item::getShopObject(): WC_Order_Item_product
            // - product (or item::getProduct()): Product
            // - product::getShopObject(): ?WC_Product
            LineType::Item => [
                Fld::ItemNumber => '[product::getShopObject()::get_sku()|product::getShopObject()::get_global_unique_id()'
                    . '|"#".product::getShopObject()::get_id()]',
                Fld::Product => '[item::getShopObject()::get_name()]',
                // In refunds, the quantity will be negative and prices will be positive,
                // so no further need for us to correct with sign (unless quantity appears
                // to be 0).
                Fld::Quantity => '[item::getShopObject()::get_quantity()|source::getSign()]',
                Fld::UnitPrice => '[item::getShopObject()::unit_price_tax_excl]',
                Meta::UnitPriceInc => '[item::getShopObject()::unit_price_tax_incl]',
            ],
        ];
    }

    public function getShopOrderStatuses(): array
    {
        $result = [];
        $orderStatuses = $this->localApi()->getList('GetOrderStatuses', [], 'statuses', 'status');
        foreach ($orderStatuses as $orderStatus) {
            $result[$orderStatus['title']] = $orderStatus['title'];
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * WHMCS only supports invoices as source. Orders do not contain tax-info.
     * @todo: what about credit notes in WHMCS 9?
     */
    public function getSupportedInvoiceSourceTypes(): array
    {
        return [
            Source::Invoice,
        ];
    }

    public function getInvoiceNrSourceOptions(): array
    {
        $result = parent::getInvoiceNrSourceOptions();
        unset($result[Config::InvoiceNrSource_ShopOrder]);
        return $result;
    }

    public function getDateToUseOptions(): array
    {
        $result = parent::getDateToUseOptions();
        unset($result[Config::IssueDateSource_OrderCreate]);
        return $result;
    }

    public function getTriggerInvoiceEventOptions(): array
    {
        $result = parent::getTriggerInvoiceEventOptions();
        $result[Config::TriggerInvoiceEvent_Create] = $this->t('option_triggerInvoiceEvent_1');
        $result[Config::TriggerInvoiceEvent_Send] = $this->t('option_triggerInvoiceEvent_2');
        return $result;
    }

    public function getPaymentMethods(): array
    {
        $result = [];
        $paymentMethods = $this->localApi()->getList('GetPaymentMethods', [], 'paymentmethods');
        foreach ($paymentMethods as $paymentMethod) {
            $result[$paymentMethod['module']] = $paymentMethod['displayname'];
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * WHMCS only knows vat rates, no vat classes. These tax rate are stored in table
     * 'tbltax' which has a.o. columns 'name' and 'country'. I assume that column name is
     * used to group tax rates for different countries into something like a tax class.
     */
    public function getVatClasses(): array
    {
        $result = [];
        $taxRates = Tax::all();
        foreach ($taxRates as $tax) {
            $result[$tax->name] = $tax->name;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This WHMCS override assumes that a WHMCS installation only:
     * - Sells services and thus no (margin) products.
     * - Uses the integrated invoicing features of WHMCS self and thus no Acumulus PDF
     *   invoices, let alone packing slips, are used.
     * - As only services are sold, no stock management is done.
     * - No update messages have to be shown.
     */
    public function getDefaultShopConfig(): array
    {
        return [
            'sendEmptyShipping' => false,
            'nature_shop' => Config::Nature_Services,
            'marginProducts' => Config::MarginProducts_No,
            'showInvoiceDetail' => false,
            'mailInvoiceDetail' => false,
            'showPackingSlipDetail' => false,
            'mailPackingSlipDetail' => false,
            'showInvoiceList' => false,
            'mailInvoiceList' => false,
            'showPackingSlipList' => false,
            'mailPackingSlipList' => false,
            'emailAsPdf' => false,
            'showPluginV84Message' => PHP_INT_MAX,
            'showPluginV8Message' => PHP_INT_MAX,
        ];
    }

    public function getLink(string $linkType, mixed $parameter = null): string
    {
        $addOnName = 'acumulus';
        $rootUri = $this->localApi()->getConfig('SystemURL');
        $addOnAdminPage = "$rootUri/admin/addonmodules.php/?module=$addOnName"; // @todo: test
        $addOnFolderUri = "$rootUri/modules/addons/$addOnName";
        return match ($linkType) {
            'settings', 'mappings', 'batch', 'register', 'activate' => "$addOnAdminPage&page=$linkType",
            'fiscal-address-setting' => AddressType::Invoice,
            'logo' => "$addOnFolderUri/Acumulus-Online-Boekhouden_icon_150.png",
            'pro-support-image' => "$addOnFolderUri/pro-support-whmcs.png",
            'pro-support-link' => 'https://pay.siel.nl/?p=1qCi6ERRazteSIOHWDR4t3fpMIc2N9fuOL3bQdfxYsq7TywW',
            default => parent::getLink($linkType, $parameter),
        };
    }

    public function hasOrderList(): bool
    {
        return true;
    }

    public function getFiscalAddressSetting(): string
    {
        return AddressType::Invoice;
    }
}
