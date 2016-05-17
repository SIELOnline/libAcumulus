<?php
namespace Siel\Acumulus\OpenCart\OpenCart2\Shop;

use Siel\Acumulus\OpenCart\Helpers\Registry;
use Siel\Acumulus\OpenCart\Shop\ConfigForm as BaseConfigForm;

/**
 * Class ConfigForm processes and builds the settings form page for the
 * PrestaShop Acumulus module.
 */
class ConfigForm extends BaseConfigForm
{
    /**
     * {@inheritdoc}
     */
    protected function getPaymentMethods()
    {
        Registry::getInstance()->load->model('extension/extension');
        $extensions = Registry::getInstance()->model_extension_extension->getInstalled('payment');
        $extensions = array_filter($extensions, function($extension) {
            return (bool) Registry::getInstance()->config->get($extension . '_status');
        });

        return $this->paymentMethodToOptions($extensions);
    }
}
