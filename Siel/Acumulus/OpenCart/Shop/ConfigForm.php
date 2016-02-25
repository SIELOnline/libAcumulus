<?php
namespace Siel\Acumulus\OpenCart\Shop;

use Siel\Acumulus\OpenCart\Helpers\Registry;
use Siel\Acumulus\Shop\ConfigForm as BaseConfigForm;
use Siel\Acumulus\Shop\ConfigInterface;

/**
 * Class ConfigForm processes and builds the settings form page for the
 * PrestaShop Acumulus module.
 */
class ConfigForm extends BaseConfigForm
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
     * {@inheritdoc}
     */
    protected function getShopOrderStatuses()
    {
        Registry::getInstance()->load->model('localisation/order_status');
        $states = Registry::getInstance()->model_localisation_order_status->getOrderStatuses();
        $result = array();
        foreach ($states as $state) {
            list($optionValue, $optionText) = array_values($state);
            $result[$optionValue] = $optionText;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override removes the 'Use invoice sent' option as OpenCart does not
     * have an event on creating/sending the invoice.
     *
     * @todo: What does OC have?
     */
    protected function getTriggerInvoiceSendEventOptions()
    {
        $result = parent::getTriggerInvoiceSendEventOptions();
        unset($result[ConfigInterface::TriggerInvoiceSendEvent_InvoiceCreate]);
        return $result;
    }
}
