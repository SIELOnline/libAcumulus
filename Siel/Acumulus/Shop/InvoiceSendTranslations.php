<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for classes in the \Siel\Acumulus\Shop namespace:
 * - InvoiceManager
 */
class InvoiceSendTranslations extends TranslationCollection
{
    protected $nl = array(
        'message_invoice_send' => 'Factuur voor %1$s %2$s is %3$s (reden: %4$s).',
        'action_sent' => 'verzonden',
        'action_not_sent' => 'niet verzonden',
        'reason_sent_testMode' => 'test modus',
        'reason_sent_new' => 'nieuwe verzending',
        'reason_sent_forced' => 'geforceerd',
        'reason_not_sent_wrongStatus' => 'verkeerde status',
        'reason_not_sent_alreadySent' => 'is al eerder verzonden',
        'reason_not_sent_prevented_invoiceCreated' => 'verzenden tegengehouden door het event "AcumulusInvoiceCreated"',
        'reason_not_sent_prevented_invoiceCompleted' => 'verzenden tegengehouden door het event "AcumulusInvoiceCompleted"',
        'reason_not_sent_empty_invoice' => '0-bedrag factuur',
        'reason_not_sent_not_enabled_triggerInvoiceCreate' => 'optie "verzenden op aanmaken winkelfactuur" niet aangezet',
        'reason_not_sent_not_enabled_triggerInvoiceSent' => 'optie "verzenden op versturen winkelfactuur naar klant" niet aangezet',
        'reason_unknown' => 'onbekende reden: %s',
    );

    protected $en = array(
        'message_invoice_send' => 'Invoice for %1$s %2$s was %3$s (reason: %4$s).',
        'action_sent' => 'sent',
        'action_not_sent' => 'not sent',
        'reason_sent_testMode' => 'test mode',
        'reason_sent_new' => 'not yet sent',
        'reason_sent_forced' => 'forced',
        'reason_not_sent_wrongStatus' => 'wrong status',
        'reason_not_sent_alreadySent' => 'has already been sent',
        'reason_not_sent_prevented_invoiceCreated' => 'sending prevented by event "AcumulusInvoiceCreated"',
        'reason_not_sent_prevented_invoiceCompleted' => 'sending prevented by event "AcumulusInvoiceCompleted"',
        'reason_not_sent_empty_invoice' => '0 amount invoice',
        'reason_not_sent_not_enabled_triggerInvoiceCreate' => 'option "send on creation of shop invoice" not enabled',
        'reason_not_sent_not_enabled_triggerInvoiceSent' => 'option "send on sending of shop invoice to customer" not enabled',
        'reason_unknown' => 'unknown reason: %s',
    );
}
