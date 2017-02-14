<?php
namespace Siel\Acumulus\Magento2\Shop;

use Siel\Acumulus\Magento2\Helpers\Registry;
use Siel\Acumulus\Shop\ConfigInterface;
use Siel\Acumulus\Shop\ShopCapabilities as ShopCapabilitiesBase;

/**
 * Defines the Magento 2 webshop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     */
    public function getTokenInfo()
    {
        $order = array(
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
        );
        $creditMemo = array(
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
        );
        return array(
            'source' => array(
                'class' => array('\Magento\Sales\Model\Order', '\Magento\Sales\Model\Order\CreditMemo'),
                'file' => array('vendor/magento/module-sales/Model/Order.php', 'vendor/magento/module-sales/Model/Order/Creditmemo.php'),
                'properties' => array_unique(array_merge($order, $creditMemo)),
                'properties-more' => true,
            ),
            'billingAaddress' => array(
                'class' => '\Magento\Sales\Model\Order\Address',
                'file' => 'vendor/magento/module-sales/Model/Order/Address.php',
                'properties' => array(
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
                ),
                'properties-more' => true,
            ),
            'shippingAddress' => array(
                'more-info' => $this->t('see_billing_address'),
                'class' => '\Magento\Sales\Model\Order\Address',
                'file' => 'vendor/magento/module-sales/Model/Order/Address.php',
                'properties' => array(
                    $this->t('see_above'),
                ),
                'properties-more' => false,
            ),
            'customer' => array(
                'class' => '\Magento\Customer\Model\Customer',
                'file' => 'vendor/magento/module-customer/Model/Customer.php',
                'properties' => array(
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
                ),
                'properties-more' => true,
            ),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getShopDefaults()
    {
        return array(
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
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getShopOrderStatuses()
    {
        /** @var \Magento\Sales\Model\Order\Status $model */
        $model = Registry::getInstance()->create('Magento\Sales\Model\Order\Status');
        $items = $model->getResourceCollection()->getData();
        $result = array();
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
        $result[ConfigInterface::TriggerInvoiceEvent_Create] = $this->t('option_triggerInvoiceEvent_1');
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethods()
    {
        $result = array();
        /** @var \Magento\Payment\Helper\Data $paymentHelper */
        $paymentHelper = Registry::getInstance()->get('Magento\Payment\Helper\Data');
        $paymentMethods = $paymentHelper->getPaymentMethods();
        foreach ($paymentMethods as $code => $paymentMethodData) {
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

    public function getLink($formType)
    {
        $registry = Registry::getInstance();
        switch ($formType) {
            case 'config':
                return $registry->getUrlInterface()->getUrl('acumulus/config');
            case 'advanced':
                return $registry->getUrlInterface()->getUrl('acumulus/config/advanced');
            case 'batch':
                return $registry->getUrlInterface()->getUrl('acumulus/batch');
        }
        return parent::getLink($formType);
    }
}
