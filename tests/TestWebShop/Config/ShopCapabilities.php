<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\TestWebShop\Config;

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

    public function getDefaultShopConfig(): array
    {
        return [];
    }

    public function getDefaultPropertyMappings(): array
    {
        return [
            DataType::Invoice => [
                // @todo: fields that come from the Order, metadata (if it comes
                //   from Source, it should probably be in config (for now).
            ],
            DataType::Customer => [
                Fld::ContactYourId => '[source::getOrder()::getSource()::customer::id]',
                Fld::Telephone => '[source::getOrder()::getSource()::customer::telephone]',
                Fld::Telephone2 => '[source::getOrder()::getSource()::customer::mobile]',
                Fld::Email => '[source::getOrder()::getSource()::customer::email]',
            ],
            AddressType::Invoice => [
                Fld::CompanyName1 => '[source::getOrder()::getSource()::customer::company_name]',
                Fld::Salutation => '["Beste"+source::getOrder()::getSource()::customer::first_name]',
                Fld::FullName => '[source::getOrder()::getSource()::customer::first_name+source::getOrder()::getSource()::customer::last_name]',
                Fld::Address1 => '[source::getOrder()::getSource()::customer::invoice_address::street]',
                Fld::Address2 => '[source::getOrder()::getSource()::customer::invoice_address::street2]',
                Fld::PostalCode => '[source::getOrder()::getSource()::customer::invoice_address::postal_code]',
                Fld::City => '[source::getOrder()::getSource()::customer::invoice_address::city]',
                Fld::CountryCode => '[source::getOrder()::getSource()::customer::invoice_address::country_code]',
            ],
            AddressType::Shipping => [
                Fld::CompanyName1 => '[source::getOrder()::getSource()::customer::company_name]',
                Fld::Salutation => '["Beste"+source::getOrder()::getSource()::customer::first_name]',
                Fld::FullName => '[source::getOrder()::getSource()::customer::first_name+source::getOrder()::getSource()::customer::last_name]',
                Fld::Address1 => '[source::getOrder()::getSource()::customer::shipping_address::street]',
                Fld::Address2 => '[source::getOrder()::getSource()::customer::shipping_address::street2]',
                Fld::PostalCode => '[source::getOrder()::getSource()::customer::shipping_address::postal_code]',
                Fld::City => '[source::getOrder()::getSource()::customer::shipping_address::city]',
                Fld::CountryCode => '[source::getOrder()::getSource()::customer::shipping_address::country_code]',
            ],
            EmailAsPdfType::Invoice => [
                Fld::EmailTo => '[source::getOrder()::getSource()::customer::email]',
                Fld::EmailBcc => 'dev@example.com',
            ],
            EmailAsPdfType::PackingSlip => [
                Fld::EmailTo => '[source::getOrder()::getSource()::customer::email]',
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
}
