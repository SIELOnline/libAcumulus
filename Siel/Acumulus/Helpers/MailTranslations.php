<?php
namespace Siel\Acumulus\Helpers;

/**
 * Contains translations for mails.
 */
class MailTranslations extends TranslationCollection
{
    protected $nl = array(
        // Mails.
        'mail_sender_name' => 'Uw webwinkel',
        'message_no_invoice' => 'niet aangemaakt in Acumulus',
        'mail_subject_errors' => 'Fouten bij het verzenden van een factuur naar Acumulus',
        'mail_subject_warnings' => 'Waarschuwingen bij het verzenden van een factuur naar Acumulus',
        'mail_subject_debug' => 'Factuur verzonden naar Acumulus',
        'mail_text_errors' => <<<LONGSTRING
Bij het verzenden van een factuur naar Acumulus zijn er foutmeldingen terug
gestuurd. Het versturen is niet gelukt. U dient de factuur aan te passen en
nogmaals te versturen of deze handmatig aan te maken in Acumulus.
LONGSTRING
    ,
        'mail_html_errors' => <<<LONGSTRING
<p>Bij het verzenden van een factuur naar Acumulus zijn er foutmeldingen terug
gestuurd. Het versturen is niet gelukt. U dient de factuur aan te passen en
nogmaals te versturen of deze handmatig aan te maken in Acumulus.</p>
LONGSTRING
    ,
        'mail_text_warnings' => <<<LONGSTRING
Bij het verzenden van een factuur naar Acumulus zijn er waarschuwingen terug
gestuurd. De factuur is aangemaakt in Acumulus, maar u dient deze te
controleren op correctheid.
LONGSTRING
    ,
        'mail_html_warnings' => <<<LONGSTRING
<p>Bij het verzenden van een factuur naar Acumulus zijn er waarschuwingen terug
gestuurd. De factuur is aangemaakt in Acumulus, maar u dient deze te
controleren op correctheid.</p>
LONGSTRING
    ,
        'mail_text_debug' => <<<LONGSTRING
Onderstaande factuur is succesvol naar Acumulus verstuurd. Normaal gesproken
krijgt u daar geen bericht van, maar omdat u dit zo heeft ingesteld krijgt u
hier nu toch bericht van.
LONGSTRING
    ,
        'mail_html_debug' => <<<LONGSTRING
<p>Onderstaande factuur is succesvol naar Acumulus verstuurd. Normaal gesproken
krijgt u daar geen bericht van, maar omdat u dit zo heeft ingesteld krijgt u
hier nu toch bericht van.</p>
LONGSTRING
    ,
        'mail_text' => <<<LONGSTRING
{status_specific_text}

(Webshop){invoice_source_type}: {invoice_source_reference}
(Acumulus) factuur:  {acumulus_invoice_id}
Verzendstatus:       {status} {status_message}.

Berichten:
{messages_text}

Meer informatie over eventueel vermeldde foutcodes kunt u vinden op
https://apidoc.sielsystems.nl/node/16.
LONGSTRING
    ,
        'mail_html' => <<<LONGSTRING
{status_specific_html}
<table>
  <tr><td>(Webshop){invoice_source_type}:</td><td>{invoice_source_reference}</td></tr>
  <tr><td>(Acumulus) factuur:</td><td>{acumulus_invoice_id}</td></tr>
  <tr><td>Verzendstatus:</td><td>{status} {status_message}.</td></tr>
</table>
<p>Berichten:<br>
{messages_html}</p>
<p>Meer informatie over eventueel vermeldde foutcodes kunt u vinden op
<a href="https://apidoc.sielsystems.nl/node/16">Acumulus - API documentation: exit and warning codes</a>.</p>
LONGSTRING
    ,
    );

    protected $en = array(
        'mail_sender_name' => 'Your web store',
        'message_no_invoice' => 'not created in Acumulus',
        'mail_subject_errors' => 'Errors on sending an invoice to Acumulus',
        'mail_subject_warnings' => 'Warnings on sending an invoice to Acumulus',
        'mail_subject_debug' => 'Invoice sent to Acumulus',
        'mail_text_errors' => <<<LONGSTRING
On sending an invoice to Acumulus, some errors occurred. The invoice has NOT
been created. You will have to manually create the invoice in Acumulus or
adapt it in your web shop and resend it to Acumulus.
LONGSTRING
    ,
        'mail_html_errors' => <<<LONGSTRING
<p>On sending an invoice to Acumulus, some errors occurred. The invoice has NOT
been created. You will have to manually create the invoice in Acumulus or
adapt it in your web shop and resend it to Acumulus.
</p>
LONGSTRING
    ,
        'mail_text_warnings' => <<<LONGSTRING
On sending an invoice to Acumulus, some warnings occurred, but the invoice
has been created. However, we advice you to check the invoice in Acumulus
for correctness.
LONGSTRING
    ,
        'mail_html_warnings' => <<<LONGSTRING
<p>On sending an invoice to Acumulus, some warnings occurred, but the invoice
has been created. However, we advice you to check the invoice in Acumulus for
correctness.</p>
LONGSTRING
    ,
        'mail_text_debug' => <<<LONGSTRING
The invoice below has successfully been sent to Acumulus. Normally you won't
receive a message saying so, but because you configured so, you do receive
this message now.
LONGSTRING
    ,
        'mail_html_debug' => <<<LONGSTRING
<p>The invoice below has successfully been sent to Acumulus. Normally you won't
receive a message saying so, but because you configured so, you do receive
this message now.</p>
LONGSTRING
    ,
        'mail_text' => <<<LONGSTRING
{status_specific_text}

(Webshop){invoice_source_type}:    {invoice_source_reference}
(Acumulus) invoice: {acumulus_invoice_id}
Send status:        {status} {status_message}.

Messages:
{messages_text}

At https://apidoc.sielsystems.nl/node/16 you can find more information
regarding any error codes mentioned above.
LONGSTRING
    ,
        'mail_html' => <<<LONGSTRING
{status_specific_html}
<table>
  <tr><td>(Webshop){invoice_source_type}:</td><td>{invoice_source_reference}</td></tr>
  <tr><td>(Acumulus) invoice:</td><td>{acumulus_invoice_id}</td></tr>
  <tr><td>Send status:</td><td>{status} {status_message}.</td></tr>
</table>
<p>Messages:<br>
{messages_html}</p>
<p>At <a href="https://apidoc.sielsystems.nl/node/16">Acumulus - API documentation: exit and warning codes</a>
you can find more information regarding any error codes mentioned above.</p>
LONGSTRING
    ,

    );
}
