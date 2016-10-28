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
