<?php
namespace Siel\Acumulus\Magento\Config;

use Exception;
use Magento\Framework\App\ObjectManager;
use Siel\Acumulus\Config\ShopCapabilities as ShopCapabilitiesBase;
use Siel\Acumulus\Magento\Helpers\Registry;
use Siel\Acumulus\Config\Config;

/**
 * Defines the Magento 2 web shop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    private $order = [
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
    private $creditMemo = [
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

    /**
     * {@inheritdoc}
     */
    public function getShopEnvironment()
    {
        /** @var \Magento\Framework\App\ProductMetadataInterface $productMetadata */
        $productMetadata = Registry::getInstance()->get('Magento\Framework\App\ProductMetadataInterface');
        try {
            $version = $productMetadata->getVersion();
        } catch (Exception $e) {
            // In CLI mode (php bin/magento ...) getVersion() throws an
            // exception.
            $version = 'UNKNOWN';
        }

        return [
            'moduleVersion' => Registry::getInstance()->getModuleVersion('Siel_AcumulusMa2'),
            'schemaVersion' => Registry::getInstance()->getSchemaVersion('Siel_AcumulusMa2'),
            'shopName' => $this->shopName,
            'shopVersion' => $version,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenInfoSource()
    {
        return [
            'class' => ['\Magento\Sales\Model\Order', '\Magento\Sales\Model\Order\CreditMemo'],
            'file' => ['vendor/magento/module-sales/Model/Order.php', 'vendor/magento/module-sales/Model/Order/Creditmemo.php'],
            'properties' => array_intersect($this->order, $this->creditMemo),
            'properties-more' => true,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenInfoRefund()
    {
        return [
            'class' => 'Mage_Sales_Model_Order_CreditMemo',
            'file' => 'app/code/core/Mage/Sales/Model/Order/Creditmemo.php',
            'properties' => array_diff($this->creditMemo, $this->order),
            'properties-more' => true,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenInfoOrder()
    {
        return [
            'class' => '\Magento\Sales\Model\Order\Order',
            'file' => 'vendor/magento/module-sales/Model/Order/Order.php',
            'properties' => array_diff($this->order, $this->creditMemo),
            'properties-more' => true,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenInfoShopProperties()
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
                'class' => '\Magento\Sales\Model\Order\Address',
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
                'class' => '\Magento\Sales\Model\Order\Address',
                'file' => 'vendor/magento/module-sales/Model/Order/Address.php',
                'properties' => [
                    $this->t('see_above'),
                ],
                'properties-more' => false,
            ],
            'customer' => [
                'class' => '\Magento\Customer\Model\Customer',
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
                'class' => ['\Magento\Sales\Model\Order\Item', '\Magento\Sales\Model\Order\Creditmemo\Item'],
                'file' => ['vendor/magento/module-sales/Model/Order/Item.php', 'vendor/magento/module-sales/Model/Order/Creditmemo/Item.php'],
                'properties' => array_unique(array_merge($orderItem, $creditMemoItem)),
                'properties-more' => true,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getShopDefaults()
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

    /**
     * {@inheritdoc}
     */
    public function getShopOrderStatuses()
    {
        /** @var \Magento\Sales\Model\Order\Status $model */
        $model = Registry::getInstance()->create('Magento\Sales\Model\Order\Status');
        /** @noinspection PhpUnhandledExceptionInspection */
        $items = $model->getResourceCollection()->getData();
        $result = [];
        foreach ($items as $item) {
            $result[reset($item)] = next($item);
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getTriggerInvoiceEventOptions()
    {
        $result = parent::getTriggerInvoiceEventOptions();
        $result[Config::TriggerInvoiceEvent_Create] = $this->t('option_triggerInvoiceEvent_1');
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethods()
    {
        $result = [];
        /** @var \Magento\Payment\Helper\Data $paymentHelper */
        $paymentHelper = Registry::getInstance()->get('Magento\Payment\Helper\Data');
        $paymentMethods = $paymentHelper->getPaymentMethods();
        foreach ($paymentMethods as $code => $paymentMethodData) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $instance = $paymentHelper->getMethodInstance($code);
            if ($instance->isActive()) {
                $title = $instance->getConfigData('title');
                if (empty($title)) {
                    $title = $code;
                }
                $result[$code] = $title;
            }
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getVatClasses()
    {
        $result = [];
        /** @var \Magento\Tax\Model\ClassModel $taxClass */
        /** @noinspection PhpFullyQualifiedNameUsageInspection */
        $taxClass = ObjectManager::getInstance()->create(\Magento\Tax\Model\ClassModel::class);
        foreach ($taxClass->getCollection() as $item) {
            $result[$item->getData('class_id')] = $item->getData('class_name');
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getLink($linkType)
    {
        $registry = Registry::getInstance();
        switch ($linkType) {
            case 'register':
                return $registry->getUrlInterface()->getUrl('acumulus/register');
            case 'config':
                return $registry->getUrlInterface()->getUrl('acumulus/config');
            case 'advanced':
                return $registry->getUrlInterface()->getUrl('acumulus/config/advanced');
            case 'batch':
                return $registry->getUrlInterface()->getUrl('acumulus/batch');
            case 'logo':
                /** @noinspection PhpFullyQualifiedNameUsageInspection */
                $repository = ObjectManager::getInstance()->get(\Magento\Framework\View\Asset\Repository::class);
                return $repository->getUrl('Siel_AcumulusMa2::images/siel-logo.svg');
        }
        return parent::getLink($linkType);
    }
}
