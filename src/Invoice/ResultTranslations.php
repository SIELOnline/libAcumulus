<?php

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for invoice send result logging.
 *
 * @noinspection PhpUnused Loaded by the Container
 */
class ResultTranslations extends TranslationCollection
{
    protected $nl = [
        'message_invoice_send' => '%1$s: %2$s is %3$s',
        'message_invoice_source' => 'Factuur voor %1$s %2$s',
        'message_invoice_reason' => '%1$s (reden: %2$s)',
        'action_unknown' => 'nog niet bekend',
        'action_sent' => 'verzonden',
        'action_not_sent' => 'niet verzonden',
        'reason_sent_testMode' => 'test modus',
        'reason_sent_new' => 'nieuwe verzending',
        'reason_sent_new_status_change' => 'nieuwe verzending en %1$s in [%2$s]',
        'reason_sent_lock_expired' => 'nieuwe verzending omdat de vorige poging is mislukt',
        'reason_sent_forced' => 'geforceerd',
        'reason_not_sent_wrongStatus' => 'verkeerde status: %1$s niet in [%2$s]',
        'reason_not_sent_triggerCreditNoteEvent_None' => 'optie om creditfactuur automatisch te verzenden niet aangezet',
        'reason_not_sent_alreadySent' => 'is al eerder verzonden',
        'reason_not_sent_alreadySending' => 'andere verzending aan de gang',
        'reason_not_sent_prevented_invoiceCreated' => 'verzenden tegengehouden door het event "AcumulusInvoiceCreated"',
        'reason_not_sent_prevented_invoiceCompleted' => 'verzenden tegengehouden door het event "AcumulusInvoiceSendBefore"',
        'reason_not_sent_empty_invoice' => '0-bedrag factuur',
        'reason_not_sent_not_enabled_triggerInvoiceCreate' => 'optie "verzenden op aanmaken winkelfactuur" niet aangezet',
        'reason_not_sent_not_enabled_triggerInvoiceSent' => 'optie "verzenden op versturen winkelfactuur naar klant" niet aangezet',
        'reason_not_sent_dry_run' => 'verzenden tegengehouden door optie om niet daadwerkelijk te versturen',
        'reason_not_sent_local_errors' => 'verzenden tegengehouden omdat er lokaal fouten zijn geconstateerd',
        'reason_unknown' => 'onbekende reden: %d',
    ];

    protected $en = [
        'message_invoice_send' => '%1$s: %2$s was %3$s',
        'message_invoice_source' => 'Invoice for %1$s %2$s',
        'message_invoice_reason' => '%1$s (reason: %2$s)',
        'action_unknown' => 'yet unknown',
        'action_sent' => 'sent',
        'action_not_sent' => 'not sent',
        'reason_sent_testMode' => 'test mode',
        'reason_sent_new' => 'not yet sent',
        'reason_sent_new_status_change' => 'mot yet sent and %1$s in [%2$s]',
        'reason_sent_lock_expired' => 'not yet sent because the previous attempt failed',
        'reason_sent_forced' => 'forced',
        'reason_not_sent_wrongStatus' => 'wrong status: %1$s not in [%2$s]',
        'reason_not_sent_triggerCreditNoteEvent_None' => 'option to automatically send credit notes not enabled',
        'reason_not_sent_alreadySent' => 'has already been sent',
        'reason_not_sent_alreadySending' => 'already sending',
        'reason_not_sent_prevented_invoiceCreated' => 'sending prevented by event "AcumulusInvoiceCreated"',
        'reason_not_sent_prevented_invoiceCompleted' => 'sending prevented by event "AcumulusInvoiceSendBefore"',
        'reason_not_sent_empty_invoice' => '0-amount invoice',
        'reason_not_sent_not_enabled_triggerInvoiceCreate' => 'option "send on creation of shop invoice" not enabled',
        'reason_not_sent_not_enabled_triggerInvoiceSent' => 'option "send on sending of shop invoice to customer" not enabled',
        'reason_not_sent_dry_run' => 'sending prevented by "dry run" option',
        'reason_not_sent_local_errors' => 'sending prevented by local errors',
        'reason_unknown' => 'unknown reason: %d',
    ];
}
