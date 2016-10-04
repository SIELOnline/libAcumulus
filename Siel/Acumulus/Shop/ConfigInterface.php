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
    // Invoice send handling related constants.
    const Invoice_NotSent = 0x8;
    const Invoice_NotSent_EventInvoiceCreated = 0x18;
    const Invoice_NotSent_EventInvoiceCompleted = 0x28;
    const Invoice_NotSent_AlreadySent = 0x38;
    const Invoice_NotSent_WrongStatus = 0x48;
    const Invoice_NotSent_TriggerInvoiceCreateNotEnabled = 0x58;
    const Invoice_NotSent_TriggerInvoiceSentNotEnabled = 0x68;
    const Invoice_NotSent_Mask = 0x78;
    const Invoice_Sent_New = 0x80;
    const Invoice_Sent_Forced = 0x100;
    const Invoice_Sent_TestMode = 0x180;
    const Invoice_Sent_Mask = 0x180;

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
