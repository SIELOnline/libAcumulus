<?php
namespace Siel\Acumulus\OpenCart\OpenCart1\Config;

use Siel\Acumulus\OpenCart\Config\ShopCapabilities as ShopCapabilitiesBase;
use Siel\Acumulus\OpenCart\Helpers\Registry;

/**
 * Defines the OpenCart 1 specific capabilities that differ from OpenCart 2.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     */
    public function getPaymentMethods()
    {
        Registry::getInstance()->load->model('setting/extension');
        $extensions = Registry::getInstance()->model_setting_extension->getInstalled('payment');

        return $this->paymentMethodToOptions($extensions);
    }
}
