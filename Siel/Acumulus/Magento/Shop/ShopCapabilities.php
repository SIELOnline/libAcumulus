<?php
namespace Siel\Acumulus\Magento\Shop;

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
    public function getShopDefaults()
    {
        return array(
            'contactYourId' => '[customer::incrementId|customer::id]', // Mage_Customer_Model_Customer
            'companyName1' => '[company]', // Mage_Sales_Model_Order_Address
            'fullName' => '[firstName+lastName]', // Mage_Sales_Model_Order_Address
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
