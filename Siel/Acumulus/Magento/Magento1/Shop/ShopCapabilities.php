<?php
namespace Siel\Acumulus\Magento\Magento1\Shop;

use Mage;
use Siel\Acumulus\Shop\ConfigInterface;
use Siel\Acumulus\Shop\ShopCapabilities as ShopCapabilitiesBase;

/**
 * Defines the Magento 1 webshop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     */
    public function getTokenInfo()
    {
        $order = array(
            'state',
            'status',
            'couponCode',
            'protectCode',
            'shippingDescription',
            'isVirtual',
            'storeId',
            'customerId',
            'baseDiscountAmount',
            'baseDiscountCanceled',
            'baseDiscountInvoiced',
            'baseDiscountRefunded',
            'baseGrandTotal',
            'baseShippingAmount',
            'baseShippingCanceled',
            'baseShippingInvoiced',
            'baseShippingRefunded',
            'baseShippingTaxAmount',
            'baseShippingTaxRefunded',
            'baseSubtotal',
            'baseSubtotalCanceled',
            'baseSubtotalInvoiced',
            'baseSubtotalRefunded',
            'discountAmount',
            'discountCanceled',
            'discountInvoiced',
            'discountRefunded',
            'grandTotal',
            'shippingAmount',
            'shippingCanceled',
            'shippingInvoiced',
            'shippingRefunded',
            'shippingTaxAmount',
            'shippingTaxRefunded',
            'storeToBaseRate',
            'storeToOrderRate',
            'subtotal',
            'subtotalCanceled',
            'subtotalInvoiced',
            'subtotalRefunded',
            'taxAmount',
            'taxCanceled',
            'taxInvoiced',
            'taxRefunded',
            'totalCanceled',
            'totalInvoiced',
            'totalOfflineRefunded',
            'totalOnlineRefunded',
            'totalPaid',
            'totalQtyOrdered',
            'totalRefunded',
            'canShipPartially',
            'canShipPartiallyItem',
            'customerIsGuest',
            'customerNoteNotify',
            'billingAddressId',
            'customerGroupId',
            'editIncrement',
            'emailSent',
            'forcedDoShipmentWithInvoice',
            'giftMessageId',
            'paymentAuthorizationExpiration',
            'paypalIpnCustomerNotified',
            'quoteAddressId',
            'quoteId',
            'shippingAddressId',
            'adjustmentNegative',
            'adjustmentPositive',
            'baseAdjustmentNegative',
            'baseAdjustmentPositive',
            'baseShippingDiscountAmount',
            'baseSubtotalInclTax',
            'paymentAuthorizationAmount',
            'shippingDiscountAmount',
            'subtotalInclTax',
            'weight',
            'customerDob',
            'incrementId',
            'appliedRuleIds',
            'baseCurrencyCode',
            'customerEmail',
            'customerFirstname',
            'customerMiddlename',
            'customerLastname',
            'customerPrefix',
            'customerSuffix',
            'customerTaxvat',
            'discountDescription',
            'extCustomerId',
            'extOrderId',
            'globalCurrencyCode',
            'holdBeforeState',
            'holdBeforeStatus',
            'orderCurrencyCode',
            'originalIncrementId',
            'relationChildId',
            'relationChildRealId',
            'relationParentId',
            'relationParentRealId',
            'remoteIp',
            'storeCurrencyCode',
            'storeName',
            'xForwardedFor',
            'customerNote',
            'createdAt',
            'updatedAt',
            'totalItemCount',
            'customerGender',
            'hiddenTaxAmount',
            'baseHiddenTaxAmount',
            'shippingHiddenTaxAmount',
            'baseShippingHiddenTaxAmount',
            'hiddenTaxInvoiced',
            'baseHiddenTaxInvoiced',
            'hiddenTaxRefunded',
            'baseHiddenTaxRefunded',
            'shippingInclTax',
            'baseShippingInclTax',
        );
        $creditMemo = array(
            'resource',
            'storeId',
            'adjustmentPositive',
            'baseShippingTaxAmount',
            'storeToOrderRate',
            'baseDiscountAmount',
            'grandTotal',
            'baseAdjustmentNegative',
            'baseSubtotalInclTax',
            'shippingAmount',
            'subtotalInclTax',
            'adjustmentNegative',
            'baseShippingAmount',
            'storeToBaseRate',
            'baseAdjustment',
            'baseSubtotal',
            'discountAmount',
            'subtotal',
            'adjustment',
            'baseGrandTotal',
            'baseAdjustmentPositive',
            'shippingTaxAmount',
            'taxAmount',
            'orderId',
            'emailSent',
            'creditmemoStatus',
            'state',
            'shippingAddressId',
            'billingAddressId',
            'invoiceId',
            'cybersourceToken',
            'storeCurrencyCode',
            'orderCurrencyCode',
            'baseCurrencyCode',
            'globalCurrencyCode',
            'transactionId',
            'incrementId',
            'createdAt',
            'updatedAt',
            'hiddenTaxAmount',
            'baseHiddenTaxAmount',
            'shippingHiddenTaxAmount',
            'baseShippingHiddenTaxAmount',
            'shippingInclTax',
            'baseShippingInclTax',
        );
        return array(
            'source' => array(
                'class' => array('Mage_Sales_Model_Order', 'Mage_Sales_Model_Order_CreditMemo'),
                'file' => array('app/code/core/Mage/Sales/Model/Order.php', 'app/code/core/Mage/Sales/Model/Order/Creditmemo.php'),
                'properties' => array_unique(array_merge($order, $creditMemo)),
                'properties-more' => true,
            ),
            'billingAaddress' => array(
                'class' => 'Mage_Sales_Model_Order_Address',
                'file' => 'app/code/core/Mage/Sales/Model/Order/Address.php',
                'properties' => array(
                    'entityId',
                    'parentId',
                    'customerAddressId',
                    'quoteAddressId',
                    'regionId',
                    'customerId',
                    'fax',
                    'region',
                    'postcode',
                    'lastname',
                    'street',
                    'city',
                    'email',
                    'telephone',
                    'countryId',
                    'firstname',
                    'addressType',
                    'prefix',
                    'middlename',
                    'suffix',
                    'company',
                    'vatId',
                    'vatIsValid',
                    'vatRequestId',
                    'vatRequestDate',
                    'vatRequestSuccess',
                    'giftregistryItemId',
                ),
                'properties-more' => true,
            ),
            'shippingAddress' => array(
                'more-info' => $this->t('see_billing_address'),
                'class' => 'Mage_Sales_Model_Order_Address',
                'file' => 'app/code/core/Mage/Sales/Model/Order/Address.php',
                'properties' => array(
                    $this->t('see_above'),
                ),
                'properties-more' => false,
            ),
            'customer' => array(
                'class' => 'Mage_Customer_Model_Customer',
                'file' => 'app/code/core/Mage/Customer/Model/Customer.php',
                'properties' => array(
                    'entityIid',
                    'websiteId',
                    'email',
                    'groupId',
                    'incrementId',
                    'storeId',
                    'createdAt',
                    'updatedAt',
                    'isActive',
                    'disableAutoGroupChange',
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
            'contactYourId' => '[customer::incrementId|customer::id]', // Mage_Customer_Model_Customer
            'companyName1' => '[company]', // Mage_Sales_Model_Order_Address
            'fullName' => '[firstname+lastname]', // Mage_Sales_Model_Order_Address
            'address1' => '[street1]', // Mage_Sales_Model_Order_Address
            'address2' => '[street2]', // Mage_Sales_Model_Order_Address
            'postalCode' => '[postcode]', // Mage_Sales_Model_Order_Address
            'city' => '[city]', // Mage_Sales_Model_Order_Address
            // Magento has 2 VAT numbers:
            // http://magento.stackexchange.com/questions/42164/there-are-2-vat-fields-in-onepage-checkout-which-one-should-i-be-using
            'vatNumber' => '[vatId|customerTaxvat]', // Mage_Sales_Model_Order
            'telephone' => '[telephone]', // Mage_Sales_Model_Order_Address
            'fax' => '[fax]', // Mage_Sales_Model_Order_Address
            'email' => '[email]', // Mage_Sales_Model_Order_Address
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getShopOrderStatuses()
    {
        $items = Mage::getModel('sales/order_status')->getResourceCollection()->getData();
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
        /** @var \Mage_Payment_Helper_Data $paymentHelper */
        $paymentHelper = Mage::helper("payment");
        $paymentMethods = $paymentHelper->getPaymentMethods();
        foreach ($paymentMethods as $code => $paymentMethodData) {
            if (!empty($paymentMethodData['active'])) {
                if ((isset($data['title']))) {
                    $title = $data['title'];
                } elseif ($paymentHelper->getMethodInstance($code)) {
                    $title = $paymentHelper->getMethodInstance($code)->getConfigData('title');
                }
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
        /** @var \Mage_Adminhtml_helper_data $helper */
        $helper = Mage::helper('adminhtml');
        switch ($formType) {
            case 'config':
                return $helper->getUrl('adminhtml/acumulus/config');
            case 'advanced':
                return $helper->getUrl('adminhtml/acumulus/advanced');
            case 'batch':
                return $helper->getUrl('adminhtml/acumulus/batch');
        }
        return parent::getLink($formType);
    }
}
