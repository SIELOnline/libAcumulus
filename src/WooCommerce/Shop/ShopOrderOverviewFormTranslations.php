<?php
namespace Siel\Acumulus\WooCommerce\Shop;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for the shop order status overview form.
 */
class ShopOrderOverviewFormTranslations extends TranslationCollection
{
    protected $nl = array(
        'shop_order_form_title' => 'Acumulus | Bestellingsoverzicht',
        'shop_order_form_header' => 'Acumulus bestellingsstatusoverzicht',
        'shop_order_form_link_text' => 'Acumulus bestellingsstatusoverzicht',

        'acumulus_invoice' => 'Factuur',
        ShopOrderOverviewForm::Status_NotSent => 'Nog niet aangemaakt',
        ShopOrderOverviewForm::Status_Sent => 'Aangemaakt',
        ShopOrderOverviewForm::Status_SentConcept => 'Als concept aangemaakt',
        ShopOrderOverviewForm::Status_Deleted => 'Verwijderd',
        ShopOrderOverviewForm::Status_NonExisting => 'Niet (meer) bestaand in Acumulus',
        ShopOrderOverviewForm::Status_CommunicationError => 'Communicatiefout met Acumulus. Probeer het later nog eens.',
        'status_unknown' => "Onbekende status '%s'",

        'unknown' => 'onbekend',
        'date_invoice' => 'Factuurdatum',
        'date_sent' => 'Verstuurd op',
        'date_deleted' => 'Verwijderd op',
        'date_payment' => 'Betaald op',
        'info_concept' => 'Helaas kan een als concept verzonden factuur niet verder meer gevolgd worden in Acumulus, ook niet als deze definitief gemaakt is.',
        'info_non_existing' => 'De factuur is destijds verzonden, maar bestaat niet meer in Acumulus, ook niet in de prullenbak.',
        'messages' => 'Meldingen',
        'info_deleted' => 'De factuur is verzonden, maar is in Acumulus naar de prullenbak verplaatst.',
        'invoice_number' => 'Factuurnummer in Acumulus',
        'vat_type' => 'Factuurtype',
        'vat_type_1' => 'Normaal',
        'vat_type_2' => 'BTW-verlegd binnen Nederland;',
        'vat_type_3' => 'BTW-verlegd naar ondernemer in de EU',
        'vat_type_4' => 'Goederen buiten de EU',
        'vat_type_5' => 'Margeregeling (tweedehands producten)',
        'vat_type_6' => 'Electronische diensten aan particulieren in de EU',
        'payment_status' => 'Betaalstatus',
        'payment_status_1' => 'Nog niet betaald',
        'payment_status_2' => 'Betaald',

        'button_save' => 'Verzenden',
        'button_send' => 'Verzenden',
        'button_cancel' => 'Annuleren',

        'batchFieldsHeader' => 'Batchgewijs verzenden van facturen naar Acumulus',
        'field_invoice_source_type' => 'Factuurtype',
        'field_invoice_source_reference_from' => '# van',
        'field_invoice_source_reference_to' => '# tot',
        'desc_invoice_source_reference_from_to_1' => 'Vul de reeks bestelling-referenties of nummers in die u naar Acumulus wilt verzenden. Als u slechts 1 factuur wilt verzenden hoeft u alleen het \'# van\' in te vullen. Laat beide velden leeg als u op datum wilt verzenden.',
        'desc_invoice_source_reference_from_to_2' => 'Vul de reeks bestel of creditnota-referenties of nummers in die u naar Acumulus wilt verzenden. Als u slechts 1 factuur wilt verzenden hoeft u alleen het \'# van\' in te vullen. Laat beide velden leeg als u op datum wilt verzenden.',
        'field_date_from' => 'Datum van',
        'field_date_to' => 'Datum tot',
        'desc_date_from_to' => 'Vul de periode in waarvan u de facturen naar Acumulus wilt verzenden (verwacht formaat %1$s). De selectie vindt plaats op basis van de datum van de meest recente wijziging aan de bestelling of creditnota. Als u slechts de facturen van 1 dag wilt verzenden hoeft u alleen de \'Datum van\' in te vullen. Laat beide velden leeg als u op nummer wilt verzenden.',
        'field_options' => 'Opties',
        'option_force_send' => 'Forceer verzenden',
        'option_send_test_mode' => 'Verzend in testmodus',
        'option_dry_run' => 'Laat alleen de lijst van facturen zien die verstuurd zouden worden, zonder daadwerkelijk te versturen.',
        'batchLogHeader' => 'Resultaten',
        'batchInfoHeader' => 'Uitgebreide informatie',

        'message_validate_batch_source_type_required' => 'U dient een Factuurtype te selecteren.',
        'message_validate_batch_source_type_invalid' => 'U dient een bestaand factuurtype te selecteren.',
        'message_validate_batch_reference_or_date_1' => 'U dient of een reeks van bestelnummers of een reeks van datums in te vullen.',
        'message_validate_batch_reference_or_date_2' => 'U dient of een reeks van bestel of creditnotanummers of een reeks van datums in te vullen.',
        'message_validate_batch_reference_and_date_1' => 'U kunt niet en een reeks van bestelnummers en een reeks van datums invullen.',
        'message_validate_batch_reference_and_date_2' => 'U kunt niet en een reeks van bestel of creditnotanummers en een reeks van datums invullen.',
        'message_validate_batch_bad_date_from' => 'U dient een correcte \'Datum van\' in te vullen (verwacht formaat: %1$s).',
        'message_validate_batch_bad_date_to' => 'U dient een correcte \'Datum tot\' in te vullen (verwacht formaat %1$s).',
        'message_validate_batch_bad_date_range' => '\'Datum tot\' dient na \'Datum van\' te liggen.',
        'message_validate_batch_bad_order_range' => '\'# tot\' dient groter te zijn dan \'# van\'.',

        'message_form_range_reference' => 'Reeks: %1$s van %2$s tot %3$s.',
        'message_form_range_date' => 'Reeks: %1$s tussen %2$s en %3$s.',
        'message_form_range_empty' => 'De door u opgegeven reeks bevat geen enkele %1$s.',
        'message_form_batch_success' => 'De facturen zijn succesvol verzonden. Zie het resultatenoverzicht voor eventuele opmerkingen en waarschuwingen.',
        'message_form_batch_error' => 'Er zijn fouten opgetreden bij het versturen van de facturen. Zie het resultatenoverzicht voor meer informatie over de fouten.',

    );

    protected $en = array(
        'batch_form_title' => 'Acumulus | Send batch',
        'batch_form_header' => 'Send a batch of invoices to Acumulus',
        'batch_form_link_text' => 'Acumulus batch',

        'button_save' => 'Send',
        'button_send' => 'Send',
        'button_cancel' => 'Cancel',

        'batchFieldsHeader' => 'Send a batch of invoices to Acumulus',
        'field_invoice_source_type' => 'Invoice type',
        'field_invoice_source_reference_from' => '# from',
        'field_invoice_source_reference_to' => '# to',
        'desc_invoice_source_reference_from_to_1' => 'Enter the range of order references or ids you want to send to Acumulus. If you only want to send 1 invoice, you only have to fill in the \'# from\' field. Leave empty if you want to send by date.',
        'desc_invoice_source_reference_from_to_2' => 'Enter the range of order or credit note numbers or ids you want to send to Acumulus. If you only want to send 1 invoice, you only have to fill in the \'# from\' field. Leave empty if you want to send by date.',
        'field_date_from' => 'Date from',
        'field_date_to' => 'Date to',
        'desc_date_from_to' => 'Enter the period over which you want to send invoices to Acumulus (expected format: %1$s). If you want to send the invoices of 1 day, only fill in the \'Date from\' field. Leave empty if you want to send by id.',
        'field_options' => 'Options',
        'option_force_send' => 'Force sending',
        'option_send_test_mode' => 'Send in test mode',
        'option_dry_run' => 'Dry run.',
        'batchLogHeader' => 'Results',
        'batchInfoHeader' => 'Additional information',

        'message_validate_batch_source_type_required' => 'Please select an invoice type.',
        'message_validate_batch_source_type_invalid' => 'Please select an existing invoice type.',
        'message_validate_batch_reference_or_date_1' => 'Fill in a range of order numbers or a range of dates.',
        'message_validate_batch_reference_or_date_2' => 'Fill in a range of order/credit note numbers or a range of dates.',
        'message_validate_batch_reference_and_date_1' => 'Either fill in a range of order numbers OR a range of dates, not both.',
        'message_validate_batch_reference_and_date_2' => 'Either fill in a range of order/credit note numbers OR a range of dates, not both.',
        'message_validate_batch_bad_date_from' => 'Incorrect \'Date from\' (expected format: %1$s).',
        'message_validate_batch_bad_date_to' => 'Incorrect \'Date to\' (expected format: %1$s).',
        'message_validate_batch_bad_date_range' => '\'Date to\' should be after \'Date from\'.',
        'message_validate_batch_bad_order_range' => '\'# to\' should to be greater than \'# from\'.',

        'message_form_range_reference' => 'Range: %1$s from %2$s to %3$s.',
        'message_form_range_date' => 'Range: %1$s between %2$s and %3$s.',
        'message_form_range_empty' => 'The range you defined does not contain any %1$s.',
        'message_form_success' => 'The invoices were sent successfully. See the results overview for any remarks or warnings.',
        'message_form_error' => 'Errors during sending the invoices. See the results overview for more information on the errors.',

    );
}
