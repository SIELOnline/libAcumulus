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
    const TriggerInvoiceEvent_None = 0;
    const TriggerInvoiceEvent_Create = 1;
    const TriggerInvoiceEvent_Send = 2;

    /**
     * Returns the set of settings related to reacting to shop events.
     *
     * @return array
     *   A keyed array with the keys:
     *   - triggerOrderStatus
     *   - triggerInvoiceEvent
     */
    public function getShopEventSettings();
}
