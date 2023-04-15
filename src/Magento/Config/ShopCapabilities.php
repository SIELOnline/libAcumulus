<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Config;

use Magento\Customer\Model\Customer;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Payment\Model\PaymentMethodList;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Item;
use Magento\Sales\Model\ResourceModel\Status\Collection as OrderStatusCollection;
use Magento\Tax\Model\ResourceModel\TaxClass\Collection as TaxClassCollection;
use Siel\Acumulus\Config\ShopCapabilities as ShopCapabilitiesBase;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Magento\Helpers\Registry;
use Siel\Acumulus\Config\Config;

/**
 * Defines the Magento 2 web shop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    private array $order = [
        'adjustmentNegative',
        'adjustmentPositive',
        'appliedRuleIds',
        'baseAdjustmentNegative',
        'baseAdjustmentPositive',
        'baseCurrencyCode',
        'baseDiscountAmount',
        'baseDiscountCanceled',
        'baseDiscountInvoiced',
        'baseDiscountRefunded',
        'baseGrandTotal',
        'baseDiscountTaxCompensationAmount',
        'baseDiscountTaxCompensationInvoiced',
        'baseDiscountTaxCompensationRefunded',
        'baseShippingAmount',
        'baseShippingCanceled',
        'baseShippingDiscountAmount',
        'baseShippingDiscountTaxCompensationAmnt',
        'baseShippingInclTax',
        'baseShippingInvoiced',
        'baseShippingRefunded',
        'baseShippingTaxAmount',
        'baseShippingTaxRefunded',
        'baseSubtotal',
        'baseSubtotalCanceled',
        'baseSubtotalInclTax',
        'baseSubtotalInvoiced',
        'baseSubtotalRefunded',
        'baseTaxAmount',
        'baseTaxCanceled',
        'baseTaxInvoiced',
        'baseTaxRefunded',
        'baseTotalCanceled',
        'baseTotalDue',
        'baseTotalInvoiced',
        'baseTotalInvoicedCost',
        'baseTotalOfflineRefunded',
        'baseTotalOnlineRefunded',
        'baseTotalPaid',
        'baseTotalQtyOrdered',
        'baseTotalRefunded',
        'baseToGlobalRate',
        'baseToOrderRate',
        'billingAddressId',
        'canShipPartially',
        'canShipPartiallyItem',
        'couponCode',
        'createdAt',
        'customerDob',
        'customerEmail',
        'customerFirstname',
        'customerGender',
        'customerGroupId',
        'customerId',
        'customerIsGuest',
        'customerLastname',
        'customerMiddlename',
        'customerNote',
        'customerNoteNotify',
        'customerPrefix',
        'customerSuffix',
        'customerTaxvat',
        'discountAmount',
        'discountCanceled',
        'discountDescription',
        'discountInvoiced',
        'discountRefunded',
        'editIncrement',
        'emailSent',
        'entityId',
        'extCustomerId',
        'extOrderId',
        'forcedShipmentWithInvoice',
        'globalCurrencyCode',
        'grandTotal',
        'discountTaxCompensationAmount',
        'discountTaxCompensationInvoiced',
        'discountTaxCompensationRefunded',
        'holdBeforeState',
        'holdBeforeStatus',
        'incrementId',
        'isVirtual',
        'orderCurrencyCode',
        'originalIncrementId',
        'paymentAuthorizationAmount',
        'paymentAuthExpiration',
        'protectCode',
        'quoteAddressId',
        'quoteId',
        'relationChildId',
        'relationChildRealId',
        'relationParentId',
        'relationParentRealId',
        'remoteIp',
        'shippingAmount',
        'shippingCanceled',
        'shippingDescription',
        'shippingDiscountAmount',
        'shippingDiscountTaxCompensationAmount',
        'shippingInclTax',
        'shippingInvoiced',
        'shippingRefunded',
        'shippingTaxAmount',
        'shippingTaxRefunded',
        'state',
        'status',
        'storeCurrencyCode',
        'storeId',
        'storeName',
        'storeToBaseRate',
        'storeToOrderRate',
        'subtotal',
        'subtotalCanceled',
        'subtotalInclTax',
        'subtotalInvoiced',
        'subtotalRefunded',
        'taxAmount',
        'taxCanceled',
        'taxInvoiced',
        'taxRefunded',
        'totalCanceled',
        'totalDue',
        'totalInvoiced',
        'totalItemCount',
        'totalOfflineRefunded',
        'totalOnlineRefunded',
        'totalPaid',
        'totalQtyOrdered',
        'totalRefunded',
        'updatedAt',
        'weight',
        'xForwardedFor',
        'items',
        'billingAddress',
        'payment',
        'statusHistories',
        'extensionAttributes',
    ];
    private array $creditMemo = [
        'adjustment',
        'adjustmentNegative',
        'adjustmentPositive',
        'baseAdjustment',
        'baseAdjustmentNegative',
        'baseAdjustmentPositive',
        'baseCurrencyCode',
        'baseDiscountAmount',
        'baseGrandTotal',
        'baseDiscountTaxCompensationAmount',
        'baseShippingAmount',
        'baseShippingDiscountTaxCompensationAmnt',
        'baseShippingInclTax',
        'baseShippingTaxAmount',
        'baseSubtotal',
        'baseSubtotalInclTax',
        'baseTaxAmount',
        'baseToGlobalRate',
        'baseToOrderRate',
        'billingAddressId',
        'createdAt',
        'creditmemoStatus',
        'discountAmount',
        'discountDescription',
        'emailSent',
        'entityId',
        'globalCurrencyCode',
        'grandTotal',
        'discountTaxCompensationAmount',
        'incrementId',
        'invoiceId',
        'orderCurrencyCode',
        'orderId',
        'shippingAddressId',
        'shippingAmount',
        'shippingDiscountTaxCompensationAmount',
        'shippingInclTax',
        'shippingTaxAmount',
        'state',
        'storeCurrencyCode',
        'storeId',
        'storeToBaseRate',
        'storeToOrderRate',
        'subtotal',
        'subtotalInclTax',
        'taxAmount',
        'transactionId',
        'updatedAt',
        'items',
        'comments',
        'extensionAttributes',
    ];

    protected function getTokenInfoSource(): array
    {
        return [
            'class' => [Order::class, Creditmemo::class],
            'file' => ['vendor/magento/module-sales/Model/Order.php', 'vendor/magento/module-sales/Model/Order/Creditmemo.php'],
            'properties' => array_intersect($this->order, $this->creditMemo),
            'properties-more' => true,
        ];
    }

    protected function getTokenInfoRefund(): array
    {
        return [
            'class' => 'Mage_Sales_Model_Order_CreditMemo',
            'file' => 'app/code/core/Mage/Sales/Model/Order/Creditmemo.php',
            'properties' => array_diff($this->creditMemo, $this->order),
            'properties-more' => true,
        ];
    }

    protected function getTokenInfoOrder(): array
    {
        return [
            'class' => '\Magento\Sales\Model\Order\Order',
            'file' => 'vendor/magento/module-sales/Model/Order/Order.php',
            'properties' => array_diff($this->order, $this->creditMemo),
            'properties-more' => true,
        ];
    }

    protected function getTokenInfoShopProperties(): array
    {
        $orderItem = [
            'additionalData',
            'amountRefunded',
            'appliedRuleIds',
            'baseAmountRefunded',
            'baseCost',
            'baseDiscountAmount',
            'baseDiscountInvoiced',
            'baseDiscountRefunded',
            'baseDiscountTaxCompensationAmount',
            'baseDiscountTaxCompensationInvoiced',
            'baseDiscountTaxCompensationRefunded',
            'baseOriginalPrice',
            'basePrice',
            'basePriceInclTax',
            'baseRowInvoiced',
            'baseRowTotal',
            'baseRowTotalInclTax',
            'baseTaxAmount',
            'baseTaxBeforeDiscount',
            'baseTaxInvoiced',
            'baseTaxRefunded',
            'baseWeeeTaxAppliedAmount',
            'baseWeeeTaxAppliedRowAmnt',
            'baseWeeeTaxDisposition',
            'baseWeeeTaxRowDisposition',
            'createdAt',
            'description',
            'discountAmount',
            'discountInvoiced',
            'discountPercent',
            'discountRefunded',
            'eventId',
            'extOrderItemId',
            'freeShipping',
            'gwBasePrice',
            'gwBasePriceInvoiced',
            'gwBasePriceRefunded',
            'gwBaseTaxAmount',
            'gwBaseTaxAmountInvoiced',
            'gwBaseTaxAmountRefunded',
            'gwId',
            'gwPrice',
            'gwPriceInvoiced',
            'gwPriceRefunded',
            'gwTaxAmount',
            'gwTaxAmountInvoiced',
            'gwTaxAmountRefunded',
            'discountTaxCompensationAmount',
            'discountTaxCompensationCanceled',
            'discountTaxCompensationInvoiced',
            'discountTaxCompensationRefunded',
            'isQtyDecimal',
            'isVirtual',
            'itemId',
            'lockedDoInvoice',
            'lockedDoShip',
            'name',
            'noDiscount',
            'orderId',
            'originalPrice',
            'parentItemId',
            'price',
            'priceInclTax',
            'productId',
            'productType',
            'qtyBackordered',
            'qtyCanceled',
            'qtyInvoiced',
            'qtyOrdered',
            'qtyRefunded',
            'qtyReturned',
            'qtyShipped',
            'quoteItemId',
            'rowInvoiced',
            'rowTotal',
            'rowTotalInclTax',
            'rowWeight',
            'sku',
            'storeId',
            'taxAmount',
            'taxBeforeDiscount',
            'taxCanceled',
            'taxInvoiced',
            'taxPercent',
            'taxRefunded',
            'updatedAt',
            'weeeTaxApplied',
            'weeeTaxAppliedAmount',
            'weeeTaxAppliedRowAmount',
            'weeeTaxDisposition',
            'weeeTaxRowDisposition',
            'weight',
            'parentItem',
        ];
        $creditMemoItem = [
            'additionalData',
            'baseCost',
            'baseDiscountAmount',
            'baseDiscountTaxCompensationAmount',
            'basePrice',
            'basePriceInclTax',
            'baseRowTotal',
            'baseRowTotalInclTax',
            'baseTaxAmount',
            'baseWeeeTaxAppliedAmount',
            'baseWeeeTaxAppliedRowAmnt',
            'baseWeeeTaxDisposition',
            'baseWeeeTaxRowDisposition',
            'description',
            'discountAmount',
            'entityId',
            'discountTaxCompensationAmount',
            'name',
            'orderItemId',
            'parentId',
            'price',
            'priceInclTax',
            'productId',
            'qty',
            'rowTotal',
            'rowTotalInclTax',
            'sku',
            'taxAmount',
            'weeeTaxApplied',
            'weeeTaxAppliedAmount',
            'weeeTaxAppliedRowAmount',
            'weeeTaxDisposition',
            'weeeTaxRowDisposition',
        ];
        return [
            'billingAddress' => [
                'class' => Address::class,
                'file' => 'vendor/magento/module-sales/Model/Order/Address.php',
                'properties' => [
                    'addressType',
                    'city',
                    'company',
                    'countryId',
                    'customerAddressId',
                    'customerId',
                    'email',
                    'entityId',
                    'fax',
                    'firstname',
                    'lastname',
                    'middlename',
                    'parentId',
                    'postcode',
                    'prefix',
                    'region',
                    'regionCode',
                    'regionId',
                    'street',
                    'suffix',
                    'telephone',
                    'vatId',
                    'vatIsValid',
                    'vatRequestDate',
                    'vatRequestId',
                    'vatRequestSuccess',
                    'extensionAttributes',
                ],
                'properties-more' => true,
            ],
            'shippingAddress' => [
                'more-info' => $this->t('see_billing_address'),
                'class' => Address::class,
                'file' => 'vendor/magento/module-sales/Model/Order/Address.php',
                'properties' => [
                    $this->t('see_above'),
                ],
                'properties-more' => false,
            ],
            'customer' => [
                'class' => Customer::class,
                'file' => 'vendor/magento/module-customer/Model/Customer.php',
                'properties' => [
                    'name',
                    'taxClassId',
                    'store',
                    'entityId',
                    'websiteId',
                    'email',
                    'groupId',
                    'incrementId',
                    'storeId',
                    'createdAt',
                    'updatedAt',
                    'isActive',
                    'disableAutoGroupChange',
                    'prefix',
                    'firstname',
                    'middlename',
                    'lastname',
                    'suffix',
                    'dob',
                    'taxvat',
                    'gender',
                ],
                'properties-more' => true,
            ],
            'item' => [
                'class' => [Order\Item::class, Item::class],
                'file' => [
                    'vendor/magento/module-sales/Model/Order/Item.php',
                    'vendor/magento/module-sales/Model/Order/Creditmemo/Item.php'
                ],
                'properties' => array_unique(array_merge($orderItem, $creditMemoItem)),
                'properties-more' => true,
            ],
        ];
    }

    public function getDefaultShopConfig(): array
    {
        return [
            'contactYourId' => '[customer::incrementId|customer::entityId]', // \Mage\Customer\Model\Customer
            'companyName1' => '[company]', // \Magento\Sales\Model\Order\Address
            'fullName' => '[name]', // \Magento\Sales\Model\Order\Address
            'address1' => '[streetLine(1)]', // \Magento\Sales\Model\Order\Address
            'address2' => '[streetLine(2)]', // \Magento\Sales\Model\Order\Address
            'postalCode' => '[postcode]', // \Magento\Sales\Model\Order\Address
            'city' => '[city]', // \Magento\Sales\Model\Order\Address
            // Magento has 2 VAT numbers:
            // http://magento.stackexchange.com/questions/42164/there-are-2-vat-fields-in-onepage-checkout-which-one-should-i-be-using
            'vatNumber' => '[vatId|customerTaxvat]', // \Magento\Sales\Model\Order
            'telephone' => '[telephone]', // \Magento\Sales\Model\Order\Address
            'fax' => '[fax]', // \Magento\Sales\Model\Order\Address
            'email' => '[email]', // \Magento\Sales\Model\Order\Address

            // Invoice lines defaults.
            'itemNumber' => '[sku]',
            'productName' => '[name]',
        ];
    }

    public function getDefaultPropertyMappings(): array
    {
        return [
            DataType::Customer => [
                Fld::ContactYourId => '[source::getOrder()::getSource()::getCustomer()::getId()]', // Order, not Creditmemo
                // Magento has 2 VAT numbers:
                // http://magento.stackexchange.com/questions/42164/there-are-2-vat-fields-in-onepage-checkout-which-one-should-i-be-using
                // Magento\Customer\Model\Address also has a getVatId() method, but that is not the Address we have here.
                Fld::VatNumber => '[source::getOrder()::getSource()::getCustomerTaxvat())', // Order, not Creditmemo
                Fld::Telephone => '[source::getSource()::getBillingAddress()::getTelephone()]', // Address
                Fld::Telephone2 => '[source::getSource()::getShippingAddress()::getTelephone()]', // Address
                Fld::Fax => '[source::getSource()::getShippingAddress()::getFax()]', // Address
                // Email field of Address seems to be a copy of the Customer email field.
                Fld::Email => '[source::getSource()::getShippingAddress()::getEmail()]', // Address
            ],
            // Both Order and Creditmemo have get(Billing|Shipping)Address() methods.
            AddressType::Invoice => [
                Fld::CompanyName1 => '[source::getSource()::getBillingAddress()::getCompany()]', // Address
                Fld::FullName => '[source::getSource()::getBillingAddress()::getName()]', // Address
                Fld::Address1 => '[source::getSource()::getBillingAddress()::getStreetLine(1)]', // Address
                Fld::Address2 => '[source::getSource()::getBillingAddress()::getStreetLine(1)]', // Address
                Fld::PostalCode => '[source::getSource()::getBillingAddress()::getPostcode()]', // Address
                Fld::City => '[source::getSource()::getBillingAddress()::getCity()]', // Adress
                Fld::CountryCode => '[source::getSource()::getBillingAddress()::getCountryId()]', // Address
            ],
            AddressType::Shipping => [
                Fld::CompanyName1 => '[source::getSource()::getShippingAddress()::getCompany()]', // Address
                Fld::FullName => '[source::getSource()::getShippingAddress()::getName()]', // Address
                Fld::Address1 => '[source::getSource()::getShippingAddress()::getStreetLine(1)]', // Address
                Fld::Address2 => '[source::getSource()::getShippingAddress()::getStreetLine(1)]', // Address
                Fld::PostalCode => '[source::getSource()::getShippingAddress()::getPostcode()]', // Address
                Fld::City => '[source::getSource()::getShippingAddress()::getCity()]', // Adress
                Fld::CountryCode => '[source::getSource()::getShippingAddress()::getCountryId()]', // Address
            ],
            EmailAsPdfType::Invoice => [
                Fld::EmailTo => '[source::getSource()::getShippingAddress()::getEmail()]', // Address
            ],
            DataType::Invoice => [
                // @todo: fields that come from source, metadata
            ],
            LineType::Item => [
                // @todo: on which object?
                Fld::ItemNumber => '[sku]',
                Fld::Product => '[name]',
                // @todo: others? (e.g. quantity, unit price, metadata)
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
        // @todo: get active store/all stores
        /** @noinspection PhpParamsInspection   Wrong phpdoc */
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

    public function getLink(string $linkType): string
    {
        $registry = Registry::getInstance();
        /** @var UrlInterface $urlInterface */
        $urlInterface = $registry->get(UrlInterface::class);
        switch ($linkType) {
            case 'register':
            case 'activate':
            case 'config':
            case 'batch':
                return $urlInterface->getUrl("acumulus/$linkType");
            case 'advanced':
                return $urlInterface->getUrl('acumulus/config/advanced');
            case 'logo':
                $repository = Registry::getInstance()->get(AssetRepository::class);
                return $repository->getUrl('Siel_AcumulusMa2::images/siel-logo.svg');
            case 'pro-support-image':
                $repository = Registry::getInstance()->get(AssetRepository::class);
                return $repository->getUrl('Siel_AcumulusMa2::images/pro-support-magento.png');
            case 'pro-support-link':
                return 'https://pay.siel.nl/?p=GWFrqaYs68gnhRGP2UAHkXhvGQqWEDmP46DMuxa5BPXvpjmu';
        }
        return parent::getLink($linkType);
    }
}
