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
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Whmcs\Helpers\LocalApiTrait;
use WHMCS\Database\Capsule;

/**
 * Defines the WooCommerce web shop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    use LocalApiTrait;

    public function getDefaultShopMappings(): array
    {
        return [
            DataType::Invoice => [
            ],
            DataType::Customer => [
                // Customer defaults.
                Fld::ContactYourId => '[source::getClient()::getShopObject()::id]',
                Fld::VatNumber => '[source::getClient()::getShopObject()::tax_id]',
                Fld::Telephone => '[source::getClient()::getShopObject()::phonenumber]',
                Fld::Email => '[source::getClient()::getShopObject()::email]',
            ],
            AddressType::Invoice => [
                Fld::CompanyName1 => '[source::getClient()::companyname]',
                Fld::FullName => '[source::getClient()::getShopObject()::fullname]',
                Fld::Address1 => '[source::getClient()::getShopObject()::address1]',
                Fld::Address2 => '[source::getClient()::getShopObject()::address2]',
                Fld::PostalCode => '[source::getClient()::getShopObject()::postcode]',
                Fld::City => '[source::getClient()::getShopObject()::city]',
                Fld::CountryCode => '[source::getClient()::getShopObject()::country]',
                Meta::ShopCountryName => '[source::getClient()::getShopObject()::countryName]',
            ],
            AddressType::Shipping => [
                Fld::CountryCode => '',
            ],
            EmailAsPdfType::Invoice => [
                Fld::EmailTo => '[source::getClient()::getShopObject()::email]',
            ],
            // Property sources for LineType::Item:
            // - source: Source
            // - item: Item,
            // - item::getShopObject(): WC_Order_Item_product
            // - product (or item::getProduct()): Product
            // - product::getShopObject(): ?WC_Product
            LineType::Item => [
                Fld::Product => '[item::getShopObject()::description]',
                Fld::Quantity => '[source::getSign()]', // WHMCS has always quantity 1 (or -1 on return: @todo check the latter)
                Fld::VatRate => '[source::getShopObject()::taxrate]',
                Meta::VatRateSource => VatRateSource::Exact,
                Meta::UnitPriceInc => '[item::getShopObject()::amount]',
                Meta::Taxed => '[item::getShopObject()::taxed]',
            ],
        ];
    }

    /**
     * @todo: WHMCS knows the following invoice statuses (see Payments - Invoices submenu)
     *   - Paid
     *   - Draft
     *   - Unpaid
     *   - Overdue
     *   - Cancelled
     *   - Refunded
     *   - Collections (???)
     *   - Payment_Pending
     *   Should/can we do something with that? Or should we only look at the available
     *   hooks (as there does not seem to be a general "invoice status change" hook anyway)
     */
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
     *
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
        Capsule::table('tbltax')
            ->select('name')
            ->distinct()
            ->get()
            ->each(function ($tax) use (&$result) {
                $result[$tax->name] = $tax->name;
            });
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
            // Shop
            'nature_shop' => Config::Nature_Services,
            'marginProducts' => Config::MarginProducts_No,
            'vatFreeClass' => Config::VatClass_Null, // hidden
            'zeroVatClass' => Config::VatClass_Null, // hidden
            // Triggers
            'triggerOrderStatus' => [], // hidden
            // Customer setings.
            'mainAddress' => Config::MainAddress_Invoice, // Shipping address will be hidden.
            // Invoice show/mail buttons. @todo: decide if and what we can and want to show
            'showInvoiceDetail' => false,
            'mailInvoiceDetail' => false,
            'showPackingSlipDetail' => false,
            'mailPackingSlipDetail' => false,
            'showInvoiceList' => false,
            'mailInvoiceList' => false,
            'showPackingSlipList' => false,
            'mailPackingSlipList' => false,
            'emailAsPdf' => false,

            'sendEmptyShipping' => false, // hidden

            'showPluginV84Message' => PHP_INT_MAX,
            'showPluginV8Message' => PHP_INT_MAX,
        ];
    }

    public function getLink(string $linkType, mixed $parameter = null): string
    {
        $addOnName = 'acumulus';
        $rootUri = rtrim($this->localApi()->getConfig('SystemURL'), '/');
        return match ($linkType) {
            'settings', 'mappings', 'batch', 'register', 'activate' => $this->getLink('modulePage') . "&page=$linkType",
            'fiscal-address-setting' => AddressType::Invoice,
            'modulePage' => "$rootUri/admin/addonmodules.php?module=$addOnName",
            'moduleUri' => "$rootUri/modules/addons/$addOnName",
            'logo' => $this->getLink('moduleUri') . '/Acumulus-Online-Boekhouden_icon_150.png',
            'pro-support-image' => $this->getLink('moduleUri') . '/pro-support-whmcs.png',
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
