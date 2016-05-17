<?php
namespace Siel\Acumulus\OpenCart\OpenCart1\Shop;

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
        Registry::getInstance()->load->model('setting/extension');
        $extensions = Registry::getInstance()->model_setting_extension->getInstalled('payment');

        return $this->paymentMethodToOptions($extensions);
    }
}
