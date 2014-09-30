<?php
// Page elements
$_['extensions'] = 'Extensies';
$_['modules'] = 'Modules';
$_['page_title'] = 'Acumulus instellingen';
$_['module_name'] = 'Acumulus';
$_['module_description'] = 'Acumulus koppeling';
$_['text_home'] = 'Home';
$_['button_settings'] = 'Instellingen';
$_['button_save'] = 'Opslaan';
$_['button_back'] = 'Terug naar overzicht';
$_['button_confirm_uninstall'] = 'Ja, verwijder data en instellingen';
$_['button_cancel_uninstall'] = 'Nee, alleen uitschakelen, bewaar data en instellingen';
$_['button_cancel'] = 'Annuleren';
$_['button_send'] = 'Verzenden';

// Messages
$_['message_config_saved'] = 'De instellingen zijn opgeslagen.';
$_['message_uninstall'] = 'Wilt u de configuratie-instellingen verwijderen?';

$_['message_validate_contractcode_0'] = 'Het veld Contractcode is verplicht, vul de contractcode in die u ook gebruikt om in te loggen op Acumulus.';
$_['message_validate_contractcode_1'] = 'Het veld Contractcode is een numeriek veld, vul de contractcode in die u ook gebruikt om in te loggen op Acumulus.';
$_['message_validate_username_0'] = 'Het veld Gebruikersnaam is verplicht, vul de gebruikersnaam in die u ook gebruikt om in te loggen op Acumulus.';
$_['message_validate_password_0'] = 'Het veld Wachtwoord is verplicht, vul het wachtwoord in dat u ook gebruikt om in te loggen op Acumulus.';
$_['message_validate_email_0'] = 'Het veld Email is geen valide e-mailadres, vul uw eigen e-mailadres in.';
$_['message_validate_email_1'] = 'Het veld Email is verplicht, vul uw eigen e-mailadres in.';
$_['message_validate_email_2'] = 'Het veld (fictieve klant) Email is geen valide e-mailadres, vul een correct e-mailadres in.';
$_['message_validate_email_3'] = 'Het veld BCC is geen valide e-mailadres, vul een correct e-mailadres in.';
$_['message_validate_email_4'] = 'Het veld Afzender is geen valide e-mailadres, vul een correct e-mailadres in.';
$_['message_validate_conflicting_options'] = 'Als u geen klantgegevens naar Acumulus verstuurt, kunt u Acumulus geen PDF factuur laten versturen. Pas één van beide opties aan.';

$_['message_error_vat19and21'] = 'Deze order heeft zowel 19% als 21% BTW percentages. U dient deze factuur handmatig aan te maken in Acumulus.';
$_['message_warning_incorrect_vat'] = 'De Acumulus koppeling was niet in staat om de BTW bedragen op de factuur correct te herleiden. U dient daarom deze factuur handmatig te controleren in Acumulus!';

$_['message_error_req_curl'] = 'Voor het gebruik van deze module dient de CURL PHP extensie actief te zijn op uw server.';
$_['message_error_req_xml'] = 'Voor het gebruik van deze module met het output format XML, dient de SimpleXML PHP extensie actief te zijn op uw server.';
$_['message_error_req_dom'] = 'Voor het gebruik van deze module dient de DOM PHP extensie actief te zijn op uw server.';

$_['message_error_auth'] = 'Uw Acumulus account gegevens zijn onjuist. Zodra u de correcte gevens hebt ingevuld, worden hier de overige instellingen getoond.';
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

$_['order_id'] = 'Ordernummer';
$_['shipping_costs'] = 'Verzendkosten';
$_['payment_costs'] = 'Betalingskosten';
$_['discount'] = 'Korting';
$_['discount_code'] = 'Kortingscode';
$_['coupon_code'] = 'Cadeaubon';
$_['gift_wrapping'] = 'Cadeauverpakking';
$_['fee'] = 'Behandelkosten';
$_['refund'] = 'Terugbetaling';
$_['refund_adjustment'] = 'Aanpassing kredietbedrag';

// Mails
$_['mail_sender_name'] = 'Uw webwinkel';
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

Verzendstatus:    {status} {status_text}.
(Webshop)Order:   {order_id}
(Webshop)Factuur: {invoice_id}

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
  <tr><td>(Webshop)Order:</td><td>{order_id}</td></tr>
  <tr><td>(Webshop)Factuur:</td><td>{invoice_id}</td></tr>
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
$_['desc_email'] = 'Het e-mailadres waarop u geïnformeerd wordt over fouten die zijn opgetreden tijdens het versturen van facturen. Omdat deze module niet kan weten of het vanuit een beheerdersscherm is aangeroepen, zal het geen berichten op het scherm plaatsen. Daarom is het invullen van een e-mailadres verplicht.';

$_['invoiceSettingsHeader'] = 'Uw factuurinstellingen';
$_['option_empty'] = 'Maak uw keuze';

$_['field_invoiceNrSource'] = 'Factuurnummer';
$_['option_invoiceNrSource_1'] = 'Gebruik het factuurnummer van uw webwinkel. Let op: als er nog geen factuur aan een order gekoppeld is, zal het bestelnummer gebruikt worden!';
$_['option_invoiceNrSource_2'] = 'Gebruik het bestelnummer van uw webwinkel.';
$_['option_invoiceNrSource_3'] = 'Laat Acumulus het factuurnummer bepalen.';
$_['desc_invoiceNrSource'] = 'U kunt hier kiezen welk nummer Acumulus als factuurnummer moet gebruiken.';

$_['field_dateToUse'] = 'Factuurdatum';
$_['option_dateToUse_1'] = 'Gebruik de aanmaakdatum van de factuur. Let op: als er nog geen factuur aan uw order gekoppeld is, zal de aanmaakdatum van de bestelling gebruikt worden!';
$_['option_dateToUse_2'] = 'Gebruik de aanmaakdatum van de bestelling.';
$_['option_dateToUse_3'] = 'Gebruik de datum van het overzetten.';
$_['desc_dateToUse'] = 'U kunt hier kiezen welke datum de factuur in Acumulus moet krijgen.';

$_['field_clientData'] = 'Klantadresgegevens';
$_['option_sendCustomer'] = 'Uw (niet zakelijke) klanten automatisch aan uw relaties in Acumulus toevoegen.';
$_['option_overwriteIfExists'] = 'Overschrijf bestaande adresgegevens.';
$_['desc_clientData'] = 'Binnen Acumulus is het mogelijk om uw klantrelaties te beheren.
Deze koppeling voegt automatisch uw klanten aan het relatieoverzicht van Acumulus toe.
Dit is niet altijd gewenst en kunt u voorkomen door de eerste optie uit te zetten.
Hierdoor worden alle transacties van consumenten binnen uw webwinkel onder 1 vaste fictieve relatie ingeboekt in Acumulus.
De tweede optie moet u alleen uitzetten als u direct in Acumulus adresgegevens van uw webwinkel-klanten bijwerkt.
Als u de eerste optie heeft uitgezet, geldt de tweede optie alleen voor uw zakelijke klanten.';

$_['field_defaultCustomerType'] = 'Importeer klanten als';

$_['field_defaultAccountNumber'] = 'Bankrekeningnummer';
$_['desc_defaultAccountNumber'] = 'Maakt u binnen Acumulus gebruik van meerdere rekeningen en wilt u alle orders uit uw webwinkel op een specifieke rekening binnen laten komen, kies dan hier het bankrekeningnummer.';

$_['field_defaultCostCenter'] = 'Kostenplaats';
$_['desc_defaultCostCenter'] = 'Maakt u binnen Acumulus gebruik van meerdere kostenplaatsen en wilt u alle orders uit uw webwinkel op een specifieke kostenplaats binnen laten komen, kies dan hier de kostenplaats.';

$_['field_defaultInvoiceTemplate'] = 'Factuur-sjabloon (niet betaald)';
$_['field_defaultInvoicePaidTemplate'] = 'Factuur-sjabloon (betaald)';
$_['option_same_template'] = 'Zelfde sjabloon als voor niet betaald';
$_['desc_defaultInvoiceTemplates'] = 'Maakt u binnen Acumulus gebruik van meerdere factuur-sjablonen en wilt u alle orders uit uw webwinkel op een specifieke factuursjabloon printen, kies dan hier de factuur-sjablonen voor niet betaalde respectievelijk betaalde orders.';

$_['field_triggerOrderEvent'] = 'Moment van versturen';
$_['option_triggerOrderEvent_1'] = 'Als een order de hieronder door u gekozen status bereikt.';
$_['option_triggerOrderEvent_2'] = 'Als de factuur wordt aangemaakt voor deze order.';
$_['desc_triggerOrderEvent'] = 'U kunt hier kiezen op welk moment de factuur wordt verstuurd. Deze koppeling gebruikt alleen gegevens van de order, dus u kunt elke status kiezen. De factuur hoeft dus nog niet aangemaakt te zijn.';

$_['option_empty_triggerOrderStatus'] = 'Niet automatisch overzetten';
$_['field_triggerOrderStatus'] = 'Orderstatus';
$_['desc_triggerOrderStatus'] = 'U kunt hier kiezen bij welke orderstatus facturen worden overgezet naar Acumulus. Als u voor "Niet automatisch overzetten" kiest, doet deze module niets.';

$_['emailAsPdfSettingsHeader'] = 'PDF Factuur';
$_['desc_emailAsPdfInformation'] = 'Bij het versturen van ordergegevens naar Acumulus, kunt u Acumulus een PDF factuur laten versturen naar uw klant. Deze wordt direct verstuurd naar het door de klant opgegeven emailadres.';

$_['field_emailAsPdf'] = 'Optie inschakelen';
$_['option_emailAsPdf'] = 'Verstuur de factuur als PDF vanuit Acumulus.';
$_['desc_emailAsPdf'] = 'Als u deze optie aanvinkt, kunt u de overige opties gebruiken om de emailverzending aan uw wensen aan te passen. Het bericht in de email body kunt u niet hier instellen, dat kunt u in Acumulus doen onder "Beheer - Factuur-sjablonen".';

$_['field_emailFrom'] = 'Afzender';
$_['desc_emailFrom'] = 'Het email adres dat als afzender gebruikt moet worden. Als u dit leeg laat wordt het emailadres van uw Acumulus account gebruikt.';

$_['field_emailBcc'] = 'BCC';
$_['desc_emailBcc'] = 'Additionele emailadressen om de factuur naar toe te sturen, bv. het emailadres van uw eigen administratie-afdeling. Als u dit leeg laat wordt de factuur alleen naar de klant verstuurd.';

$_['field_subject'] = 'Onderwerp';
$_['desc_subject'] = 'Het onderwerp van de email. Als u dit leeg laat wordt "Factuur [factuurnummer] Order [bestelnummer]" gebruikt. U kunt [#b] gebruiken om het bestelnummer in de onderwerpregel te plaatsen en [#f] voor het factuurnummer (van de webshop, niet van Acumulus).';

$_['versionInformationHeader'] = 'Informatie over deze module';
$_['desc_versionInformation'] = 'Vermeldt aub deze gegevens bij een supportverzoek.';

$_['field_debug'] = 'Support en debug';
$_['option_debug_1'] = 'Verzend berichten naar Acumulus en ontvang alleen een mail bij fouten of waarschuwingen.';
$_['option_debug_2'] = 'Verzend berichten naar Acumulus en ontvang een mail met het verzonden en ontvangen bericht.';
$_['option_debug_3'] = 'Verzend geen berichten naar Acumulus, verstuur alleen een mail met het bericht dat verstuurd zou worden.';
$_['desc_debug'] = 'U kunt hier een support mode kiezen. Kies voor de eerste optie tenzij u i.v.m. een supportverzoek bent geïnstrueerd om iets anders te kiezen.';

// Send manual form
$_['page_title_manual'] = 'Verstuur factuur handmatig';
$_['manualSelectIdHeader'] = 'Specificeer de opnieuw te verzenden factuur';
$_['field_manual_order'] = 'Order #';
$_['field_manual_invoice'] = 'Factuur #';
$_['field_manual_creditmemo'] = 'Creditmemo #';
$_['manual_form_desc'] = '<strong>LET OP: Het gebruik van dit formulier is op eigen risico.</strong> Acumulus voert geen controle op dubbel inzenden uit. Door het (nogmaals) handmatig versturen van facturen kan uw administatie ontregeld raken. Gebruik dit formulier daarom alleen als u i.v.m. een supportverzoek bent geïnstrueerd om dit te doen en dan het liefst alleen als voor de "support en debug" mode de 3e optie is gekozen (zie het instellingen scherm).';
$_['manual_order_sent'] = "Order '%s' verzonden";
$_['manual_order_not_found'] = "Order '%s' niet gevonden";
$_['manual_invoice_sent'] = "Factuur '%s' verzonden";
$_['manual_invoice_not_found'] = "Factuur '%s' niet gevonden";
$_['manual_creditmemo_sent'] = "Creditmemo '%s' verzonden";
$_['manual_creditmemo_not_found'] = "Credit memo '%s' niet gevonden";

// Uninstall form
$_['uninstallHeader'] = 'Bevestig verwijderen';
$_['desc_uninstall'] = 'De module is uitgeschakeld. Maak een keuze of u ook alle data en instellingen wilt verwijderen of dat u deze (voorlopig) wilt bewaren.';
