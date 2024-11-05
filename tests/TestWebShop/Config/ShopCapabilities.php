<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Config;

use Siel\Acumulus\Config\ShopCapabilities as ShopCapabilitiesBase;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Fld;

/**
 * Defines the TestWebShop specific capabilities.
 *
 * For now, we only have a minimal implementation.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * @inheritDoc
     */
    protected function getTokenInfoSource(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    protected function getTokenInfoShopProperties(): array
    {
        return [];
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection parent method returns empty array.
     */
    public function getDefaultShopMappings(): array
    {
        return [
            DataType::Invoice => [
            ],
            DataType::Customer => [
                Fld::ContactYourId => '[source::getOrder()::getShopObject()::customer::id]',
                Fld::Salutation => '["Beste"+source::getOrder()::getShopObject()::customer::first_name]',
                Fld::Telephone => '[source::getOrder()::getShopObject()::customer::telephone]',
                Fld::Telephone2 => '[source::getOrder()::getShopObject()::customer::mobile]',
                Fld::Email => '[source::getOrder()::getShopObject()::customer::email]',
            ],
            AddressType::Invoice => [
                Fld::CompanyName1 => '[source::getOrder()::getShopObject()::customer::company_name]',
                Fld::FullName => '[source::getOrder()::getShopObject()::customer::first_name+source::getOrder()::getShopObject()::customer::last_name]',
                Fld::Address1 => '[source::getOrder()::getShopObject()::customer::invoice_address::street]',
                Fld::Address2 => '[source::getOrder()::getShopObject()::customer::invoice_address::street2]',
                Fld::PostalCode => '[source::getOrder()::getShopObject()::customer::invoice_address::postal_code]',
                Fld::City => '[source::getOrder()::getShopObject()::customer::invoice_address::city]',
                Fld::CountryCode => '[source::getOrder()::getShopObject()::customer::invoice_address::country_code]',
            ],
            AddressType::Shipping => [
                Fld::CompanyName1 => '[source::getOrder()::getShopObject()::customer::company_name]',
                Fld::FullName => '[source::getOrder()::getShopObject()::customer::first_name+source::getOrder()::getShopObject()::customer::last_name]',
                Fld::Address1 => '[source::getOrder()::getShopObject()::customer::shipping_address::street]',
                Fld::Address2 => '[source::getOrder()::getShopObject()::customer::shipping_address::street2]',
                Fld::PostalCode => '[source::getOrder()::getShopObject()::customer::shipping_address::postal_code]',
                Fld::City => '[source::getOrder()::getShopObject()::customer::shipping_address::city]',
                Fld::CountryCode => '[source::getOrder()::getShopObject()::customer::shipping_address::country_code]',
            ],
            EmailAsPdfType::Invoice => [
                Fld::EmailTo => '[source::getOrder()::getShopObject()::customer::email]',
                Fld::EmailBcc => 'dev@example.com',
            ],
            EmailAsPdfType::PackingSlip => [
                Fld::EmailTo => '[source::getOrder()::getShopObject()::customer::email]',
                Fld::EmailBcc => 'dev@example.com',
            ],
            LineType::Item => [
                Fld::ItemNumber => '[sku]',
                Fld::Product => '[name]',
                Fld::CostPrice => '[cost_price]',
                // @todo: others? (e.g. quantity, unit price, metadata)
            ],
        ];
    }

    public function getShopOrderStatuses(): array
    {
        return [];
    }

    public function getPaymentMethods(): array
    {
        return [];
    }

    public function getVatClasses(): array
    {
        return [];
    }

    public function getLink(string $linkType): string
    {
        return match ($linkType) {
            'register', 'activate', 'batch', 'settings', 'mappings', 'fiscal-address-setting' => "admin.php?page=acumulus_$linkType",
            'logo' => 'acumulus/siel-logo.svg',
            'pro-support-image' => 'acumulus/pro-support-woocommerce.png',
            'pro-support-link' => 'https://pay.siel.nl/?p=3t0EasGQCcX0lPlraqMiGkTxFRmRo3zicBbhMtmD69bGozBl',
            default => parent::getLink($linkType),
        };
    }

    public function getFiscalAddressSetting(): string
    {
        return 'testwebshop_tax_based_on';
    }
}
