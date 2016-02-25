<?php
namespace Siel\Acumulus\Joomla\VirtueMart\Shop;

use JText;
use Siel\Acumulus\Joomla\Shop\ConfigForm as BaseConfigForm;
use Siel\Acumulus\Shop\ConfigInterface;
use VirtueMartModelOrderstatus;
use VmModel;

/**
 * Class ConfigForm processes and builds the settings form page for the
 * VirtueMart Acumulus module.
 */
class ConfigForm extends BaseConfigForm
{
    /**
     * {@inheritdoc}
     */
    protected function getShopOrderStatuses()
    {
        /** @var VirtueMartModelOrderstatus $orderStatusModel */
        $orderStatusModel = VmModel::getModel('orderstatus');
        /** @var array[] $orderStates Method getOrderStatusNames() has an incorrect @return type ... */
        $orderStates = $orderStatusModel->getOrderStatusNames();
        foreach ($orderStates as $code => &$value) {
            $value = \JText::_($value['order_status_name']);
        }
        return $orderStates;
    }

    /**
     * {@inheritdoc}
     */
    protected function getTriggerInvoiceSendEventOptions()
    {
        $result = parent::getTriggerInvoiceSendEventOptions();
        // @todo: find out if there's something like an invoice create event.
        unset($result[ConfigInterface::TriggerInvoiceSendEvent_InvoiceCreate]);
        return $result;
    }
}
