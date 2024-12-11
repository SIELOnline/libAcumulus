<?php

declare(strict_types=1);

namespace Siel\Acumulus\Mail;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for mails.
 */
class MailTranslations extends TranslationCollection
{
    protected array $nl = [
        // Subject base: to be overridden
        'mail_subject' => 'Mail van uw webwinkel',
        'mail_subject_testmode' => 'in testmodus',

        // Subject result.
        'mail_subject_null' => '',
        'mail_subject_success' => 'succes',
        'mail_subject_warning' => 'waarschuwing(en)',
        'mail_subject_error' => 'fout(en)',
        'mail_subject_exception' => 'ernstige fout',

        // From name.
        'mail_sender_name' => 'Uw webwinkel',
        'message_not_created' => 'niet aangemaakt in Acumulus',

        // Body main: to be overridden.
        'mail_body_main_paragraph' => '',

        // Body about: to be overridden.
        'mail_about_header' => 'Over',

        // Body messages: can be overridden.
        'send_status' => 'Verzendresultaat',
        'mail_messages_header' => 'Meldingen',
        'mail_messages_desc_text' => 'Meer informatie over de terugkoppeling van de vermeldde foutcodes kunt u vinden op https://www.siel.nl/acumulus/API/Basic_Response/',
        'mail_messages_desc_html' => '<p>Meer informatie over de terugkoppeling van vermeldde foutcodes kunt u vinden op <a href="https://www.siel.nl/acumulus/API/Basic_Response/">Acumulus - Basic response</a>.</p>',

        'mail_support_header' => 'Informatie voor Acumulus support',
        'mail_support_desc' => 'De informatie hieronder wordt alleen getoond om eventuele support te vergemakkelijken, u kunt deze informatie negeren.',
        'mail_support_contact' => 'U kunt support contacteren door deze mail door te sturen naar {support_mail}.',

    ];

    protected array $en = [
        // Mails.
        'mail_subject' => 'Invoice sent to Acumulus',
        'mail_subject_concept' => 'Concept invoice sent to Acumulus',
        'mail_subject_test_mode' => 'Invoice sent to Acumulus in test mode',

        'mail_subject_null' => '',
        'mail_subject_success' => 'success',
        'mail_subject_warning' => 'warning(s)',
        'mail_subject_error' => 'error(s)',
        'mail_subject_exception' => 'serious error',

        'mail_body_exception' => 'Serious error on sending an invoice to Acumulus.',
        'mail_body_exception_not_created' => 'The invoice has not been created in Acumulus.',
        'mail_body_exception_maybe_created' => 'The invoice may have been created, but you\'ll have to check this yourself.',
        'mail_body_errors' => 'Errors on sending an invoice to Acumulus.',
        'mail_body_errors_not_created' => 'The invoice has not been created in Acumulus. Correct the invoice in your webshop before sending it again.',
        'mail_body_warnings' => 'Warnings on sending an invoice to Acumulus.',
        'mail_body_warnings_created' => 'The invoice has been created, but you have to check, and if necessary correct, it in Acumulus.',
        'mail_body_success' => 'The invoice below has successfully been sent to Acumulus.',

        'mail_body_testmode' => 'The invoice has been sent in test mode and thus has not been added to your administration.',
        'mail_body_concept' => 'The invoice has been created as concept. Check the invoice in Acumulus before finalising it. you will find concept invoices at "Lists - Concept invoices and quotations".',

        'mail_messages_header' => 'Messages:',
        'mail_messages_desc_text' => 'At https://www.siel.nl/acumulus/API/Basic_Response/ you can find more information regarding error codes, warnings and responses.',
        'mail_messages_desc_html' => 'At <a href="https://www.siel.nl/acumulus/API/Basic_Response/">Acumulus - Basic responses</a> you can find more information regarding error codes, warnings and responses.',

        'mail_support_header' => 'Information for Acumulus support:',
        'mail_support_desc' => 'The information below is only shown to facilitate support, you may ignore it.',

        'mail_sender_name' => 'Your web store',
        'message_not_created' => 'not created in Acumulus',

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
        ];
}
