<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for the batch form.
 */
class BatchFormTranslations extends TranslationCollection {

  protected $nl = array(
    'batch_form_title' => 'Acumulus | Batch verzending',
    'batch_form_header' => 'Verzend facturen naar Acumulus',

    'button_send' => 'Verzenden',
    'button_cancel' => 'Annuleren',

    'batchFieldsHeader' => 'Batchgewijs verzenden van facturen naar Acumulus',
    'field_invoice_source_type' => 'Factuurtype',
    'field_invoice_source_reference_from' => '# van',
    'field_invoice_source_reference_to' => '# tot',
    'desc_invoice_source_reference_from_to' => 'Vul de reeks bestelnummers in die u naar Acumulus wilt verzenden. Als u slechts 1 factuur wilt verzenden hoeft u alleen het \'# van\' in te vullen. Laat beide velden leeg als u op datum wilt verzenden.',
    'field_date_from' => 'Datum van',
    'field_date_to' => 'Datum tot',
    'desc_date_from_to' => 'Vul de periode in waarvan u de facuren naar Acumulus wilt verzenden. De selectie vindt plaats op basis van de datum van de meest recente wijziging aan de bestelling of creditnota. Als u slechts de facturen van 1 dag wilt verzenden hoeft u alleen de \'Datum van\' in te vullen. Laat beide velden leeg als u op nummer wilt verzenden.',
    'field_options' => 'Opties',
    'option_force_send' => 'Forceer verzenden',
    'desc_batch_options' => 'Facturen die binnen de reeks vallen maar al naar Acumulus verstuurd zijn, worden standaard niet verzonden. Door deze optie aan te vinken forceert u het nogmaals versturen van deze facturen.',
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
<p><strong>LET OP: Het gebruik van de optie 'Forceer verzenden' is op eigen
risico. Door het nogmaals handmatig versturen van facturen kan uw administratie
ontregeld raken.</strong></p>
<p>Het versturen van orders gaat net als het automatisch versturen:</p>
<ul style="list-style: inside disc;">
<li>De factuur wordt op exact dezelfde wijze aangemaakt als bij het automatisch
versturen.</li>
<li>Als er facturen zijn die fouten bevatten ontvangt u een mail per factuur.
</li>
<li>Als u een event handler heeft geregistreerd voor het 'AcumulusInvoiceAdd"
event (of hook of actie) wordt die voor alle facturen die verzonden gaan worden
uitgevoerd.</li>
</ul>
<p>Dit formulier bevindt zich in een experimentele status.
Het werkt in zijn huidige vorm, maar als u de behoefte heeft om de reeks van
facturen op een andere manier te willen aangeven, laat dit ons dan weten.</p>
<p>Standaard worden facuren die al naar Acumulus verzonden zijn, niet opnieuw
verstuurd. Dit kunt u forceren door die optie aan te vinken, maar let op: in
Acumulus wordt dit als een nieuwe factuur gezien. U dient zelf uw boekhouding te
ontdubbelen.
Merk ook nog op dat deze beveiliging alleen werkt voor facturen die sinds de
installatie van de versie met uitgiftedatum begin september 2014 van deze
koppeling verstuurd zijn.
In oudere versies werd nog niet bijgehouden voor welke orders en creditnota's al
een factuur verzonden was.</p>
LONGSTRING
    ,

    'message_validate_batch_source_type_required' => 'U dient een Factuurtype te selecteren.',
    'message_validate_batch_source_type_invalid' => 'U dient een bestaand factuurtype te selecteren.',
    'message_validate_batch_reference_or_date' => 'U dient of een reeks van bestelnummers of een reeks van datums in te vullen.',
    'message_validate_batch_reference_and_date' => 'U kunt niet en een reeks van bestelnummers en een reeks van datums invullen.',
    'message_validate_batch_bad_date_from' => 'U dient een correcte \'Datum van\' in te vullen (formaat jjjj-mm-dd).',
    'message_validate_batch_bad_date_to' => 'U dient een correcte \'Datum tot\' in te vullen (formaat jjjj-mm-dd).',
    'message_validate_batch_bad_date_range' => '\'Datum tot\' dient na \'Datum van\' te liggen.',
    'message_validate_batch_bad_order_range' => '\'# tot\' dient groter te zijn dan \'# van\'.',

    'message_form_empty_range' => 'De door u opgegeven reeks bevat geen enkele %1$s.',
    'message_form_success' => 'De facturen zijn succesvol verzonden. Zie het resultatenoverzicht voor eventuele opmerkingen en waarschuwingen.',
    'message_form_error' => 'Er zijn fouten opgetreden bij het versturen van de facturen. Zie het resultatenoverzicht voor meer informatie over de fouten.',


  );

  protected $en = array(
    'batch_form_title' => 'Acumulus | Send batch',
    'batch_form_header' => 'Send invoices to Acumulus',

    'button_send' => 'Send',
    'button_cancel' => 'Cancel',

    'batchFieldsHeader' => 'Send a batch of invoices to Acumulus',
    'field_invoice_source_type' => 'Invoice type',
    'field_invoice_source_reference_from' => '# from',
    'field_invoice_source_reference_to' => '# to',
    'desc_invoice_source_reference_from_to' => 'Enter the range of order numbers you want to send to Acumulus. If you only want to send 1 invoice, you only have to fill in the \'# from\' field. Leave empty if you want to send by date.',
    'field_date_from' => 'Date from',
    'field_date_to' => 'Date to',
    'desc_date_from_to' => 'Enter the period over which you want to send invoices to Acumulus. If you want to send the invoices of 1 day, only fill in the \'Date from\' field. Leave empty if you want to send by id.',
    'field_options' => 'Options',
    'option_force_send' => 'Force sending',
    'desc_batch_options' => 'Invoices that fall within the range but are already sent to Acumulus will normally not be sent again. By checking this option these orders will be sent again.',
    'batchLogHeader' => 'Results',
    'batchInfoHeader' => 'Additional information',

    'message_validate_batch_source_type_required' => 'Please select an invoice type.',
    'message_validate_batch_source_type_invalid' => 'Please select an existing invoice type.',
    'message_validate_batch_reference_or_date' => 'Fill in a range of order numbers or a range of dates.',
    'message_validate_batch_reference_and_date' => 'Either fill in a range of order numbers OR a range of dates, not both.',
    'message_validate_batch_bad_date_from' => 'Incorrect \'Date from\' (expected format: yyyy-mm-dd).',
    'message_validate_batch_bad_date_to' => 'Incorrect \'Date to\' (expected format: yyyy-mm-dd).',
    'message_validate_batch_bad_date_range' => '\'Date to\' should be after \'Date from\'.',
    'message_validate_batch_bad_order_range' => '\'# to\' should to be greater than \'# from\'.',

    'message_form_empty_range' => 'The range you defined does not contain any %1$s.',
    'message_form_success' => 'The invoices were sent successfully. See the results overview for any remarks or warnings.',
    'message_form_error' => 'Errors during sending the invoices. See the results overview for more information on the errors.',

  );

}
