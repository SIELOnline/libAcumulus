<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection Most parent methods are base/no-op implementations.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Config;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Payment\Model\PaymentMethodList;
use Magento\Sales\Model\ResourceModel\Status\Collection as OrderStatusCollection;
use Magento\Tax\Model\ResourceModel\TaxClass\Collection as TaxClassCollection;
use Siel\Acumulus\Config\ShopCapabilities as ShopCapabilitiesBase;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Magento\Helpers\Registry;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Meta;

use function sprintf;

/**
 * Defines the Magento 2 web shop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    public function getDefaultShopMappings(): array
    {
        return [
            DataType::Invoice => [
            ],
            DataType::Customer => [
                // Order, not Creditmemo
                Fld::ContactYourId => '[source::getOrder()::getShopObject()::getCustomerId()]',
                // Magento has 2 VAT numbers:
                // http://magento.stackexchange.com/questions/42164/there-are-2-vat-fields-in-onepage-checkout-which-one-should-i-be-using
                // Magento\Customer\Model\Address also has a getVatId() method, but that is not the Address we have here.
                // Order, not Creditmemo
                Fld::VatNumber => '[source::getOrder()::getShopObject()::getCustomerTaxvat())]',
                // Order, not Creditmemo
                Fld::Telephone => '[source::getShopObject()::getBillingAddress()::getTelephone()]',
                // Address
                Fld::Telephone2 => '[source::getShopObject()::getShippingAddress()::getTelephone()]',
                // Address
                Fld::Fax => '[source::getShopObject()::getBillingAddress()::getFax()|source::getShopObject()::getShippingAddress()::getFax()]',
                // Address
                // Email field of Address seems to be a copy of the Customer email field.
                Fld::Email => '[source::getShopObject()::getBillingAddress()::getEmail()|source::getShopObject()::getShippingAddress()::getEmail()]',
                // Address
            ],
            // Both Order and Creditmemo have get(Billing|Shipping)Address() methods.
            AddressType::Invoice => [
                Fld::CompanyName1 => '[source::getShopObject()::getBillingAddress()::getCompany()]', // Address
                Fld::FullName => '[source::getShopObject()::getBillingAddress()::getName()]', // Address
                Fld::Address1 => '[source::getShopObject()::getBillingAddress()::getStreetLine(1)]', // Address
                Fld::Address2 => '[source::getShopObject()::getBillingAddress()::getStreetLine(2)]', // Address
                Fld::PostalCode => '[source::getShopObject()::getBillingAddress()::getPostcode()]', // Address
                Fld::City => '[source::getShopObject()::getBillingAddress()::getCity()]', // Adress
                Fld::CountryCode => '[source::getShopObject()::getBillingAddress()::getCountryId()]', // Address
            ],
            AddressType::Shipping => [
                Fld::CompanyName1 => '[source::getShopObject()::getShippingAddress()::getCompany()]', // Address
                Fld::FullName => '[source::getShopObject()::getShippingAddress()::getName()]', // Address
                Fld::Address1 => '[source::getShopObject()::getShippingAddress()::getStreetLine(1)]', // Address
                Fld::Address2 => '[source::getShopObject()::getShippingAddress()::getStreetLine(2)]', // Address
                Fld::PostalCode => '[source::getShopObject()::getShippingAddress()::getPostcode()]', // Address
                Fld::City => '[source::getShopObject()::getShippingAddress()::getCity()]', // Adress
                Fld::CountryCode => '[source::getShopObject()::getShippingAddress()::getCountryId()]', // Address
            ],
            EmailAsPdfType::Invoice => [
                Fld::EmailTo => '[source::getShopObject()::getShippingAddress()::getEmail()]', // Address
            ],
            LineType::Item => [
                Meta::Id => '[item::getId()]',
                Fld::ItemNumber => '[item::getShopObject()::getSku()]',
                Fld::Product => '[item::getProduct()::getShopObject()::getName()]',
                // getBasePrice() exists on both CreditmemoInterface and OrderItemInterface.
                Fld::UnitPrice => '[sign*item::getShopObject()::getBasePrice()]',
                Meta::PrecisionUnitPrice => 0.01,
                // CreditmemoItemInterface::getQty() or OrderItemInterface::getQtyOrdered().
                Fld::Quantity => '[item::getShopObject()::getQty()|item::getShopObject()::getQtyOrdered()]',
                // Creditmemo::getOrderItem() to get the vat rate from the order item.
                Fld::VatRate => '[item::getShopObject()::getOrderItem()::getTaxPercent()|item::getShopObject()::getTaxPercent()]',
                // getBasePriceInclTax() exists on both CreditmemoInterface and OrderItemInterface.
                Meta::UnitPriceInc => '[sign*item::getShopObject()::getBasePriceInclTax()]',
                Meta::PrecisionUnitPriceInc => 0.01,
                Meta::VatRateSource => VatRateSource::Mapped,
                // Tax class id is a custom attribute, so this is handled by __call()
                Meta::VatClassId => '[item::getProduct()::getShopObject()::getTaxClassId()]',
                // Creditmemo::getOrderItem() to get the product type from the order item.
                Meta::ProductType => '[item::getShopObject()::getOrderItem()::getProductType()|item::getShopObject()::getProductType()]',
                Meta::ProductId => '[item::getShopObject()::getProductId()]',
            ],
            LineType::Shipping => [
                // Order, not Creditmemo
                Fld::Product => sprintf('[source::getOrder()::getShopObject()::getShippingDescription()|"%s"]', $this->t('shipping_costs')),
                Fld::UnitPrice => '[sign*source::getShopObject()::getBaseShippingAmount()]',
                Meta::PrecisionUnitPrice => 0.01,
                Fld::Quantity => 1,
                Meta::UnitPriceInc => '[sign*source::getShopObject()::getBaseShippingInclTax()]',
                Meta::PrecisionUnitPriceInc => 0.01,
                Meta::VatAmount => '[sign*source::getShopObject()::getBaseShippingTaxAmount()]',
                Meta::PrecisionVatAmount => 0.01,
            ],
            // Magento stores info about a discount on the order. Only the amount is also
            // stored on the creditmemo as it may be a part of the original discount.
            // In both objects it is stored as a negative amount.
            LineType::Discount => [
                Fld::Product => sprintf(
                    '["%s"+source::getOrder()::getShopObject()::getDiscountDescription()|"%s""+source::getOrder()::getShopObject()::getCouponCode()|"%s"]',
                    $this->t('discount_code'),
                    $this->t('discount_code'),
                    $this->t('shipping_costs')
                ),
                Meta::UnitPriceInc => '[sign*source::getShopObject()::getBaseDiscountAmount()]',
                Meta::PrecisionUnitPriceInc => 0.01,
                Fld::Quantity => 1,
                Meta::VatRateSource => VatRateSource::Strategy,
                Meta::StrategySplit => true,
            ],
            LineType::Manual => [
                Fld::Product => $this->t('refund_adjustment'),
                Meta::UnitPriceInc => '[source::getShopObject()::getBaseAdjustment()]',
                Meta::PrecisionUnitPriceInc => 0.001,
                Fld::Quantity => 1,
                // Let the strategy phase decide on the vat rate.
                // However, following the shop, it will always result in no vat.
                Meta::VatRateSource => VatRateSource::Strategy,
                Meta::StrategySplit => true,
            ],
        ];
    }

    public function getShopOrderStatuses(): array
    {
        /** @var OrderStatusCollection $model */
        $orderStatuses = Registry::getInstance()->create(OrderStatusCollection::class);
        $result = [];
        foreach ($orderStatuses as $orderStatus) {
            /** @var \Magento\Sales\Model\Order\Status $orderStatus */
            $result[$orderStatus->getStatus()] = $orderStatus->getLabel();
        }
        return $result;
    }

    public function getTriggerInvoiceEventOptions(): array
    {
        $result = parent::getTriggerInvoiceEventOptions();
        $result[Config::TriggerInvoiceEvent_Create] = $this->t('option_triggerInvoiceEvent_1');
        return $result;
    }

    public function getPaymentMethods(): array
    {
        $result = [];
        /** @var \Magento\Payment\Model\PaymentMethodList $paymentMethodListModel */
        $paymentMethodListModel = Registry::getInstance()->get(PaymentMethodList::class);
        // @todo: get active store/all stores?
        /** @noinspection PhpParamsInspection Wrong phpdoc */
        $paymentMethods = $paymentMethodListModel->getActiveList(null);
        foreach ($paymentMethods as $paymentMethod) {
            /** @var \Magento\Payment\Api\Data\PaymentMethodInterface $paymentMethod */
            $code = $paymentMethod->getCode();
            $title = $paymentMethod->getTitle();
            if (empty($title)) {
                $title = $code;
            }
            $result[$code] = $title;
        }
        return $result;
    }

    public function getVatClasses(): array
    {
        $result = [];
        /** @var TaxClassCollection $taxClassCollection */
        $taxClassCollection = Registry::getInstance()->create(TaxClassCollection::class);
        foreach ($taxClassCollection as $taxClass) {
            /** @var \Magento\Tax\Model\ClassModel $taxClass */
            $result[$taxClass->getClassId()] = $taxClass->getClassName();
        }
        return $result;
    }

    /**
     * @noinspection MultipleReturnStatementsInspection
     */
    public function getLink(string $linkType, mixed $parameter = null): string
    {
        $registry = Registry::getInstance();
        /** @var UrlInterface $urlInterface */
        $urlInterface = $registry->get(UrlInterface::class);
        switch ($linkType) {
            case 'register':
            case 'activate':
            case 'batch':
            case 'settings':
            case 'mappings':
                return $urlInterface->getUrl("acumulus/$linkType");
            case 'fiscal-address-setting':
                return $urlInterface->getUrl('admin/system_config/edit/section/tax');
            case 'logo':
                $repository = Registry::getInstance()->get(AssetRepository::class);
                return $repository->getUrl('Siel_AcumulusMa2::images/Acumulus-Online-Boekhouden_icon_150.png');
            case 'pro-support-image':
                $repository = Registry::getInstance()->get(AssetRepository::class);
                return $repository->getUrl('Siel_AcumulusMa2::images/pro-support-magento.png');
            case 'pro-support-link':
                return 'https://pay.siel.nl/?p=GWFrqaYs68gnhRGP2UAHkXhvGQqWEDmP46DMuxa5BPXvpjmu';
        }
        return parent::getLink($linkType, $parameter);
    }

    public function getFiscalAddressSetting(): string
    {
        return \Magento\Tax\Model\Config::CONFIG_XML_PATH_BASED_ON;
    }
}
