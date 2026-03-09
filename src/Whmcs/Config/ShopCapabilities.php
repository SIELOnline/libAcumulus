<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection  Most parent methods are base/no-op implementations.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Config;

use Siel\Acumulus\Config\ShopCapabilities as ShopCapabilitiesBase;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Meta;
use WHMCS\Billing\Tax;

/**
 * Defines the WooCommerce web shop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{

    public function getDefaultShopMappings(): array
    {
        return [
            DataType::Invoice => [
            ],
            DataType::Customer => [
                // Customer defaults.
                // @todo
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
                Fld::ItemNumber => '[product::getShopObject()::get_sku()|product::getShopObject()::get_global_unique_id()|"#".product::getShopObject()::get_id()]',
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
        $orderStatuses = localAPI('GetOrderStatuses', []);
        foreach ($orderStatuses['statuses']['status'] as $status) {
            $result[$status['title']] = $status['title'];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     * @todo: check this, I guess that WHMCS DOES have invoices.
     * This override removes the 'Use invoice #' option as WHMCS does not have separate
     * invoices.
     */
    public function getInvoiceNrSourceOptions(): array
    {
        $result = parent::getInvoiceNrSourceOptions();
        unset($result[Config::InvoiceNrSource_ShopInvoice]);

        return $result;
    }

    /**
     * {@inheritdoc}
     * @todo: check this, I guess that WHMCS DOES have invoices.
     * This override removes the 'Use invoice date' option as WHMCS does not have separate
     * invoices.
     */
    public function getDateToUseOptions(): array
    {
        $result = parent::getDateToUseOptions();
        unset($result[Config::IssueDateSource_InvoiceCreate]);

        return $result;
    }

    public function getPaymentMethods(): array
    {
        $result = [];
        $orderStatuses = localAPI('GetPaymentMethods', []);
        foreach ($orderStatuses['paymentmethods']['paymentmethod'] as $paymentMethod) {
            $result[$paymentMethod['module']] = $paymentMethod['displayname'];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     * WHMCS only knows vat rates, no vat classes. These tax rate are stored in table
     * 'tbltax' which has a.o. columns 'name' and 'country'. I assume that column name is
     * used to group tax rates for different countries into something like a tax class.
     *
     * @todo: confirm how various tax rates for various products can be used.
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
        // @todo: Check this.
        global $CONFIG;
        $rootUri = $CONFIG['SystemURL']; // @todo
        $addOnName = 'acumulus';
        $addOnAdminPage = "$rootUri/addOns/$addOnName"; // @todo
        $addOnFolderUri = "$rootUri/modules/addons/$addOnName";
        return match ($linkType) {
            'settings', 'mappings', 'batch', 'register', 'activate' => "$addOnAdminPage?page=$linkType", // @todo
            'fiscal-address-setting' => "$rootUri/", // @todo
            'logo' => "$addOnFolderUri/siel-logo.svg",
            'pro-support-image' => "$addOnFolderUri/pro-support-whmcs.png",
            'pro-support-link' => 'https://pay.siel.nl/?p=1qCi6ERRazteSIOHWDR4t3fpMIc2N9fuOL3bQdfxYsq7TywW',
            default => parent::getLink($linkType, $parameter),
        };
    }

    public function hasOrderList(): bool
    {
        // @todo: what are the consequences?
        return true;
    }

    public function getFiscalAddressSetting(): string
    {
        // @todo: does WHMCS has a (separate) shipping address?
        return '';
    }
}
