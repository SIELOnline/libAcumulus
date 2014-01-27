<?php
// Page elements
$_['extensions'] = 'Extensies';
$_['modules'] = 'Modules';
$_['page_title'] = 'Acumulus';
$_['module_name'] = 'Acumulus';
$_['text_home'] = 'Home';
$_['button_save'] = 'Opslaan';
$_['button_cancel'] = 'Annuleren';

// Messages
$_['message_success'] = 'De instellingen zijn opgeslagen.';

$_['message_validate_contractcode_0'] = 'Het veld Contractcode is verplicht, vul de contractcode in die u ook gebruikt om in te loggen op Acumulus.';
$_['message_validate_contractcode_1'] = 'Het veld Contractcode is een numeriek veld, vul de contractcode in die u ook gebruikt om in te loggen op Acumulus.';
$_['message_validate_username_0'] = 'Het veld Gebruikersnaam is verplicht, vul de gebruikersnaam in die u ook gebruikt om in te loggen op Acumulus.';
$_['message_validate_password_0'] = 'Het veld Wachtwoord is verplicht, vul het wachtwoord in dat u ook gebruikt om in te loggen op Acumulus.';
$_['message_validate_email_0'] = 'Het veld Email is geen valide e-mailadres, vul uw eigen e-mailadres in.';

$_['message_error_vat19and21'] = 'Deze order heeft zowel 19% als 21% BTW percentages. U dient deze factuur handmatig aan te maken in Acumulus.';
$_['message_warning_multiplevat'] = 'Deze order heeft meerdere BTW percentages. Door verschillen in de manier waarop uw web shop en Acumulus een factuur opslaan, kan het zijn dat dat de BTW bedragen in Acumulus NIET kloppen. U dient daarom deze factuur handmatig te controleren in Acumulus!';

$_['message_error_req_curl'] = 'Voor het gebruik van deze module dient de CURL PHP extensie actief te zijn op uw server.';
$_['message_error_req_xml'] = 'Voor het gebruik van deze module met het output format XML, dient de SimpleXML PHP extensie actief te zijn op uw server.';
$_['message_error_req_dom'] = 'Voor het gebruik van deze module dient de DOM PHP extensie actief te zijn op uw server.';

$_['message_error_auth'] = 'Uw Acumulus account gegevens zijn onjuist. Zodra u de correcte gegevens hebt ingevuld, worden hier de overige instellingen getoond.';
$_['message_error_comm'] = 'Er is een fout opgetreden bij het ophalen van uw gegevens van Acumulus. Probeer het later nog eens. Zodra de verbinding hersteld is worden hier de overige instellingen getoond.';
$_['message_auth_unknown'] = 'Zodra u uw Acumulus accountgegevens hebt ingevuld, worden hier de overige instellingen getoond.';

$_['message_response_0'] = 'Succes. Zonder waarschuwingen';
$_['message_response_1'] = 'Mislukt. Fouten gevonden';
$_['message_response_2'] = 'Succes. Met waarschuwingen';
$_['message_response_3'] = 'Fout. Neem contact op met Acumulus';
$_['message_response_x'] = 'Onbekende status code';

$_['message_error'] = 'Fout';
$_['message_warning'] = 'Waarschuwing';

$_['message_info_for_user'] = 'De informatie hieronder wordt alleen getoond om eventuele support te vergemakkelijken. U kunt deze informatie negeren.';
$_['message_sent'] = 'Verzonden bericht';
$_['message_received'] = 'Ontvangen bericht';

$_['message_no_invoice'] = 'niet aangemaakt';

$_['mail_subject'] = 'Fouten of waarschuwingen bij verzenden factuur naar Acumulus';
$_['mail_text'] = <<<LONGSTRING
Geachte heer/mevrouw,

Bij het verzenden van een order naar Acumulus zijn er foutmeldingen of
waarschuwingen terug gestuurd.

Als de verzendstatus gelijk aan "2 {status_2_text}" is,
zijn er alleen waarschuwingen. Het versturen is dan wel gelukt en is er een
factuur aangemaakt in Acumulus. We raden u aan om de factuur in Acumulus op te
zoeken en deze extra goed te controleren.

Als de verzendstatus "1 {status_1_text}" of
"3 {status_3_text}" is, dan is het versturen niet gelukt.
U dient de factuur dan handmatig aan te maken in Acumulus of deze aan te passen
en nogmaals te versturen.

Verzendstatus: {status} {status_text}.
Order:         {order_id}
Factuur:       {invoice_id}

Berichten:
{messages}

Meer informatie betreffende de vermeldde foutcodes kunt u vinden op
https://apidoc.sielsystems.nl/node/16.
LONGSTRING;

$_['mail_html'] = <<<LONGSTRING
<p>Geachte heer/mevrouw,</p>

<p>Bij het verzenden van een order naar Acumulus zijn er foutmeldingen of
waarschuwingen terug gestuurd.</p>
<p>Als de verzendstatus gelijk aan "2 {status_2_text}" is,
zijn er alleen waarschuwingen. Het versturen is dan wel gelukt en is er een
factuur aangemaakt in Acumulus. We raden u aan om de factuur in Acumulus op te
zoeken en deze extra goed te controleren.</p>
<p>Als de verzendstatus "1 {status_1_text}" of
"3 {status_3_text}" is, dan is het versturen niet gelukt.
U dient de factuur dan handmatig aan te maken in Acumulus of deze aan te passen
en nogmaals te versturen.</p>
<table>
  <tr><td>Verzendstatus:</td><td>{status} {status_text}.</td></tr>
  <tr><td>Order:</td><td>{order_id}</td></tr>
  <tr><td>Factuur:</td><td>{invoice_id}</td></tr>
</table>
<p>Berichten:<br>
{messages_html}</p>
<p>Meer informatie betreffende de vermeldde foutcodes kunt u vinden op
<a href="https://apidoc.sielsystems.nl/node/16">Acumulus - API documentation: exit and warning codes</a>.</p>
LONGSTRING;

// Configuration form
$_['accountSettingsHeader'] = 'Uw Acumulus account';
$_['field_code'] = 'Contractcode';
$_['field_username'] = 'Gebruikersnaam';
$_['field_password'] = 'Wachtwoord';
$_['field_email'] = 'E-mail';
$_['desc_email'] = 'Als er fouten optreden tijdens een batchverwerking, ontvangt u op dit adres informatie hierover. Als u dit leeg laat ontvangt u geen meldingen.';

$_['invoiceSettingsHeader'] = 'Uw factuurinstellingen';
$_['option_empty'] = 'Maak uw keuze';

$_['field_useAcumulusInvoiceNr'] = 'Factuurnummer';
$_['option_useAcumulusInvoiceNr_0'] = 'Gebruik het factuurnummer gegenereerd door uw webwinkel';
$_['option_useAcumulusInvoiceNr_1'] = 'Laat Acumulus het factuurnummer bepalen';

$_['field_useOrderDate'] = 'Factuurdatum';
$_['option_useOrderDate_0'] = 'Gebruik orderdatum';
$_['option_useOrderDate_1'] = 'Gebruik datum van overzetten';

$_['field_defaultCustomerType'] = 'Importeer klanten als';

$_['field_overwriteIfExists'] = 'Klantadresgegevens';
$_['option_overwriteIfExists'] = 'Overschrijf bestaande adresgegevens';
$_['desc_overwriteIfExists'] = 'Vink deze optie aan als u niet direct in Acumulus adresgegevens van uw web shop klanten bijwerkt.';

$_['field_defaultAccountNumber'] = 'Bankrekeningnummer';
$_['desc_defaultAccountNumber'] = 'Maakt u binnen Acumulus gebruik van meerdere rekeningen en wilt u alle orders uit uw webwinkel op een specifieke rekening binnen laten komen, kies dan hier het bankrekeningnummer.';

$_['field_defaultCostHeading'] = 'Kostenplaats';
$_['desc_defaultCostHeading'] = 'Maakt u binnen Acumulus gebruik van meerdere kostenplaatsen en wilt u alle orders uit uw webwinkel op een specifieke kostenplaats binnen laten komen, kies dan hier de kostenplaats.';

$_['field_defaultInvoiceTemplate'] = 'Factuur-sjabloon';
$_['desc_defaultInvoiceTemplate'] = 'Maakt u binnen Acumulus gebruik van meerdere factuur-sjablonen en wilt u alle orders uit uw webwinkel op een specifieke factuursjabloon printen, kies dan hier het factuur-sjabloon.';

$_['option_empty_triggerOrderStatus'] = 'Niet automatisch overzetten';
$_['field_triggerOrderStatus'] = 'Orderstatus';
$_['desc_triggerOrderStatus'] = 'U kunt hier kiezen bij welke orderstatus facturen worden overgezet naar Acumulus. Als u voor "Niet automatisch overzetten" kiest, doet deze module niets.';
