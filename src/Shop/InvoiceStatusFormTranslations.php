<?php
/**
 * @noinspection LongLine
 */

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for the shop order status overview form.
 */
class InvoiceStatusFormTranslations extends TranslationCollection
{
    protected array $nl = [
        // Invoice status.
        'invoice_form_title' => 'Acumulus',
        'invoice_form_header' => 'Status van de factuur in Acumulus voor deze bestelling',
        'invoice_form_header_p' => 'Status van de factuur in Acumulus voor deze %1$s',
        InvoiceStatusForm::Invoice_NotSent => 'Nog niet verzonden',
        InvoiceStatusForm::Invoice_Sent => 'Factuur %1$s van %2$s',
        InvoiceStatusForm::Invoice_SentConcept => 'Op %1$s als concept verzonden',
        InvoiceStatusForm::Invoice_SentConceptNoInvoice => 'Op %1$s als concept verzonden maar nog niet definitief gemaakt.',
        InvoiceStatusForm::Invoice_Deleted => 'Verzonden op %1$s maar vervolgens op %2$s naar de prullenbak verplaatst.',
        InvoiceStatusForm::Invoice_NonExisting => 'Verzonden op %1$s maar niet meer bestaand in Acumulus, ook niet in de prullenbak.',
        InvoiceStatusForm::Invoice_CommunicationError => 'Verzonden, maar door een communicatiefout met Acumulus kunnen we niet meer informatie tonen. Probeer het later nog eens.',
        InvoiceStatusForm::Invoice_LocalError => 'Verzonden, maar door een interne fout kunnen we niet meer informatie tonen. Probeer het later nog eens.',
        'invoice_status_ok' => 'De factuur lijkt in orde, er zijn geen onregelmatigheden gevonden',
        'concept_no_conceptid' => 'Helaas kan van deze conceptfactuur niet meer informatie getoond worden, ook niet als u deze definitief gemaakt heeft.',
        'concept_conceptid_deleted' => 'Deze conceptfactuur is verwijderd voordat deze definitief gemaakt is.',
        'concept_no_invoice' => 'U kunt deze conceptfactuur in Acumulus definitief maken.',
        'concept_multiple_invoiceid' => 'Van deze conceptfactuur zijn meerdere definitieve facturen gemaakt, we weten dus niet aan welke definitieve factuur deze te koppelen.',
        'entry_concept_not_loaded' => 'Deze conceptfactuur is definitief gemaakt, maar lokaal de link aanpassen en opnieuw laden is mislukt.',
        'entry_concept_not_updated' => 'Deze conceptfactuur is definitief gemaakt, maar lokaal de link aanpassen naar deze definitieve factuur is mislukt.',
        'entry_concept_not_id' => 'Deze conceptfactuur is definitief gemaakt, maar we kunnen het nieuwe boekstuknummer niet achterhalen.',
        'messages' => 'Meldingen',
        'invoice_status_unknown' => "Onbekende status '%s'",
        'unknown_action' => "Onbekende actie '%s'",
        'unknown_source' => 'Onbekende %s %u',
        'unknown_entry' => 'Onbekende Acumulus factuur voor %s %u',

        // Amounts.
        'invoice_amount' => 'Bedrag',
        'amount_status_2' => 'Verschil minder dan 2 cent, waarschijnlijk een afrondingsfout',
        'amount_status_3' => 'Verschil tussen de 2 en 5 cent, waarschijnlijk een fout',
        'amount_status_4' => 'Verschil meer dan 5 cent: fout!',

        // Payment status.
        'payment_status' => 'Betaalstatus',
        'payment_status_1' => 'Nog niet betaald',
        'payment_status_2' => 'Betaald',
        'payment_status_2_date' => 'Betaald op %1$s',
        'payment_date' => 'Betaaldatum',
        'payment_status_not_equal' => 'De betaalstatus in Acumulus komt niet overeen met die in uw webshop',
        'message_validate_batch_bad_payment_date' => 'U dient een correcte \'Betaaldatum\' in te vullen.',
        // Dit bericht (met format) is nodig als we ergens geen date picker en geen html5 date field zouden hebben.
        //'message_validate_batch_bad_payment_date' => 'U dient een correcte \'Betaaldatum\' in te vullen (verwacht formaat: %1$s).',

        // Actions.
        'show' => 'Tonen',
        'send_now' => 'Nu verzenden',
        'send_again' => 'Opnieuw verzenden',
        'undelete' => 'Herstel verwijderde boeking',
        'set_paid' => 'Zet op Betaald',
        'set_due' => 'Zet op Niet betaald',
    ];

    protected array $en = [
        // Invoice status.
        'invoice_form_title' => 'Acumulus',
        'invoice_form_header' => 'Status of the invoice in Acumulus for this order',
        'invoice_form_header_p' => 'Status of the invoice in Acumulus for this %1$s',
        InvoiceStatusForm::Invoice_NotSent => 'Not yet sent',
        InvoiceStatusForm::Invoice_Sent => 'Invoice %1$s of %2$s',
        InvoiceStatusForm::Invoice_SentConcept => 'On %1$s sent as concept',
        InvoiceStatusForm::Invoice_SentConceptNoInvoice => 'On %1$s sent as concept but not yet turned into a definitive invoice.',
        InvoiceStatusForm::Invoice_Deleted => 'Sent on %1$s, but subsequently on %2$s moved to the trash bin.',
        InvoiceStatusForm::Invoice_NonExisting => 'Sent on %1$s, but no longer existing in Acumulus, not even in the thrash bin.',
        InvoiceStatusForm::Invoice_CommunicationError => 'Sent, but due to a communication error we cannot show more information. Try again later.',
        InvoiceStatusForm::Invoice_LocalError => 'Sent, but due to an internal error we cannot show more information. Try again later.',
        'invoice_status_ok' => 'The invoice seems to be fine, no irregularities were found',
        'concept_no_conceptid' => 'Unfortunately, we cannot show more information about this concept invoice, not even when it has been made definitive.',
        'concept_conceptid_deleted' => 'This concept invoice has been deleted before turning it into a definitive invoice.',
        'concept_no_invoice' => 'You can turn this concept invoice into a definitive invoice in Acumulus.',
        'concept_multiple_invoiceid' => 'This concept invoice has been turned into multiple definitive invoices, so we do not know to which definitive invoice to link it.',
        'entry_concept_not_loaded' => 'This concept invoice has been turned into a definitive invoice, but locally updating the link to its shop part failed.',
        'entry_concept_not_updated' => 'This concept invoice has been turned into a definitive invoice, but locally updating the link to the definitive invoice failed.',
        'entry_concept_not_id' => 'This concept invoice has been turned into a definitive invoice, but we cannot find out its new entry number.',
        'messages' => 'Messages',
        'invoice_status_unknown' => "Unknown status '%s'",
        'unknown_action' => "Unknown action '%s'",
        'unknown_source' => 'Unknown %s %u',
        'unknown_entry' => 'Unknown Acumulus invoice for %s %u',

        // Amounts.
        'invoice_amount' => 'Amount',
        'amount_status_2' => 'Difference less than 2 cents, probably a rounding error',
        'amount_status_3' => 'Difference between 2 and 5 cents, probably an error',
        'amount_status_4' => 'Difference more than 5 cents: error!',

        // Payment status.
        'payment_status' => 'Payment status',
        'payment_status_1' => 'Due',
        'payment_status_2' => 'Paid',
        'payment_status_2_date' => 'Paid on %1$s',
        'payment_date' => 'Payment date',
        'payment_status_not_equal' => 'The payment state in Acumulus differs from the one in your webshop',
        'message_validate_batch_bad_payment_date' => 'Incorrect \'Payment date\'.',
        // Dit bericht (met format) is nodig als we ergens geen date picker en geen html5 date field zouden hebben.
        //'message_validate_batch_bad_payment_date' => 'Incorrect \'Payment date\' (expected format: %1$s).',

        // Actions.
        'show' => 'Show',
        'send_now' => 'Send now',
        'send_again' => 'Send again',
        'undelete' => 'Restore deleted invoice',
        'set_paid' => 'Set paid',
        'set_due' => 'Set due',
    ];
}
