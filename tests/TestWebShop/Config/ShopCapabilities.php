<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\TestWebShop\Config;

use Siel\Acumulus\Config\Mappings;
use Siel\Acumulus\Config\ShopCapabilities as ShopCapabilitiesBase;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Data\LineType;

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

            ],
            DataType::Customer => [
                'contactYourId' => '[source::getOrder()::getSource()::customer::id]',
                'telephone' => '[source::getOrder()::getSource()::customer::telephone]',
                'telephone2' => '[source::getOrder()::getSource()::customer::mobile]',
                'email' => '[source::getOrder()::getSource()::customer::email]',
            ],
            AddressType::Invoice => [
                'companyName1' => '[source::getOrder()::getSource()::customer::company_name]',
                'salutation' => '["Beste"+source::getOrder()::getSource()::customer::first_name]',
                'fullName' => '[source::getOrder()::getSource()::customer::first_name+source::getOrder()::getSource()::customer::last_name]',
                'address1' => '[source::getOrder()::getSource()::customer::invoice_address::street]',
                'address2' => '[source::getOrder()::getSource()::customer::invoice_address::street2]',
                'postalCode' => '[source::getOrder()::getSource()::customer::invoice_address::postal_code]',
                'city' => '[source::getOrder()::getSource()::customer::invoice_address::city]',
                'countryCode' => '[source::getOrder()::getSource()::customer::invoice_address::country_code]',
            ],
            AddressType::Shipping => [
                'companyName1' => '[source::getOrder()::getSource()::customer::company_name]',
                'salutation' => '["Beste"+source::getOrder()::getSource()::customer::first_name]',
                'fullName' => '[source::getOrder()::getSource()::customer::first_name+source::getOrder()::getSource()::customer::last_name]',
                'address1' => '[source::getOrder()::getSource()::customer::shipping_address::street]',
                'address2' => '[source::getOrder()::getSource()::customer::shipping_address::street2]',
                'postalCode' => '[source::getOrder()::getSource()::customer::shipping_address::postal_code]',
                'city' => '[source::getOrder()::getSource()::customer::shipping_address::city]',
                'countryCode' => '[source::getOrder()::getSource()::customer::shipping_address::country_code]',
            ],
            EmailAsPdfType::Invoice => [
                'emailTo' => '[source::getOrder()::getSource()::customer::email]',
                'emailBcc' => 'dev@example.com',
            ],
            EmailAsPdfType::PackingSlip => [
                'emailTo' => '[source::getOrder()::getSource()::customer::email]',
                'emailBcc' => 'dev@example.com',
            ],
            LineType::Item => [
                'itemNumber' => '[sku]',
                'productName' => '[name]',
                'costPrice' => '[cost_price]',
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
