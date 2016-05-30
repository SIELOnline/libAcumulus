<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use Context;
use Module;
use OrderState;
use PaymentModule;
use Siel\Acumulus\Shop\ConfigInterface;
use Siel\Acumulus\Shop\ShopCapabilities as ShopCapabilitiesBase;

/**
 * Defines the PrestaShop webshop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     */
    public function getShopOrderStatuses()
    {
        $states = OrderState::getOrderStates((int) Context::getContext()->language->id);
        $result = array();
        foreach ($states as $state) {
            $result[$state['id_order_state']] = $state['name'];
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override removes the 'Use invoice sent' option as PS does not have
     * an event on creating/sending the invoice.
     *
     * @todo: PS has the 'actionSetInvoice' event, can we use that?
     * This event fires when the order state changes to a state that allows an
     * invoice and on manually creating one via the adminOrdersController page.
     */
    public function getTriggerInvoiceSendEventOptions()
    {
        $result = parent::getTriggerInvoiceSendEventOptions();
        unset($result[ConfigInterface::TriggerInvoiceSendEvent_InvoiceCreate]);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethods()
    {
        $paymentModules = PaymentModule::getInstalledPaymentModules();
        $result = array();
        foreach($paymentModules as $paymentModule)
        {
            $module = Module::getInstanceById($paymentModule['id_module']);
            $result[$module->name] = $module->displayName;
        }
        return $result;
    }
}
