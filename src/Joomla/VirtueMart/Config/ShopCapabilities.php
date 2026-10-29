<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection  Most parent methods are base/no-op implementations.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\VirtueMart\Config;

use Joomla\CMS\Uri\Uri;
use JText;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Joomla\Config\ShopCapabilities as ShopCapabilitiesBase;
use Siel\Acumulus\Meta;
use VirtueMartModelCalc;
use VirtueMartModelOrderstatus;
use VmModel;

/**
 * Defines the VirtueMart web shop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    public function getDefaultShopMappings(): array
    {
        return [
            DataType::Invoice => [
                Meta::ProductPricesIncludeVat => false,
            ],
            DataType::Customer => [
                // Customer defaults.
                Fld::ContactYourId => '[source::getShopObject()::details::BT::virtuemart_user_id]',
                Fld::VatNumber => '[source::getShopObject()::details::BT::tax_exemption_number]',
                Fld::Telephone => '[source::getShopObject()::details::BT::phone_1|source::getShopObject()::details::ST::phone_1]',
                Fld::Telephone2 => '[source::getShopObject()::details::BT::phone_2|source::getShopObject()::details::ST::phone_2]',
                Fld::Fax => '[source::getShopObject()::details::BT::fax|source::getShopObject()::details::ST::fax]',
                Fld::Email => '[source::getShopObject()::details::BT::email|source::getShopObject()::details::ST::email]',
            ],
            AddressType::Invoice => [
                Fld::CompanyName1 => '[source::getShopObject()::details::BT::company]',
                Fld::FullName =>
                    '[source::getShopObject()::details::BT::first_name' .
                    '+source::getShopObject()::details::BT::middle_name' .
                    '+source::getShopObject()::details::BT::last_name]',
                Fld::Address1 => '[source::getShopObject()::details::BT::address_1]',
                Fld::Address2 => '[source::getShopObject()::details::BT::address_2]',
                Fld::PostalCode => '[source::getShopObject()::details::BT::zip]',
                Fld::City => '[source::getShopObject()::details::BT::city]',
                Meta::ShopCountryId => '[source::getShopObject()::details::BT::virtuemart_country_id]',
            ],
            AddressType::Shipping => [
                Fld::CompanyName1 => '[source::getShopObject()::details::ST::company]',
                Fld::FullName =>
                    '[source::getShopObject()::details::ST::first_name' .
                    '+source::getShopObject()::details::ST::middle_name' .
                    '+source::getShopObject()::details::ST::last_name]',
                Fld::Address1 => '[source::getShopObject()::details::ST::address_1]',
                Fld::Address2 => '[source::getShopObject()::details::ST::address_2]',
                Fld::PostalCode => '[source::getShopObject()::details::ST::zip]',
                Fld::City => '[source::getShopObject()::details::ST::city]',
                Meta::ShopCountryId => '[source::getShopObject()::details::ST::virtuemart_country_id]',
            ],
            EmailAsPdfType::Invoice => [
                Fld::EmailTo => '[source::getShopObject()::details::BT::email|source::getShopObject()::details::ST::email]',
            ],
            LineType::Item => [
                // @todo: complete when we start converting lines to using collectors.
                Fld::ItemNumber => '[item::getShopObject()::order_item_sku]',
                Fld::Product => '[item::getShopObject()::order_item_name]',
                Meta::ProductId => '[item::getShopObject()::virtuemart_product_id]',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     *
     * This default implementation returns order and credit note. Override if
     * the specific shop supports other types or does not support credit notes.
     */
    public function getSupportedInvoiceSourceTypes(): array
    {
        $result = parent::getSupportedInvoiceSourceTypes();
        unset($result[Source::CreditNote]);
        return $result;
    }

    public function getShopOrderStatuses(): array
    {
        /** @var VirtueMartModelOrderstatus $orderStatusModel */
        $orderStatusModel = VmModel::getModel('orderstatus');
        /** @var array[] $orderStatuses Method getOrderStatusNames() has an incorrect @return type ... */
        $orderStatuses = $orderStatusModel::getOrderStatusNames();
        foreach ($orderStatuses as &$value) {
            $value = JText::_($value['order_status_name']);
        }
        return $orderStatuses;
    }

    /**
     * {@inheritdoc}
     *
     * VirtueMart does not group collections of tax rates into a tax class, but
     * can assign a different tax rate (calc rules) to a product - user group
     * combination. I guess that's how to implement EU vat goods in
     * VirtueMart. This means that users should define different tax calc rules
     * for each country - rate combination, even if the rates are the same,
     * otherwise this plugin might still not be able to distinguish between
     * Dutch and Belgium 21% vat.
     */
    public function getVatClasses(): array
    {
        $result = [];
        /** @var \TableCalcs[] $taxes */
        $taxes = VirtueMartModelCalc::getTaxes();
        foreach ($taxes as $tax) {
            $result[$tax->virtuemart_calc_id] = $tax->calc_name;
        }

        return $result;
    }

    public function getPaymentMethods(): array
    {
        $result = [];
        /** @var \VirtueMartModelPaymentmethod $model */
        $model = VmModel::getModel('paymentmethod');
        $paymentMethods = $model->getPayments(true);
        foreach ($paymentMethods as $paymentMethod) {
            $result[$paymentMethod->virtuemart_paymentmethod_id] = $paymentMethod->payment_name;
        }
        return $result;
    }

    public function getLink(string $linkType, mixed $parameter = null): string
    {
        return match ($linkType) {
            'pro-support-image' => Uri::root(true) . '/administrator/components/com_acumulus/media/pro-support-virtuemart.png',
            'pro-support-link' => 'https://pay.siel.nl/?p=t7jYwPSWYgFJdWQuWVJmC0R6d6LWHKmNVsNUlgtv82TIhgNS',
            default => parent::getLink($linkType, $parameter),
        };
    }

    public function getFiscalAddressSetting(): string
    {
        return AddressType::Shipping;
    }
}
