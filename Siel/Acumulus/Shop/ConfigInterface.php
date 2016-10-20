<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Invoice\ConfigInterface as InvoiceConfigInterface;
use Siel\Acumulus\Web\ConfigInterface as WebConfigInterface;

/**
 * Defines an interface to retrieve shop specific configuration settings.
 *
 * Configuration is stored in the host environment, normally a web shop.
 * This interface abstracts from how a specific web shop does so.
 */
interface ConfigInterface extends InvoiceConfigInterface, WebConfigInterface, InjectorInterface
{
    // Invoice send handling related constants. These can be combined with a
    // send Status_... const (bits 1 to 3).
    // Not sent: bit 4 always set.
    const Invoice_NotSent = 0x8;
    // Reason for not sending: bits 5 to 7.
    const Invoice_NotSent_EventInvoiceCreated = 0x18;
    const Invoice_NotSent_EventInvoiceCompleted = 0x28;
    const Invoice_NotSent_AlreadySent = 0x38;
    const Invoice_NotSent_WrongStatus = 0x48;
    const Invoice_NotSent_EmptyInvoice = 0x58;
    const Invoice_NotSent_TriggerInvoiceCreateNotEnabled = 0x68;
    const Invoice_NotSent_TriggerInvoiceSentNotEnabled = 0x78;
    const Invoice_NotSent_Mask = 0x78;
    // Reason for sending: bits 8 and 9
    const Invoice_Sent_New = 0x80;
    const Invoice_Sent_Forced = 0x100;
    const Invoice_Sent_TestMode = 0x180;
    const Invoice_Sent_Mask = 0x180;

    // Web shop configuration related constants.
    const TriggerInvoiceEvent_None = 0;
    const TriggerInvoiceEvent_Create = 1;
    const TriggerInvoiceEvent_Send = 2;

    /**
     * Saves the configuration to the actual configuration provider.
     *
     * @param array $values
     *   A keyed array that contains the values to store, this may be a subset
     *   of the possible keys.
     *
     * @return bool
     *   Success.
     */
    public function save(array $values);

    /**
     * Returns a list of keys that are stored in the shop specific config store.
     *
     * @return array
     */
    public function getKeys();

    /**
     * Returns the set of settings related to reacting to shop events.
     *
     * @return array
     *   A keyed array with the keys:
     *   - triggerOrderStatus
     *   - triggerInvoiceEvent
     *   - sendEmptyInvoice
     */
    public function getShopEventSettings();
}
