<?php
namespace Siel\Acumulus\OpenCart\OpenCart2\Shop;

use Siel\Acumulus\OpenCart\Helpers\Registry;
use Siel\Acumulus\OpenCart\Shop\ShopCapabilities as ShopCapabilitiesBase;

/**
 * Defines the OpenCart 2 specific capabilities that differ from OpenCart 1.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     */
    public function getPaymentMethods()
    {
        Registry::getInstance()->load->model('extension/extension');
        $extensions = Registry::getInstance()->model_extension_extension->getInstalled('payment');
        $extensions = array_filter($extensions, function($extension) {
            return (bool) Registry::getInstance()->config->get($extension . '_status');
        });
        return $this->paymentMethodToOptions($extensions);
    }
}
