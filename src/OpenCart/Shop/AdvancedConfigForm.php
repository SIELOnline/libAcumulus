<?php
namespace Siel\Acumulus\OpenCart\Shop;

use Siel\Acumulus\OpenCart\Helpers\Registry;
use Siel\Acumulus\Shop\AdvancedConfigForm as BaseAdvancedConfigForm;

/**
 * Class ConfigForm processes and builds the settings form page for the
 * OpenCart Acumulus module.
 */
class AdvancedConfigForm extends BaseAdvancedConfigForm
{
    /**
     * {@inheritdoc}
     */
    public function isSubmitted()
    {
        return $this->getRequest()->server['REQUEST_METHOD'] == 'POST';
    }

    /**
     * return \Request
     */
    private function getRequest()
    {
        return Registry::getInstance()->request;
    }
}
