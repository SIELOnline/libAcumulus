<?php
namespace Siel\Acumulus\Helpers;

/**
 * Contains translations for mails.
 */
class MailTranslations extends TranslationCollection
{
    protected $nl = array(
        // Mails.
        'mail_subject' => 'Factuur verzonden naar Acumulus',
        'mail_subject_concept' => 'Conceptfactuur verzonden naar Acumulus',
        'mail_subject_test_mode' => 'Factuur in testmodus verzonden naar Acumulus',

        'mail_subject_success' => 'succes',
        'mail_subject_warning' => 'waarschuwing(en)',
        'mail_subject_error' => 'fout(en)',
        'mail_subject_exception' => 'ernstige fout',
        'mail_subject_no_pdf' => 'geen pdf verstuurd',

        'mail_sender_name' => 'Uw webwinkel',
        'message_no_invoice' => 'niet aangemaakt in Acumulus',

        'mail_body_exception' => 'Bij het verzenden van een factuur naar Acumulus is er een ernstige fout opgetreden.',
        'mail_body_exception_invoice_not_created' => 'De factuur is niet aangemaakt in Acumulus.',
        'mail_body_exception_invoice_maybe_created' => 'De factuur is misschien aangemaakt, controleer dit in Acumulus zelf.',
        'mail_body_errors' => 'Bij het verzenden van een factuur naar Acumulus zijn er fouten opgetreden.',
        'mail_body_errors_not_created' => 'De factuur is niet aangemaakt in Acumulus. Pas de factuur aan in uw webshop alvorens deze opnieuw te versturen.',
        'mail_body_warnings' => 'Bij het verzenden van een factuur naar Acumulus zijn er waarschuwingen opgetreden.',
        'mail_body_warnings_created' => 'De factuur is aangemaakt, maar u dient deze in Acumulus te controleren en zonodig te corrigeren.',
        'mail_body_success' => 'Onderstaande factuur is succesvol naar Acumulus verstuurd.',

        'mail_body_testmode' => 'De factuur is in testmodus verstuurd en is dus niet aangemaakt.',
        'mail_body_concept' => 'De factuur is als concept aangemaakt. Controleer de factuur in Acumulus, waarna u deze alsnog defintief kan maken. U vindt conceptfacturen onder "Overzichten - Conceptfacturen en offertes".',

        'mail_body_pdf_enabled' => 'U laat Acumulus de factuur als pdf naar de klant versturen.',
        'mail_body_pdf_not_sent_errors' => 'Omdat de factuur fouten bevat en niet is aangemaakt is deze pdf niet verstuurd.',
        'mail_body_pdf_not_sent_concept' => 'Omdat de factuur als concept is aangemaakt is deze pdf niet verstuurd.',

        'mail_messages_header' => 'Meldingen:',
        'mail_messages_desc' => 'Meer informatie over de terugkoppeling van de vermelde foutcodes kunt u vinden op https://www.siel.nl/acumulus/koppelingen/API/Basic_Response/',
        'mail_messages_desc_html' => '<p>Meer informatie over de terugkoppeling van vermeldde foutcodes kunt u vinden op <a href="https://www.siel.nl/acumulus/koppelingen/API/Basic_Response/">Acumulus - Basic response</a>.</p>',

        'mail_support_header' => 'Informatie voor Acumulus support:',
        'mail_support_desc' => 'De informatie hieronder wordt alleen getoond om eventuele support te vergemakkelijken, u kunt deze informatie negeren.',

        'mail_text' => <<<LONGSTRING
{status_specific_text}

(Webshop){invoice_source_type}: {invoice_source_reference}
(Acumulus) factuur:  {acumulus_invoice_id}
Verzendstatus:       {status} {status_message}
{messages_text}
{support_messages_text}
LONGSTRING
    ,
        'mail_html' => <<<LONGSTRING
{status_specific_html}
<table>
  <tr><td>(Webshop){invoice_source_type}:</td><td>{invoice_source_reference}</td></tr>
  <tr><td>(Acumulus) factuur:</td><td>{acumulus_invoice_id}</td></tr>
  <tr><td>Verzendstatus:</td><td>{status} {status_message}</td></tr>
</table>
{messages_html}
{support_messages_html}
LONGSTRING
    ,
    );

    protected $en = array(
        // Mails.
        'mail_subject' => 'Invoice sent to Acumulus',
        'mail_subject_concept' => 'Concept invoice sent to Acumulus',
        'mail_subject_test_mode' => 'Invoice sent to Acumulus in test mode',

        'mail_subject_success' => 'succes',
        'mail_subject_warning' => 'warning(s)',
        'mail_subject_error' => 'error(s)',
        'mail_subject_exception' => 'serious error',
        'mail_subject_no_pdf' => 'no pdf sent',

        'mail_body_exception' => 'Serious error on sending an invoice to Acumulus.',
        'mail_body_exception_invoice_not_created' => 'The invoice has not been created in Acumulus.',
        'mail_body_exception_invoice_maybe_created' => 'The invoice may have been created, but you\'ll have to check this yourself.',
        'mail_body_errors' => 'Errors on sending an invoice to Acumulus.',
        'mail_body_errors_not_created' => 'The invoice has not been created in Acumulus. Correct the invoice in your webshop before sending it again.',
        'mail_body_warnings' => 'Warnings on sending an invoice to Acumulus.',
        'mail_body_warnings_created' => 'The invoice has been created, but you have to check, and if necessary correct, it in Acumulus.',
        'mail_body_success' => 'The invocie below has successfully been sent to Acumulus.',

        'mail_body_testmode' => 'The invoice has been sent in test mode and thus has not been created.',
        'mail_body_concept' => 'The invoice has been created as concept. Check the invoice in Acumulus before finalising it. you will find concept invoices at "Overzichten - Conceptfacturen en offertes".',

        'mail_body_pdf_enabled' => 'you have Acumulus send the invoice as a pdf to the client.',
        'mail_body_pdf_not_sent_errors' => 'Because the invoice contains errors, this pdf has not been sent.',
        'mail_body_pdf_not_sent_concept' => 'Because the invoice was created as concept, this pdf has not been sent.',

        'mail_messages_header' => 'Messages:',
        'mail_messages_desc' => 'At https://www.siel.nl/acumulus/koppelingen/API/Basic_Response/ you can find more information regarding error codes, warnings and responses.',
        'mail_messages_desc_html' => '<p>At <a href="https://www.siel.nl/acumulus/koppelingen/API/Basic_Response/">Acumulus - Basic responses</a> you can find more information regarding error codes, warnings and responses.</p>',

        'mail_support_header' => 'Information for Acumulus support:',
        'mail_support_desc' => 'The information below is only shown to facilitate support, you may ignore it.',

        'mail_sender_name' => 'Your web store',
        'message_no_invoice' => 'not created in Acumulus',

        'mail_text' => <<<LONGSTRING
{status_specific_text}

(Webshop){invoice_source_type}:    {invoice_source_reference}
(Acumulus) invoice: {acumulus_invoice_id}
Send status:        {status} {status_message}
{messages_text}
{support_messages_text}
LONGSTRING
    ,
        'mail_html' => <<<LONGSTRING
{status_specific_html}
<table>
  <tr><td>(Webshop){invoice_source_type}:</td><td>{invoice_source_reference}</td></tr>
  <tr><td>(Acumulus) invoice:</td><td>{acumulus_invoice_id}</td></tr>
  <tr><td>Send status:</td><td>{status} {status_message}</td></tr>
</table>
{messages_html}
{support_messages_html}
LONGSTRING
    ,
    );
}
