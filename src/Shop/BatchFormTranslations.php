<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for the batch form.
 */
class BatchFormTranslations extends TranslationCollection
{
    protected $nl = array(
        'batch_form_title' => 'Acumulus | Batchverzending',
        'batch_form_header' => 'Verzend facturen naar Acumulus',
        'batch_form_link_text' => 'Acumulus batchverzending',

        'button_submit_batch'=> 'Verzenden',
        'button_cancel' => 'Annuleren',

        'batchFieldsHeader' => 'Batchgewijs verzenden van facturen naar Acumulus',
        'field_invoice_source_type' => 'Factuurtype',
        'field_invoice_source_reference_from' => '# van',
        'field_invoice_source_reference_to' => '# tot',
        'desc_invoice_source_reference_from_to_1' => 'Vul de reeks bestel-referenties of nummers in die u naar Acumulus wilt verzenden. Als u slechts 1 factuur wilt verzenden hoeft u alleen het \'# van\' in te vullen. Laat beide velden leeg als u op datum wilt verzenden.',
        'desc_invoice_source_reference_from_to_2' => 'Vul de reeks bestel of creditnota-referenties of nummers in die u naar Acumulus wilt verzenden. Als u slechts 1 factuur wilt verzenden hoeft u alleen het \'# van\' in te vullen. Laat beide velden leeg als u op datum wilt verzenden.',
        'field_date_from' => 'Datum van',
        'field_date_to' => 'Datum tot',
        'desc_date_from_to' => 'Vul de periode in waarvan u de facturen naar Acumulus wilt verzenden. De selectie vindt plaats op basis van de datum van de meest recente wijziging aan de bestelling of creditnota. Als u slechts de facturen van 1 dag wilt verzenden hoeft u alleen de \'Datum van\' in te vullen. Laat beide velden leeg als u op nummer wilt verzenden.',
        'field_send_mode' => 'Verzendwijze',
        'option_send_normal' => 'Verzend alleen indien nog niet verzonden',
        'option_send_force' => 'Altijd verzenden, als de factuur al is verzonden wordt de bestaande factuur verwijderd in Acumulus',
        'option_send_test_mode' => 'Verzend in testmodus',
        'desc_send_mode' => 'Facturen die binnen de reeks vallen maar al naar Acumulus verstuurd zijn, worden standaard niet verzonden. Dit is de 1e optie. Door de 2e optie te selecteren forceert u het nogmaals versturen van deze facturen. Indien nog aanwezig, wordt de oude boeking in Acumulus naar de prullenbak verplaatst. Let op: dit kan tot gaten in uw reeks van factuurnummers leiden.<br><br>
                                 Met de 3e optie worden de facturen in testmodus naar Acumulus verstuurd. Acumulus zal alleen de factuur controleren op fouten en waarschuwingen maar zal deze niet opslaan, zodat uw administratie niet in de war raakt. U ontvangt alijd een mail met de resultaten. Deze optie is gelijk aan de optie "Testmodus" van de instelling "Factuur verzendmodus" van het instellingenformulier, maar geldt alleen voor deze batchverzending. Gebruik deze optie om de mail die u ontvangt mee te kunnen sturen met een supportverzoek.',
        'field_dry_run' => 'Dry run',
        'option_dry_run' => 'Laat alleen de lijst van facturen zien die verstuurd zouden worden, zonder daadwerkelijk te versturen.',
        'desc_dry_run' => 'De filters die u hierboven heeft opgegeven kunnen in bepaalde gevallen voor verrassingen zorgen. Door deze optie aan te vinken krijgt u in het resultatenoverzicht een lijst te zien van facturen die verstuurd zouden worden, zonder dat het versturen daadwerkelijk plaats vindt.</p>',
        'batchLogHeader' => 'Resultaten',
        'batchInfoHeader' => 'Uitgebreide informatie',
        'batch_info' => <<<LONGSTRING
<p>Met dit formulier kunt u de facturen van een aantal orders of creditnota's in
één keer versturen.
Dit is vooral handig als u deze koppeling net heeft geïnstalleerd want normaal
gesproken heeft het automatisch versturen de voorkeur.</p>
<p><strong>Performance: het versturen van een factuur kan tot enige seconden
duren.
Geef daarom niet teveel facturen in één keer op.
U kunt dan een time-out krijgen, waardoor het resultaat van de laatst verstuurde
factuur niet opgeslagen wordt.</strong></p>
<p>Het versturen van orders gaat net als het automatisch versturen:</p>
<ul style="list-style: inside disc;">
<li>De factuur wordt op exact dezelfde wijze aangemaakt als bij het automatisch
versturen.</li>
<li>Als er facturen zijn die fouten bevatten ontvangt u een mail per factuur.
</li>
<li>Alle door u geregistreerde event handlers die reageren op één van de door
deze Acumulus module gedefinieerde events (of hook of actie) worden voor alle
facturen die verzonden gaan worden uitgevoerd.</li>
</ul>
<p>Dit formulier werkt in zijn huidige vorm, maar er zijn vast nog wel
verbeteringen aan te brengen. Dus als u ideeën heeft, laat het ons weten..</p>
LONGSTRING
    ,

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
        'message_form_range_list' => 'Gevonden %1$s.',
        'message_form_batch_success' => 'De facturen zijn succesvol verzonden. Zie het resultatenoverzicht voor eventuele opmerkingen en waarschuwingen.',
        'message_form_batch_error' => 'Er zijn fouten opgetreden bij het versturen van de facturen. Zie het resultatenoverzicht voor meer informatie over de fouten.',

    );

    protected $en = array(
        'batch_form_title' => 'Acumulus | Send batch',
        'batch_form_header' => 'Send a batch of invoices to Acumulus',
        'batch_form_link_text' => 'Acumulus batch',

        'button_submit_batch'=> 'Send',
        'button_cancel' => 'Cancel',

        'batchFieldsHeader' => 'Send a batch of invoices to Acumulus',
        'field_invoice_source_type' => 'Invoice type',
        'field_invoice_source_reference_from' => '# from',
        'field_invoice_source_reference_to' => '# to',
        'desc_invoice_source_reference_from_to_1' => 'Enter the range of order references or ids you want to send to Acumulus. If you only want to send 1 invoice, you only have to fill in the \'# from\' field. Leave empty if you want to send by date.',
        'desc_invoice_source_reference_from_to_2' => 'Enter the range of order or credit note numbers or ids you want to send to Acumulus. If you only want to send 1 invoice, you only have to fill in the \'# from\' field. Leave empty if you want to send by date.',
        'field_date_from' => 'Date from',
        'field_date_to' => 'Date to',
        'desc_date_from_to' => 'Enter the period over which you want to send invoices to Acumulus. If you want to send the invoices of 1 day, only fill in the \'Date from\' field. Leave empty if you want to send by id.',
        'field_send_mode' => 'Send mode',
        'option_send_normal' => 'Only send if not already sent',
        'option_send_force' => 'Always send, overwrite the existing invoice if already sent',
        'option_send_test_mode' => 'Send in test mode',
        'desc_send_mode' => '<p>Invoices that fall within the range but are already sent to Acumulus will normally not be sent again. This is the 1st option. By checking the 2nd option, these orders will be sent again. If still available, the old entry will be moved to the waste bin in Acumulus.</p>
                             <p>With the 3rd option, invoices will be sent to Acumulus using the test mode. Acumulus will only check the input for errors and warnings but not store the invoice, so your administration will not be polluted. You will always receive a mail with the results. This option overrules the setting "Invoice send mode" on the configuration form. Use this option so you can forward the mail you receive with a support request.</p>',
        'field_dry_run' => 'Dry run',
        'option_dry_run' => 'Dry run',
        'desc_dry_run' => 'In some cases, the filters you defined here can lead to surprises. By checking this option you will get a list of invoices that would be sent, without actually being sent.</p>',
        'batchLogHeader' => 'Results',
        'batchInfoHeader' => 'Additional information',
        'batch_info' => <<<LONGSTRING
<p>You can use this form to send a number of orders or credit notes at once.
This is most useful when you just installed the plugin, because normally you
should prefer automatic sending.</p>
<p><strong>Performance: sending an invoice can take up to a few seconds.
Therefore, you should not try to send to many invoices in 1 batch as that may
lead to a time-out on your web server.</strong></p>
<p>Sending invoices is done as with automatic sending:</p>
<ul style="list-style: inside disc;">
<li>The invoice is created exactly the same way as with automatic sending.</li>
<li>If an invoice for a given order or credit memo has already been sent, it
will not be sent again, unless you checked the 'Force sending' checkbox.</li>
<li>If an invoice contains a warning or an error you will receive an email per
incorrect invoice.</li>
<li>All registered event handlers that react to 1 of the Acumulus defined
events are triggered for all invoices that are sent.</li>
</ul>
<p>This form works as it is but we guess that improvements can be made.
So, if you have any ideas, please let us know.</p>
LONGSTRING
    ,

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
        'message_form_range_list' => 'Found %1$s.',
        'message_form_batch_success' => 'The invoices were sent successfully. See the results overview for any remarks or warnings.',
        'message_form_batch_error' => 'Errors during sending the invoices. See the results overview for more information on the errors.',

    );
}
