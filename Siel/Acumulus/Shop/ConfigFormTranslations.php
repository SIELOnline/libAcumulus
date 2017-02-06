<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for the configuration form.
 */
class ConfigFormTranslations extends TranslationCollection
{
    protected $nl = array(
        // Titles, headers, links, buttons and messages.
        'config_form_title' => 'Acumulus | Instellingen',
        'config_form_header' => 'Acumulus instellingen',
        'config_form_link_text' => 'Acumulus basisinstellingen',

        'advanced_form_title' => 'Acumulus | Geavanceerde Instellingen',
        'advanced_form_header' => 'Acumulus geavanceerde instellingen',
        'advanced_form_link_text' => 'Acumulus geavanceerde instellingen',

        'button_link' => '<a href="%2$s">%1$s</a>',
        'button_save' => 'Opslaan',
        'button_cancel' => 'Terug',

        'message_validate_email_2' => 'Het veld (fictieve klant) Email is geen valide e-mailadres, vul een correct e-mailadres in.',
        'message_validate_conflicting_options' => 'Als u geen klantgegevens naar Acumulus verstuurt, kunt u Acumulus geen PDF factuur laten versturen. Pas één van beide opties aan.',

        'message_form_success' => 'De instellingen zijn opgeslagen.',
        'message_form_error' => 'Er is een fout opgetreden bij het opslaan van de instellingen',
        'message_uninstall' => 'Wilt u de configuratie-instellingen verwijderen?',

        'message_error_header' => 'Fout in uw Acumulus accountgegevens',
        'message_error_auth' => 'Uw Acumulus accountgegevens zijn onjuist. Zodra u %2$s de correcte gevens hebt ingevuld, worden hier de %1$s instellingen getoond.',
        'message_error_comm' => 'Er is een fout opgetreden bij het ophalen van uw gegevens van Acumulus. Probeer het later nog eens. Zodra de verbinding hersteld is worden hier de %1$s instellingen getoond.',
        'message_auth_unknown' => 'Zodra u %2$s uw Acumulus accountgegevens hebt ingevuld, worden %1$s de overige instellingen getoond.',
        'message_error_arg1_config' => 'overige',
        'message_error_arg1_advanced' => 'geavanceerde',
        'message_error_arg2_config' => 'hier',
        'message_error_arg2_advanced' => 'in het "Acumulus basisinstellingenformulier"',

        // Account settings.
        'accountSettingsHeader' => 'Uw Acumulus account',
        'desc_accountSettings' => 'Vul hier de gegevens in die u in de welkomstmail heeft ontvangen, of beter nog: u kunt in Acumulus ook een extra gebruiker aanmaken onder "Beheer → Gebruikers → Gebruiker toevoegen". Vul "api - koppeling" in als "Gebruikerstype", deze heeft minder rechten dan een "beheerder" en is dus veiliger.',

        'field_code' => 'Contractcode',
        'field_username' => 'Gebruikersnaam',
        'field_password' => 'Wachtwoord',
        'field_email' => 'E-mail',
        'desc_email' => 'Het e-mailadres waarop u geïnformeerd wordt over fouten die zijn opgetreden tijdens het versturen van facturen. Omdat deze module niet kan weten of het vanuit een beheerdersscherm is aangeroepen, zal het geen berichten op het scherm plaatsen. Daarom is het invullen van een e-mailadres verplicht.',
        'message_validate_contractcode_0' => 'Het veld Contractcode is verplicht, vul de contractcode in die u ook gebruikt om in te loggen op Acumulus.',
        'message_validate_contractcode_1' => 'Het veld Contractcode is een numeriek veld, vul de contractcode in die u ook gebruikt om in te loggen op Acumulus.',
        'message_validate_username_0' => 'Het veld Gebruikersnaam is verplicht, vul de gebruikersnaam in die u ook gebruikt om in te loggen op Acumulus.',
        'message_validate_username_1' => 'Het veld Gebruikersnaam bevat spaties aan het begin of eind. Dit is toegestaan, maar weet u zeker dat dit de bedoeling is?',
        'message_validate_password_0' => 'Het veld Wachtwoord is verplicht, vul het wachtwoord in dat u ook gebruikt om in te loggen op Acumulus.',
        'message_validate_password_1' => 'Het veld Wachtwoord bevat spaties aan het begin of eind. Dit is toegestaan, maar weet u zeker dat dit de bedoeling is?',
        'message_validate_password_2' => 'Het veld Wachtwoord bevat tekens die Acumulus verbiedt (`\'"#%&;<>\\). Weet u zeker dat u het juiste wachtwoord heeft ingetypt?',
        'message_validate_email_0' => 'Het veld Email is geen valide e-mailadres, vul uw eigen e-mailadres in.',
        'message_validate_email_1' => 'Het veld Email is verplicht, vul uw eigen e-mailadres in.',

        // Shop settings.
        'shopSettingsHeader' => 'Over uw winkel',
        'desc_shopSettings' => 'Met behulp van deze instellingen kan de koppeling beter: het <a href="https://wiki.acumulus.nl/index.php?page=127" target="_blank">factuurtype</a> bepalen; controles uitvoeren; en BTW tarieven terugrekenen.',

        'field_digitalServices' => 'Verkoopt u digitale diensten?',
        'option_digitalServices_1' => 'Zowel digitale diensten als normale producten.',
        'option_digitalServices_2' => 'Alleen producten die onder Nederlandse BTW vallen.',
        'option_digitalServices_3' => 'Alleen digitale diensten die met buitenlandse BTW belast moeten worden voor buitenlandse klanten.',
        'desc_digitalServices' => 'Geef aan of u in uw winkel digitale diensten aanbiedt waarbij u buitenlandse BTW moet hanteren voor EU klanten.
Zie <a href="http://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/zakendoen_met_het_buitenland/goederen_en_diensten_naar_andere_eu_landen/btw_berekenen_bij_diensten/wijziging_in_digitale_diensten_vanaf_2015/wijziging_in_digitale_diensten_vanaf_2015" target="_blank">Belastingdienst: diensten naar andere EU landen</a>.',

        'field_vatFreeProducts' => 'Verkoopt u van BTW vrijgestelde producten of diensten?',
        'option_vatFreeProducts_1' => 'Zowel BTW vrije als aan BTW onderhevige producten en/of diensten.',
        'option_vatFreeProducts_2' => 'Alleen aan BTW onderhevige producten en/of diensten.',
        'option_vatFreeProducts_3' => 'Alleen producten of diensten die van BTW vrijgesteld zijn.',
        'desc_vatFreeProducts' => 'Geef aan of u in uw winkel producten en/of diensten aanbiedt die vrijgesteld zijn van BTW, bv. onderwijs.',

        // Trigger settings.
        'triggerSettingsHeader' => 'Wanneer wilt u uw facturen automatisch versturen naar Acumulus',
        'desc_triggerSettings' => 'Met behulp van deze instelling kunt u aangeven op welk(e) moment(en) u de factuur voor een bestelling naar Acumulus wilt versturen. Als u meerdere momenten selecteert, wordt de factuur naar Acumulus verstuurd zodra de bestelling één van de gekozen statussen bereikt. Een factuur zal altijd slechts 1 keer naar Acumulus worden verstuurd. Deze koppeling gebruikt alleen gegevens van de bestelling, dus u kunt elke status kiezen. De webwinkelfactuur hoeft dus nog niet aangemaakt te zijn, tenzij u voor de factuurdatum en nummer de webwinkelfactuurdatum en nummer wilt gebruiken. Als u voor "Niet automatisch versturen" kiest, dient u de facturen zelf over te zetten m.b.v. het <a href="%s">Acumulus batchverzendformulier</a>.',

        'field_triggerOrderStatus' => 'Bestelstatus(sen)',
        'desc_triggerOrderStatus' => 'Mbv de "Ctrl" toets kunt u meerdere statussen kiezen.',
        'option_empty_triggerOrderStatus' => 'Niet automatisch versturen',

        'field_triggerInvoiceEvent' => 'Webshopfactuur status',
        'option_triggerInvoiceEvent_0' => 'Niet automatisch versturen.',
        'option_triggerInvoiceEvent_1' => 'Als een factuur van de webwinkel aangemaakt wordt.',
        'option_triggerInvoiceEvent_2' => 'Als een factuur van de webwinkel naar de klant verzonden wordt.',
        'desc_triggerInvoiceEvent' => 'U kunt hier kiezen of en bij welke webwinkelfactuur-gebeurtenissen de factuur naar Acumulus wordt verstuurd. Als u voor "Niet automatisch versturen" kiest, kunt u de facturen zelf overzetten m.b.v. het batchformulier of op basis van één of meerdere bestelstatussen.',

        // Tokens
        'tokenHelpHeader' => 'Tokens',
        'desc_tokens' => '<p>Op deze pagina staan een aantal velden die "tokens" mogen bevatten.
Dit wil zeggen dat ze naast vrije tekst ook gegevens van de bestelling, de klant of het klantadres kunnen bevatten.
Tokens worden ingegeven door de naam van de eigenschap van de bestelling tussen vierkante haken, dwz. [ en ], te plaatsen.
De eigenschappen die uw webshop kent worden hieronder opgesomd.</p>
<p>Om speciale situaties aan te kunnen, mogen tokens op verschillende manieren samengevoegd worden:</p>
<ol class="property-list">
<dt>[property]:</dt><dd>Eenvoudigste vorm, vervang door de waarde van deze eigenschap.</dd>
<dt>[property1|property2|...]:</dt><dd>Vervang door de waarde van property1 of als deze geen waarde heeft door die van property2, en zo verder. BV: handig om of het mobiele of het vaste telefoonnummer mee te sturen.</dd>
<dt>[property1+property2+...]:</dt><dd>Vervang door de waarde van property1 en die van property2 en plaats tussen de properties een spatie, maar alleen als de properties niet leeg zijn.BV: handig om de volledige naam, opgebouwd uit voornaam, tussenvoegsel en achternaam, te versturen zonder dat er meerdere spaties in terecht komen.</dd>
<dt>[object::property]:</dt><dd>Vervang alleen door de eigenschap als die in het opgegeven object voorkomt (zie de lijst hieronder). Gebruik dit om verwarring te voorkomen als meerdere objecten een eigenschap met dezelfde naam hebben (bv id).</dd>
</ol>
',
        'msg_token' => 'Dit veld mag tokens bevatten.',
        'see_class' => 'zie de class %1$s',
        'see_file' => 'zie het bestand %1$s',
        'see_class_file' => 'zie de class %1$s in het bestand %2$s',
        'see_table' => 'zie de tabel %1$s',
        'see_class_more' => 'zie de class %1$s voor mogelijk andere properties en methods zonder argumenten die als token gebruikt kunnen worden',
        'see_table_more' => 'zie de tabel %1$s voor mogelijk andere velden die als token gebruikt kunnen worden',
        'see_above' => 'zie hierboven.',
        'original_order_for_refund' => 'Oorspronkelijke bestelling, alleen beschikbaar bij refunds',

        // Relation management settings.
        'relationSettingsHeader' => 'Relatiebeheer',
        'desc_relationSettingsHeader' => 'Met elke factuur die naar Acumulus verstuurd word, worden ook de klantgegevens meegestuurd. Hier kunt u instellen hoe dit precies dient te gebeuren.',

        'field_defaultCustomerType' => 'Importeer klanten als',

        'field_contactStatus' => 'Actief',
        'desc_contactStatus' => 'Geef aan of relaties als actief of inactief opgeslagen moeten worden.',
        'option_contactStatus_Active' => 'Ja',
        'option_contactStatus_Disabled' => 'Nee',

        'field_contactYourId' => 'Klantreferentie v/d webshop',
        'desc_contactYourId' => 'Als u van een relatie in Acumulus de webshopgegevens wilt opzoeken is het handig als Acumulus het voor de webshop unieke klantnummer ook heeft. Met behulp van dit veld wordt deze referentie in Acumulus opgeslagen. Deze kan ook op factuursjablonen gebruikt worden.',

        'field_salutation' => 'Volledige aanhef',
        'desc_salutation' => 'U kunt hier de aanhef specificeren zoals u die wilt gebruiken als u communiceert met deze klant.',

        'field_clientData' => 'Klantadresgegevens',
        'option_sendCustomer' => 'Uw (niet zakelijke) klanten automatisch aan uw relaties in Acumulus toevoegen.',
        'option_overwriteIfExists' => 'Overschrijf bestaande adresgegevens.',
        'desc_clientData' => 'Binnen Acumulus is het mogelijk om uw klantrelaties te beheren.
Deze koppeling voegt automatisch uw klanten aan het relatieoverzicht van Acumulus toe.
Dit is niet altijd gewenst en kunt u voorkomen door de eerste optie uit te zetten.
Hierdoor worden alle transacties van consumenten binnen uw webwinkel onder 1 vaste fictieve relatie ingeboekt in Acumulus.
De tweede optie moet u alleen uitzetten als u direct in Acumulus adresgegevens van uw webwinkel-klanten bijwerkt.
Als u de eerste optie heeft uitgezet, geldt de tweede optie alleen voor uw zakelijke klanten.',

        // Invoice settings.
        'invoiceSettingsHeader' => 'Uw factuurinstellingen',
        'option_empty' => 'Maak uw keuze',
        'option_use_default' => 'Gebruik standaard',

        'field_invoiceNrSource' => 'Factuurnummer',
        'option_invoiceNrSource_1' => 'Gebruik het factuurnummer van uw webwinkel. Let op: als er nog geen factuur aan een bestelling gekoppeld is, zal het bestelnummer gebruikt worden!',
        'option_invoiceNrSource_2' => 'Gebruik het bestelnummer van uw webwinkel.',
        'option_invoiceNrSource_3' => 'Laat Acumulus het factuurnummer bepalen.',
        'desc_invoiceNrSource' => 'U kunt hier kiezen welk nummer Acumulus als factuurnummer moet gebruiken.',

        'field_dateToUse' => 'Factuurdatum',
        'option_dateToUse_1' => 'Gebruik de aanmaakdatum van de factuur. Let op: als er nog geen factuur aan uw bestelling gekoppeld is, zal de aanmaakdatum van de bestelling gebruikt worden!',
        'option_dateToUse_2' => 'Gebruik de aanmaakdatum van de bestelling.',
        'option_dateToUse_3' => 'Gebruik de datum van het overzetten.',
        'desc_dateToUse' => 'U kunt hier kiezen welke datum de factuur in Acumulus moet krijgen.',

        'field_defaultAccountNumber' => 'Standaard rekening',
        'desc_defaultAccountNumber' => 'Kies de rekening waarop u standaard de facturen van deze winkel wilt boeken. Verderop kunt u per betaalmethode een afwijkende rekening kiezen.',

        'field_defaultCostCenter' => 'Standaard kostenplaats',
        'desc_defaultCostCenter' => 'Kies de kostenplaats waarop u standaard de facturen van deze winkel wilt boeken. Verderop kunt u per betaalmethode een afwijkende kostenplaats kiezen.',

        'field_defaultInvoiceTemplate' => 'Factuur-sjabloon (niet betaald)',
        'field_defaultInvoicePaidTemplate' => 'Factuur-sjabloon (betaald)',
        'option_same_template' => 'Zelfde sjabloon als voor niet betaald',
        'desc_defaultInvoiceTemplate' => 'Maakt u binnen Acumulus gebruik van meerdere factuur-sjablonen en wilt u de facturen uit uw webwinkel met een specifieke factuursjabloon printen, kies dan hier de factuur-sjablonen voor niet betaalde respectievelijk betaalde bestellingen.',

        // Options settings.
        'optionsSettingsHeader' => 'Opties of varianten',
        'desc_optionsSettingsHeader' => 'Als een product opties of varianten heeft, kunnen deze opties op dezelfde regel als het product komen of op aparte regels daaronder. 
            Met onderstaande instellingen kunt u aangeven wanneer voor alles op dezelfde regel gekozen wordt en wanneer voor meerdere regels.',
        'desc_composedProducts' => 'NB: als het een samengesteld product betreft en de prijsinformatie is alleen aanwezig op de subproducten, of de subproducten hebben verschillende BTW tarieven, dan komen alle subproducten op hun eigen regel, ongeacht deze instellingen.',
        'option_do_not_use' => 'Deze instelling negeren',
        'option_always' => 'Altijd',
        'field_optionsAllOn1Line' => 'T/m dit aantal opties bij hoofdproduct',
        'desc_optionsAllOn1Line' => 'Als het aantal opties van het product gelijk is aan of minder is dan deze waarde komen de opties altijd bij het hoofdproduct, ongeacht de maximale lengte die u hieronder kunt opgeven.',
        'field_optionsAllOnOwnLine' => 'Vanaf dit aantal opties op aparte regels',
        'desc_optionsAllOnOwnLine' => 'Als het aantal opties gelijk is aan of groter is dan deze waarde komen alle opties altjd op hun eigen regel, ongeacht de maximale lengte die u hieronder kunt opgeven.',
        'field_optionsMaxLength' => 'Lengte omschrijving',
        'desc_optionsMaxLength' => 'Als het aantal opties tussen bovenstaande 2 waardes ligt, bepaalt de totale lengte (in aantal letters) van de omschrijvingen van de opties of deze het bij hoofdproduct geplaatst worden of toch op aparte regels.',
        'message_validate_options_0' => 'De velden "T/m dit aantal opties bij hoofdproduct" en "Vanaf dit aantal opties op aparte regels" kunnen niet allebei op "Altijd" staan.',
        'message_validate_options_1' => 'Het veld "Vanaf dit aantal opties op aparte regels" dient groter dan het veld "T/m dit aantal opties bij hoofdproduct" te zijn.',
        'message_validate_options_2' => 'Het veld "Lengte omschrijving" dient een getal te zijn.',

        'field_sendWhat' => 'Verstuur',
        'option_sendEmptyInvoice' => 'Verstuur 0-bedrag facturen.',
        'option_sendEmptyShipping' => 'Verstuur "gratis verzending" of "zelf afhalen" regels.',
        'desc_sendWhat' => 'Met de eerste optie geeft u aan of u 0-bedrag facturen naar Acumulus wilt versturen. Om het overzicht compleet te houden en om geen gaten in de factuurnummering te krijgen staat deze optie normaal gesproken aan. De 2e optie beperkt zicht tot het wel of niet versturen van een gratis verzending of afhelen regel binnen een factuur. Omdat Acumulus pakbonnen kan printen waar de verzendmethode op moet staan, staat deze optie normaal gesproken aan.',

        // Settings per payment method.
        'paymentMethodAccountNumberFieldset' => 'Rekening per betaalmethode',
        'desc_paymentMethodAccountNumberFieldset' => 'Hieronder kunt u per actieve betaalmethode een rekening opgeven. De standaard rekening hierboven wordt gebruikt voor betaalmethoden waarvoor u geen specifieke rekening opgeeft.',

        'paymentMethodCostCenterFieldset' => 'Kostenplaats per betaalmethode',
        'desc_paymentMethodCostCenterFieldset' => 'Hieronder kunt u per actieve betaalmethode een kostenplaats opgeven. De standaard kostenplaats hierboven wordt gebruikt voor betaalmethoden waarvoor u geen specifieke kostenplaats opgeeft.',

        // Email as pdf settings.
        'emailAsPdfSettingsHeader' => 'PDF Factuur',
        'desc_emailAsPdfSettings' => 'Bij het versturen van bestellinggegevens naar Acumulus, kunt u Acumulus een PDF factuur laten versturen naar uw klant. Deze wordt direct verstuurd naar het door de klant opgegeven emailadres.',

        'field_emailAsPdf' => 'Optie inschakelen',
        'option_emailAsPdf' => 'Verstuur de factuur als PDF vanuit Acumulus.',
        'desc_emailAsPdf' => 'Als u deze optie aanvinkt, kunt u de overige opties gebruiken om de emailverzending aan uw wensen aan te passen. Het bericht in de email body kunt u niet hier instellen, dat kunt u in Acumulus doen onder "Beheer - Factuur-sjablonen".',

        'field_emailFrom' => 'Afzender',
        'desc_emailFrom' => 'Het email adres dat als afzender gebruikt moet worden. Als u dit leeg laat wordt het emailadres uit het Acumulus sjabloon gebruikt. Wij adviseren dit veld leeg te laten.',
        'message_validate_email_4' => 'Het veld Afzender is geen valide e-mailadres, vul een correct e-mailadres in.',

        'field_subject' => 'Onderwerp',
        'desc_subject' => 'Het onderwerp van de email. Als u dit leeg laat wordt "Factuur [factuurnummer] Bestelling [bestelnummer]" gebruikt. U kunt [#b] gebruiken om het bestelnummer in de onderwerpregel te plaatsen en [#f] voor het factuurnummer (van de webshop, niet van Acumulus).',

        'field_emailBcc' => 'BCC',
        'desc_emailBcc' => 'Additionele emailadressen om de factuur naar toe te sturen, bv. het emailadres van uw eigen administratie-afdeling. Als u dit leeg laat wordt de factuur alleen naar de klant verstuurd.',
        'message_validate_email_3' => 'Het veld BCC is geen valide e-mailadres, vul een correct e-mailadres in.',

        // Plugin settings.
        'pluginSettingsHeader' => 'Plugin instellingen',

        'field_debug' => 'Factuur verzendmodus',
        'option_debug_1' => 'Ontvang alleen een mail bij fouten of waarschuwingen tijdens het verzenden van een factuur naar Acumulus.',
        'option_debug_2' => 'Ontvang altijd een mail met de resultaten bij het verzenden van een factuur naar Acumulus.',
        'option_debug_3' => 'Verstuur facturen in test modus naar Acumulus. Acumulus zal alleen de factuur controleren op fouten en waarschuwingen maar zal deze niet opslaan.',
        'option_debug_4' => 'Verzend berichten naar Acumulus maar ontvang wel een mail met het bericht zoals dat vestuurd zou zijn.',
        'desc_debug' => 'U kunt hier een verzend modus kiezen. Kies voor de eerste optie tenzij u i.v.m. een supportverzoek bent geïnstrueerd om iets anders te kiezen.',

        'field_logLevel' => 'Logniveau',
        'option_logLevel_3' => 'Log foutmeldingen, waarschuwingen en operationele mededelingen.',
        'option_logLevel_4' => 'Log foutmeldingen, waarschuwingen en operationele en informatieve mededelingen.',
        'option_logLevel_5' => 'Log foutmeldingen, waarschuwingen, mededelingen, en communicatieberichten.',
        'desc_logLevel' => 'U kunt hier een logniveau kiezen. Kies voor de 1e of 2e optie tenzij u i.v.m. een supportverzoek bent geïnstrueerd om iets anders te kiezen.',

        // Plugin version information.
        'versionInformationHeader' => 'Informatie over uw webshop en deze module',
        'desc_versionInformation' => 'Vermeld aub deze gegevens bij een supportverzoek.',

        // Link to other config form.
        'desc_advancedSettings' => 'Deze plugin kent veel instellingen en daarom bevat deze pagina niet alle instellingen. Een aantal minder gebruikte instellingen vindt u in het "%1$s" onder "%2$s". Nadat u hier de gegevens hebt ingevuld en opgeslagen, kunt u het andere formulier bezoeken:',
        'menu_advancedSettings' => 'Instellingen → Acumulus geavanceerde instellingen',

        'desc_basicSettings' => 'Dit is het formulier met geavanceerde, ofwel minder gebruikte, instellingen. De basisinstellingen vindt u in het "%1$s" onder "%2$s", of via de button hieronder. Let op: als u op deze button klikt worden de op deze pagina ingevulde of gewijzigde gegevens NIET opgeslagen!',
        'menu_basicSettings' => 'Instellingen → Acumulus',
    );

    protected $en = array(
        // Titles, headers, links, buttons and messages.
        'config_form_title' => 'Acumulus | Settings',
        'config_form_header' => 'Acumulus settings',
        'config_form_link_text' => 'Acumulus basic settings',

        'advanced_form_title' => 'Acumulus | Advanced settings',
        'advanced_form_header' => 'Acumulus advanced settings',
        'advanced_form_link_text' => 'Acumulus advanced settings',

        'button_save' => 'Save',
        'button_cancel' => 'Back',

        'message_validate_email_2' => 'The field (fictitious customer) Email is not a valid e-mail address, please fill in a correct e-mail address.',
        'message_validate_conflicting_options' => 'If you don\'t send customer data to Acumulus, Acumulus cannot send PDF invoices. Change one of the options.',

        'message_form_success' => 'The settings are saved.',
        'message_form_error' => 'an error occurred wile saving the settings.',
        'message_uninstall' => 'Are you sure to delete the configuration settings?',

        'message_error_header' => 'Error in your Acumulus connection settings',
        'message_error_auth' => 'Your Acumulus connection settings are incorrect. Please check them. After you have entered the correct connection settings %2$s, the %1$s settings will be shown.',
        'message_error_comm' => 'The module encountered an error retrieving your Acumulus configuration. Please try again. When the connection is restored the %1$s settings will be shown as well.',
        'message_auth_unknown' => 'When you have filled in your Acumulus connection settings %2$s, the %1$s settings will be shown as well.',
        'message_error_arg1_config' => 'other',
        'message_error_arg1_advanced' => 'advanced',
        'message_error_arg2_config' => 'here',
        'message_error_arg2_advanced' => 'in the "Acumulus basic settings form"',

        // Account settings.
        'accountSettingsHeader' => 'Your Acumulus connection settings',
        'desc_accountSettings' => 'Enter the details you received by email, or even better: create an additional user in Acumulus under "Beheer → Gebruikers → Gebruiker toevoegen" having "api - koppeling" as "Gebruikerstype". Such a user has less permissions and is thus more secure.',

        'field_code' => 'Contract code',
        'field_username' => 'User name',
        'field_password' => 'Password',
        'field_email' => 'E-mail',
        'desc_email' => 'The e-mail address at which you will be informed about any errors that occur during invoice sending. As this module cannot know if it is called from an interactive administrator screen, it will not display any messages in the user interface. Therefore you have to fill in an e-mail address.',
        'message_validate_contractcode_0' => 'The field Contract code is required, please fill in the contract code you use to log in to Acumulus.',
        'message_validate_contractcode_1' => 'The field Contract code is a numeric field, please fill in the contract code you use to log in to Acumulus.',
        'message_validate_username_0' => 'The field User name is required, please fill in the user name you use to log in to Acumulus.',
        'message_validate_username_1' => 'The field User name contains spaces at the start or end. This is allowed but are you sure that you meant to do so?',
        'message_validate_password_0' => 'The field Password is required, please fill in the password you use to log in to Acumulus.',
        'message_validate_password_1' => 'The field Password contains spaces at the start or end. This is allowed but are you sure that you meant to do so?',
        'message_validate_password_2' => 'The field Password contains a character that is forbidden by Acumulus (`\'"#%&;<>\\). Are you sure that you typed the correct password?',
        'message_validate_email_0' => 'The field Email is not a valid e-mail address, please fill in your own e-mail address.',
        'message_validate_email_1' => 'The field Email is required, please fill in your own e-mail address.',

        // Shop settings.
        'shopSettingsHeader' => 'About your shop',
        'desc_shopSettings' => 'With these settings, this plugin is better able to: determine the <a href="https://wiki.acumulus.nl/index.php?page=127" target="_blank">invoice type</a>; perform some sanity checks; and to compute VAT rates.',

        'field_digitalServices' => 'Do you sell digital services?',
        'option_digitalServices_1' => 'Both digital services and normal products.',
        'option_digitalServices_2' => 'Only products that are subject to dutch VAT.',
        'option_digitalServices_3' => 'Only digital services subject to the regulations concerning using foreign VAT rates.',
        'desc_digitalServices' => 'Select whether your store offers digital services that are subject to foreign VAT for clients in other EU countries.
See <a href="http://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/zakendoen_met_het_buitenland/goederen_en_diensten_naar_andere_eu_landen/btw_berekenen_bij_diensten/wijziging_in_digitale_diensten_vanaf_2015/wijziging_in_digitale_diensten_vanaf_2015">Dutch tax office: services to other EU countries (in dutch)</a>.
Using this setting this plugin can better determine the invoice type; perform some validations; and extract exact VAT rates where they are calculated using rounded amounts.',

        'field_vatFreeProducts' => 'Do you sell  VAT free products or services?',
        'option_vatFreeProducts_1' => 'Both VAT free and VAT liable products or services.',
        'option_vatFreeProducts_2' => 'Only products or services that are VAT liable.',
        'option_vatFreeProducts_3' => 'Only VAT free products or services.',
        'desc_vatFreeProducts' => 'Select whether your store offers products or services that are VAT free, e.g. education.
Using this setting this plugin can better determine the invoice type and perform some validations.',

        // Trigger settings.
        'triggerSettingsHeader' => 'When to have your invoices sent to Acumulus.',
        'desc_triggerSettings' => 'This(these) setting(s) determine(s) at what instants the invoice for an order should be sent to Acumulus. If you select multiple instants, the invoice wil be sent as soon as the order reaches one of the selected statuses. Note that an invoice will only be sent once to Acumulus. This extension only uses order data, so you may select any status, the webshop invoice does not already have to be created,unless you want to use the webshop\'s invoice date and number as invoice date and number for the Acumulus invoice. If you select "Do not send automatically" you will have to use the <a href="%s">Acumulus batch send form</a>.',

        'field_triggerOrderStatus' => 'Order state(s)',
        'desc_triggerOrderStatus' => 'Select if and with which order states to send the invoice to Acumulus. If you select multiple states, the invoice will only be sent once as soon as one of the selected states is reached. This extension only uses order data, so you may select any status, the webshop invoice does not already have to be created. If you select "Do not send automatically" you will have to use the send batch form.',
        'option_empty_triggerOrderStatus' => 'Do not send automatically ',

        'field_triggerInvoiceEvent' => 'Webshop invoice state',
        'option_triggerInvoiceEvent_0' => 'Do not send automatically.',
        'option_triggerInvoiceEvent_1' => 'When the webshop invoice gets created.',
        'option_triggerInvoiceEvent_2' => 'When the webshop invoice gets sent to the customer.',
        'desc_triggerInvoiceEvent' => 'Select if and on which webshop invoice event to send the invoice to Acumulus. If you select "Do not send automatically" you can use the send batch form, or you can set one or more order states above to trigger the sending of the invoice.',

        // Relation management settings.
        'relationSettingsHeader' => 'Relation management',
        'desc_relationSettingsHeader' => 'With each invoice sent to Acumulus, its client data is sent as well. With these settings you can influence how this is done.',

        'field_defaultCustomerType' => 'Create customers as',

        'field_contactStatus' => 'Active',
        'desc_contactStatus' => 'Indicate whether relations should be saved as active or inactive',
        'option_contactStatus_Active' => 'Yes',
        'option_contactStatus_Disabled' => 'No',

        'field_salutation' => 'Full salutations',
        'desc_salutation' => 'Specify the salutations you want to use when communicating with this client. You can use [#property] to get a property value out of the order or customer values, e.g. "Dear [#firstName],".',

        'field_clientData' => 'Customer address data',
        'option_sendCustomer' => 'Send consumer client records to Acumulus.',
        'option_overwriteIfExists' => 'Overwrite existing address data.',
        'desc_clientData' => 'Acumulus allows you to store client data.
This extension automatically sends client data to Acumulus.
If you don\'t want this, uncheck this option.
All consumer invoices will be booked on one and the same fictitious client.
You should uncheck the second option if you edit customer address data manually in Acumulus.
If you unchecked the first option, the second option only applies to business clients.',

        // Invoice settings.
        'invoiceSettingsHeader' => 'Your invoice settings',
        'option_empty' => 'Select one',
        'option_use_default' => 'Use default',

        'field_invoiceNrSource' => 'Invoice number',
        'option_invoiceNrSource_1' => 'Use the web shop invoice number. Note: if no invoice has been created for the order yet, the order number will be used!',
        'option_invoiceNrSource_2' => 'Use the web shop order number as invoice number.',
        'option_invoiceNrSource_3' => 'Have Acumulus create an invoice number.',
        'desc_invoiceNrSource' => 'Select which number to use for the invoice in Acumulus.',

        'field_dateToUse' => 'Invoice date',
        'option_dateToUse_1' => 'Use the invoice date. Note: if no invoice has been created for the order yet, the order create date will be used!',
        'option_dateToUse_2' => 'Use the order create date.',
        'option_dateToUse_3' => 'Use the transfer date.',
        'desc_dateToUse' => 'Select which date to use for the invoice in Acumulus.',

        'field_defaultAccountNumber' => 'Default account',
        'desc_defaultAccountNumber' => 'Select the default account to which you want to book this shop\'s invoices. Further down you can select an alternative account per payment method.',

        'field_defaultCostCenter' => 'Default cost center',
        'desc_defaultCostCenter' => 'Select the cost center to to which you want to book this shop\'s invoices. Further down you can select an alternative cost center per payment method',

        'field_defaultInvoiceTemplate' => 'Invoice template (due)',
        'field_defaultInvoicePaidTemplate' => 'Invoice template (paid)',
        'option_same_template' => 'Same template as for due',
        'desc_defaultInvoiceTemplate' => 'Select the invoice templates to use when generating your Acumulus invoices for due respectively paid orders.',

        // Options settings.
        'optionsSettingsHeader' => 'Options or variants',
        'desc_optionsSettingsHeader' => 'For products that have options or variants, the options can be placed on the same line as the product or on separate lines below.
            These settings allow you to decide when to use only 1 line and when to use separate lines.',
        'desc_composedProducts' => 'BTW: if this is a composed product en the price information is only available on the sub products, or the sub products have different vat rates, all sub products will always be placed on their own line.',
        'option_do_not_use' => 'Ignore this setting',
        'option_always' => 'Always',
        'field_optionsAllOn1Line' => 'Up to this no. of options on main product',
        'desc_optionsAllOn1Line' => 'If the number of options is less than or equal to this value, they will always be placed on the main product, regardless the length setting below.',
        'field_optionsAllOnOwnLine' => 'As of this no. of options on separate lines',
        'desc_optionsAllOnOwnLine' => 'If the number of options is more than or equal to this value, they will always be placed on their own lines, regardless the length setting below.',
        'field_optionsMaxLength' => 'Length of description',
        'desc_optionsMaxLength' => 'If the no. of options lies between the above 2 values, the total length (in characters) of the descriptions of the options determines whether they will be placed on the main product or on their own lines.',
        'message_validate_options_0' => 'The fields "Up to this no. of options on main product" and "As of this no. of options on separate lines" cannot both be set to "Always".',
        'message_validate_options_1' => 'The field "As of this no. of options on separate lines" should be greater than or equal to "Up to this no. of options on main product".',
        'message_validate_options_2' => 'The field "Length of description" should be a number.',

        'field_sendWhat' => 'Send',
        'option_sendEmptyInvoice' => 'Send 0-amount invoices.',
        'option_sendEmptyShipping' => 'Send "free shipping" or "in store pick-up" lines.',
        'desc_sendWhat' => 'The 1st option indicates if 0-amount invoices should be sent to Acumulus. You should normally enable this option to keep the invoice collection complete and prevent missing invoice numbers. The 2nd option determines whether to send free shipping or in store pickup lines. You should normally enable this option as Acumulus can print packing slips.',

        // Settings per payment method.
        'paymentMethodAccountNumberFieldset' => 'Account per payment method',
        'desc_paymentMethodAccountNumberFieldset' => 'Below you can enter an account to use per (active) payment method. The default above serves as fallback for payment methods for which you do not specify an account.',

        'paymentMethodCostCenterFieldset' => 'Cost center per payment method',
        'desc_paymentMethodCostCenterFieldset' => 'Below you can enter a cost center to use per (active) payment method.  The default above serves as fallback for payment methods for which you do not specify a cost center.',

        // Email as pdf settings.
        'emailAsPdfSettingsHeader' => 'PDF Invoice',
        'desc_emailAsPdfSettings' => 'On sending the order details to Acumulus, Acumulus can send a PDF invoice to your customer. The mail will be sent to the clients\' email address.',

        'field_emailAsPdf' => 'Enable the feature',
        'option_emailAsPdf' => 'Have Acumulus send the invoice as PDF.',
        'desc_emailAsPdf' => 'If you check this option, you can use the other options below to configure the emails to your preferences. However, to configure the text in them mail body, go to Acumulus to "Beheer - Factuur-sjablonen".',

        'field_emailFrom' => 'Sender',
        'desc_emailFrom' => 'The email address to use as sender. If you leave this empty, the email address of the Acumulus template will be used. We advice you to leave this empty.',
        'message_validate_email_4' => 'The field Sender is not a valid e-mail address, please fill in a valid e-mail address.',

        'field_subject' => 'Subject',
        'desc_subject' => 'The subject line of the email. If you leave this empty "Invoice [invoice#] Order [order#]" will be used. You can use [#b] to place the order number in the subject and [#f] for the invoice number (from the webshop, not Acumulus).',

        'field_emailBcc' => 'BCC',
        'desc_emailBcc' => 'Additional email addresses to send the invoice to, e.g. the email address of your own administration department. If you leave this empty the invoice email will only be sent to your client.',
        'message_validate_email_3' => 'The field BCC is not a valid e-mail address, please fill in a valid e-mail address.',

        // Plugin settings.
        'pluginSettingsHeader' => 'Plugin settings',

        'field_debug' => 'Invoice send mode',
        'option_debug_1' => 'Only receive a mail when there are errors or warnings on sending an invoice to Acumulus.',
        'option_debug_2' => 'Always receive a mail with the results on sending an invoice to Acumulus.',
        'option_debug_3' => 'Send invoices to Acumulus in test mode. Acumulus will only check the input for errors and warnings but not store the invoice.',
        'option_debug_4' => 'Do not send messages to Acumulus, but receive a mail with the message as would have been sent.',
        'desc_debug' => 'Select a debug mode. Choose for the 1st option unless otherwise instructed by support staff.',

        'field_logLevel' => 'Log level',
        'option_logLevel_3' => 'Log error messages, warnings, and operational notices.',
        'option_logLevel_4' => 'Log error messages, warnings, and operational and informational notices.',
        'option_logLevel_5' => 'Log error messages, warnings, notices, and communication messages.',
        'desc_logLevel' => 'Select a log level. Choose for the 1st or 2nd option unless otherwise instructed by support staff.',

        // Plugin version information.
        'versionInformationHeader' => 'Information about your webshop and this module',
        'desc_versionInformation' => 'Please mention this information with any support request.',

        // Link to other config form.
        'desc_advancedSettings' => 'This plugin is highly configurable and therefore this form does not contain all settings. You can find the other settings in the "%1$s" under "%2$s". Once you have completed and saved the settings over here, you can visit that form to fill in the advanced settings.',
        'menu_advancedSettings' => 'Settings → Acumulus advanced settings',

        'desc_basicSettings' => 'This is the form with advanced, i.e. less commonly used, settings. You can find the basic settings in the "%1$s" under "%2$s", or via the button below. Note: if you click on this button, changes you made to this page will NOT be saved!',
        'menu_basicSettings' => 'Settings → Acumulus',
    );
}
