<?php
namespace Siel\Acumulus\Shop;

/**
 * Defines an interface to retrieve shop specific configuration settings.
 *
 * Configuration is stored in the host environment, normally a web shop.
 * This interface abstracts from how a specific web shop does so.
 */
interface ConfigInterface
{
    // Web shop configuration related constants.
    const TriggerInvoiceSendEvent_None = 0;
    const TriggerInvoiceSendEvent_OrderStatus = 1;
    const TriggerInvoiceSendEvent_InvoiceCreate = 2;

    /**
     * Returns the set of settings related to reacting to shop events.
     *
     * @return array
     *   A keyed array with the keys:
     *   - triggerInvoiceSendEvent
     *   - triggerOrderStatus
     */
    public function getShopEventSettings();
}
