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
        'message_sent' => 'verzonden',
        'message_sent_testMode' => 'test modus',
        'message_sent_new' => 'nieuwe verzending',
        'message_sent_forced' => 'geforceerd',
        'message_not_sent' => 'niet verzonden',
        'message_not_sent_wrongStatus' => 'verkeerde status',
        'message_not_sent_alreadySent' => 'is al eerder verzonden',
        'message_not_sent_prevented_invoiceCreated' => 'verzenden tegengehouden door het event "AcumulusInvoiceCreated"',
        'message_not_sent_prevented_invoiceCompleted' => 'verzenden tegengehouden door het event "AcumulusInvoiceCompleted"',
        'message_not_sent_not_enabled_triggerInvoiceCreate' => 'optie "verzenden op aanmaken winkelfactuur" niet aangezet',
        'message_not_sent_not_enabled_triggerInvoiceSent' => 'optie "verzenden op versturen winkelfactuur naar klant" niet aangezet',
        'message_reason_unknown' => 'onbekende reden: %s',
    );

    protected $en = array(
      'message_invoice_send' => 'Invoice for %1$s %2$s was %3$s (reason: %4$s).',
      'message_sent' => 'sent',
      'message_sent_testMode' => 'test mode',
      'message_sent_new' => 'not yet sent',
      'message_sent_forced' => 'forced',
      'message_not_sent' => 'not sent',
      'message_not_sent_wrongStatus' => 'wrong status',
      'message_not_sent_alreadySent' => 'has already been sent',
      'message_not_sent_prevented_invoiceCreated' => 'sending prevented by event "AcumulusInvoiceCreated"',
      'message_not_sent_prevented_invoiceCompleted' => 'sending prevented by event "AcumulusInvoiceCompleted"',
      'message_not_sent_not_enabled_triggerInvoiceCreate' => 'option "send on creation of shop invoice" not enabled',
      'message_not_sent_not_enabled_triggerInvoiceSent' => 'option "send on sending of shop invoice to customer" not enabled',
      'message_reason_unknown' => 'unknown reason: %s',
    );
}
