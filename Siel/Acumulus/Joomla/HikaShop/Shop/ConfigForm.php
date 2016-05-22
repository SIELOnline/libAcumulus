<?php
namespace Siel\Acumulus\Joomla\HikaShop\Shop;

use Siel\Acumulus\Joomla\Shop\ConfigForm as BaseConfigForm;
use Siel\Acumulus\Shop\ConfigInterface;

/**
 * Class ConfigForm processes and builds the settings form page for the
 * HikaShop Acumulus module.
 */
class ConfigForm extends BaseConfigForm
{
    /**
     * {@inheritdoc}
     */
    protected function getShopOrderStatuses()
    {
        /** @var \hikashopCategoryClass $class */
        $class = hikashop_get('class.category');
        $statuses = $class->loadAllWithTrans('status');

        $orderStatuses = array();
        foreach ($statuses as $state) {
            $orderStatuses[$state->category_name] = $state->translation;
        }
        return $orderStatuses;
    }

    /**
     * {@inheritdoc}
     */
    protected function getTriggerInvoiceSendEventOptions()
    {
        $result = parent::getTriggerInvoiceSendEventOptions();
        // HikaShop does not have separate invoice entities, let alone a create
        // event for that.
        unset($result[ConfigInterface::TriggerInvoiceSendEvent_InvoiceCreate]);
        return $result;
    }

    protected function getPaymentMethods()
    {
        $result = array();
        /** @var \hikashopPluginsClass $pluginClass */
        $pluginClass = hikashop_get('class.plugins');
        $paymentPlugins = $pluginClass->getMethods('payment');
        foreach ($paymentPlugins as $paymentPlugin) {
            if (!empty($paymentPlugin->enabled)) {
                $result[$paymentPlugin->payment_type] = $paymentPlugin->payment_name;
            }
        }
        return $result;
    }
}
