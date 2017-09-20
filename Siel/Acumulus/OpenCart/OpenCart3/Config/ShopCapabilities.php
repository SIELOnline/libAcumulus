<?php

namespace Siel\Acumulus\OpenCart\OpenCart3\Config;

use Siel\Acumulus\OpenCart\Config\ShopCapabilities as ShopCapabilitiesBase;
use Siel\Acumulus\OpenCart\Helpers\Registry;

/**
 * Defines the OpenCart 3 specific capabilities that differ from OpenCart 1 (and
 * 2).
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     */
    public function getPaymentMethods()
    {
        $type = 'payment';
        Registry::getInstance()->load->model('setting/extension');
        $extensions = Registry::getInstance()->model_setting_extension->getInstalled($type);
        $extensions = array_filter($extensions, function ($extension) use ($type) {
            return (bool)Registry::getInstance()->config->get($type . '_' . $extension . '_status');
        });
        return $this->paymentMethodToOptions($extensions);
    }

    /**
     * {@inheritdoc}
     */
    protected function paymentMethodToOptions(array $extensions)
    {
        $results = [];
        foreach ($extensions as $extension) {
            Registry::getInstance()->language->load('extension/payment/' . $extension);
            $results[$extension] = Registry::getInstance()->language->get('heading_title');
        }
        return $results;
    }
}
