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
    public function getShopDefaults()
    {
        return array(
            'contactYourId' => '[customer::incrementId|customer::id]', // \Mage\Customer\Model\Customer
            'companyName1' => '[company]', // \Magento\Sales\Model\Order\Address
            'fullName' => '[name]', // \Magento\Sales\Model\Order\Address
            'address1' => '[implodeStreetValue]', // \Magento\Sales\Model\Order\Address
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
