<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Config;

use Context;
use Module;
use OrderState;
use PaymentModule;
use Siel\Acumulus\Config\ShopCapabilities as ShopCapabilitiesBase;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Meta;
use TaxRulesGroup;

use function sprintf;

/**
 * Defines the PrestaShop web shop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    public function getDefaultShopMappings(): array
    {
        // PrestaShop: both addresses are always filled.
        return [
            DataType::Invoice => [
                // In PrestaShop, you can enter a price with or without vat, the other
                // being automatically updated. So we can not know how prices where
                // entered.
                Meta::ProductPricesIncludeVat => null,
            ],
            DataType::Customer => [
                // Customer defaults.
                Fld::ContactYourId => '[source::getShopObject()::id_customer]', // Order|OrderSlip
                Fld::VatNumber => '[address_invoice::vat_number' // Address
                    . '|address_shipping::vat_number]', // Address
                Fld::Telephone => '[address_invoice::phone|address_invoice::phone_mobile'
                    . '|address_shipping::phone|address_shipping::phone_mobile]', // Address
                Fld::Telephone2 => '[address_shipping::phone|address_shipping::phone_mobile'
                    . '|address_invoice::phone|address_invoice::phone_mobile]', // Address
                Fld::Email => '[source::getOrder()::getShopObject()::getCustomer()::email]', // Customer
                Fld::Website => '[source::getOrder()::getShopObject()::getCustomer()::website]', // Customer
                Fld::Mark => '[source::getOrder()::getShopObject()::getCustomer()::note]', // Customer (but not used?)
            ],
            AddressType::Invoice => [ // address_invoice instanceof Address
                Fld::CompanyName1 => '[address_invoice::company]',
                Fld::FullName => '[address_invoice::firstname+address_invoice::lastname]',
                Fld::Address1 => '[address_invoice::address1]',
                Fld::Address2 => '[address_invoice::address2]',
                Fld::PostalCode => '[address_invoice::postcode]',
                Fld::City => '[address_invoice::city]',
                Meta::ShopCountryId => '[address_invoice::id_country]',
                Meta::AddressId => '[address_invoice::id]',
            ],
            AddressType::Shipping => [ // address_shipping instanceof Address
                Fld::CompanyName1 => '[address_shipping::company]',
                Fld::FullName => '[address_shipping::firstname+address_shipping::lastname]',
                Fld::Address1 => '[address_shipping::address1]',
                Fld::Address2 => '[address_shipping::address2]',
                Fld::PostalCode => '[address_shipping::postcode]',
                Fld::City => '[address_shipping::city]',
                Meta::ShopCountryId => '[address_shipping::id_country]',
                Meta::AddressId => '[address_shipping::id]',
            ],
            EmailAsPdfType::Invoice => [
                Fld::EmailTo => '[source::getOrder()::getShopObject()::getCustomer()::email]',
            ],
            LineType::Item => [ // item instanceof Item
                Meta::ProductId => '[item::getShopObject()::product_id]',
                Fld::ItemNumber => '[item::getShopObject()::product_reference'
                    . '|item::getShopObject()::product_ean13'
                    . '|item::getShopObject()::product_isbn'
                    . '|item::getShopObject()::product_upc'
                    . '|item::getShopObject()::product_mpn'
                    . '|item::getShopObject()::product_supplier_reference]',
                Fld::Product => '[item::getShopObject()::product_name]',
                Fld::Quantity => '[item::getShopObject()::product_quantity]',
                Fld::CostPrice => '[sign*item::getShopObject()::purchase_supplier_price]',
                Fld::UnitPrice => '[sign*item::getShopObject()::unit_price_tax_excl]',
                Fld::VatRate => '[item::getShopObject()::rate]',
                Meta::UnitPriceInc => '[sign*item::getShopObject()::unit_price_tax_incl]',
                Meta::LineAmount => '[sign*item::getShopObject()::total_price_tax_excl]',
                Meta::LineAmountInc => '[sign*item::getShopObject()::total_price_tax_incl]',
                Meta::VatRateSource => VatRateSource::Mapped,
                // In case of a discount that applies to the collected line, the following
                // 2 fields will be unset, but will be calculated in the Completor phase.
                Meta::VatAmount => '[sign*item::getShopObject()::unit_amount]',
                Meta::LineVatAmount => '[sign*item::getShopObject()::total_amount]',
            ],
            LineType::Shipping => [
                Fld::Product => sprintf('[carrier::name|"%s"]', $this->t('shipping_costs')),
                Fld::Quantity => 1,
                Fld::UnitPrice => '[sign*source::getShopObject()::total_shipping_tax_excl]',
                Fld::VatRate => '[source::getOrder()::getShopObject()::carrier_tax_rate]',
                Meta::UnitPriceInc => '[sign*source::getShopObject()::total_shipping_tax_incl]',
                Meta::VatRateSource => VatRateSource::Mapped,
                // The shipping costs excl. VAT is rounded to a cent, so recalculating
                // this amount might prevent rounding differences for this line.
                Meta::RecalculatePrice => Fld::UnitPrice,
            ],
            LineType::GiftWrapping => [
                Fld::Product => $this->t('gift_wrapping'),
                Fld::Quantity => 1,
                Fld::UnitPrice => '[sign*source::getShopObject()::total_wrapping_tax_excl]',
                Meta::UnitPriceInc => '[sign*source::getShopObject()::total_wrapping_tax_incl]',
                // The gift wrapping fee setting is stored excl. VAT and rounded to a
                // cent, while on the order/invoice it appears as an amount incl. VAT
                // rounded to a cent. So recalculating the price ex might prevent rounding
                // differences for this line.
                Meta::PrecisionUnitPrice => 0.01,
                Meta::PrecisionUnitPriceInc => 0.01,
                Meta::RecalculatePrice => Fld::UnitPrice,
            ],
            LineType::PaymentFee => [
                Fld::Product => $this->t('payment_costs'),
                Fld::Quantity => 1,
                /**
                 * Typically, 2 our of 3 of the following fields will be set by our {@see ThirdPartyModuleSupport} class,
                 * so we cannot add precision metadata.
                 */
                Fld::UnitPrice => sprintf('[sign*paymentFeeLineInfo::%s]', Fld::UnitPrice),
                Fld::VatRate => sprintf('[paymentFeeLineInfo::%s]', Fld::VatRate),
                Meta::UnitPriceInc => sprintf('[sign*paymentFeeLineInfo::metadataGet("%s")]', Meta::UnitPriceInc),
            ],
            LineType::Discount => [
                Fld::ItemNumber => '[discountLineInfo::id_cart_rule]',
                Fld::Product => sprintf('["%s"+discountLineInfo::name]', $this->t('discount_code')),
                Fld::Quantity => 1,
                Fld::UnitPrice => '[-sign*discountLineInfo::value_tax_excl]',
                Meta::UnitPriceInc => '[-sign*discountLineInfo::value]',
                Meta::PrecisionUnitPrice => 0.01,
                Meta::PrecisionUnitPriceInc => 0.01,
                // If no match is found, this line may be split.
                Meta::StrategySplit => true,
            ],
        ];
    }

    public function getShopOrderStatuses(): array
    {
        $statuses = OrderState::getOrderStates(Context::getContext()->language->id);
        $result = [];
        foreach ($statuses as $status) {
            $result[$status['id_order_state']] = $status['name'];
        }
        return $result;
    }

    public function getPaymentMethods(): array
    {
        $result = [];
        $paymentModules = PaymentModule::getInstalledPaymentModules();
        foreach($paymentModules as $paymentModule)
        {
            $module = Module::getInstanceById($paymentModule['id_module']);
            $result[$module->name] = $module->displayName;
        }
        return $result;
    }

    public function getVatClasses(): array
    {
        $result = [];
        /** @var array[] $taxClasses */
        $taxClasses = TaxRulesGroup::getTaxRulesGroups();
        foreach ($taxClasses as $taxClass) {
            $result[$taxClass['id_tax_rules_group']] = $taxClass['name'];
        }
        return $result;
    }

    public function getLink(string $linkType, mixed $parameter = null): string
    {
        switch ($linkType) {
            case 'register':
            case 'activate':
            case 'mappings':
            case 'batch':
            case 'invoice':
            case 'message':
                $action = ucfirst($linkType);
                return Context::getContext()->link->getAdminLink("AdminAcumulus$action");
            case 'settings':
                return Context::getContext()->link->getAdminLink('AdminModules', true, [], ['configure' => 'acumulus']);
            case 'fiscal-address-setting':
                return Context::getContext()->link->getAdminLink('AdminTaxes') . '#form';
            case 'logo':
                /** @noinspection PhpUndefinedConstantInspection Defined in config,inc.php, line 130 or 132. */
                return  __PS_BASE_URI__ . 'modules/acumulus/views/img/acumulus-logo.svg';
            case 'pro-support-image':
                /** @noinspection PhpUndefinedConstantInspection Defined in config,inc.php, line 130 or 132. */
                return  __PS_BASE_URI__ . 'modules/acumulus/views/img/pro-support-prestashop.png';
            case 'pro-support-link':
                return 'https://pay.siel.nl/?p=KL9pPSIIdMzqIieXB0FA56mTgyOvlEfUEosWM3ODsTZODa0P';
        }
        return parent::getLink($linkType, $parameter);
    }

    public function getFiscalAddressSetting(): string
    {
        return 'PS_TAX_ADDRESS_TYPE'; // 'id_address_invoice' or 'id_address_delivery'
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    public function getReleasesRepository(): ?array
    {
        return ['owner' => 'SIELOnline', 'repo' => 'Acumulus-for-PrestaShop'];
    }
}
