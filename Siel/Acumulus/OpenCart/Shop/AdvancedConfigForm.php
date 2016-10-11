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

    /**
     * Turns the list into a translated list of options for a select.
     *
     * @param array $extensions
     *
     * @return array
     *   an array with the extensions as key and their translated name as value.
     */
    protected function paymentMethodToOptions(array $extensions)
    {
        $results = array();
        foreach ($extensions as $extension) {
            Registry::getInstance()->language->load('payment/' . $extension);
            $results[$extension] = Registry::getInstance()->language->get('heading_title');
        }
        return $results;
    }
}
