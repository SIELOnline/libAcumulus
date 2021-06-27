<?php
/** @noinspection HtmlUnknownTarget */

namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for the configuration form.
 */
class ConfigFormTranslations extends TranslationCollection
{
    protected $nl = [
        // Titles, headers, links, buttons and messages.
        'config_form_title' => 'Acumulus | Instellingen',
        'config_form_header' => 'Acumulus instellingen',
        'config_form_link_text' => 'Acumulus basisinstellingen',

        'advanced_form_title' => 'Acumulus | Geavanceerde Instellingen',
        'advanced_form_header' => 'Acumulus geavanceerde instellingen',
        'advanced_form_link_text' => 'Acumulus geavanceerde instellingen',

        'button_submit_config'=> 'Instellingen opslaan',
        'button_submit_advanced'=> 'Instellingen opslaan',
        'button_link' => '<a href="%2$s">%1$s</a>',
        'button_cancel' => 'Terug',

        'message_form_config_success' => 'De instellingen zijn opgeslagen.',
        'message_form_config_error' => 'Er is een fout opgetreden bij het opslaan van de instellingen',

        'message_form_advanced_success' => 'De instellingen zijn opgeslagen.',
        'message_form_advanced_error' => 'Er is een fout opgetreden bij het opslaan van de instellingen',

        'message_uninstall' => 'Wilt u de configuratie-instellingen verwijderen?',

        'message_error_header' => 'Fout in uw Acumulus accountgegevens',
        'message_error_auth' => 'Uw Acumulus accountgegevens zijn onjuist. Zodra u %2$s de correcte gegevens hebt ingevuld, worden hier de %1$s instellingen getoond.',
        'message_error_forb' => 'Uw Acumulus accountgegevens zijn juist maar staan geen toegang via de web service toe. Zodra u %2$s correcte gegevens hebt ingevuld, worden hier de %1$s instellingen getoond.',
        'message_error_comm' => 'Er is een fout opgetreden bij het ophalen van uw gegevens van Acumulus. Probeer het later nog eens. Zodra de verbinding hersteld is worden hier de %1$s instellingen getoond.',
        'message_auth_unknown' => 'Zodra u %2$s uw Acumulus accountgegevens hebt ingevuld, worden hier de %1$s instellingen getoond.',
        'message_error_arg1_config' => 'basis',
        'message_error_arg1_advanced' => 'geavanceerde',
        'message_error_arg2_config' => 'hier',
        'message_error_arg2_advanced' => 'in het "Acumulus basisinstellingenformulier"',
        'message_warning_role_deprecated' => 'U gebruikt accountgegevens met een gebruikerstype dat binnenkort niet meer gebruikt kan worden om de Acumulus API mee te benaderen. Voeg een andere gebruiker toe met gebruikerstype API-Gebruiker of verander het gebruikerstype van de huidige gebruiker.',
        'message_warning_role_insufficient' => 'U gebruikt een account met het gebruikerstype API-Invoerder. Dit gebruikerstype heeft niet alle rechten die deze plugin gebruikt. Verander het gebruikerstype van de huidige gebruiker naar API-Gebruiker of voeg een andere gebruiker toe.',
        'message_warning_role_overkill' => 'U gebruikt een account met het gebruikerstype API-Beheerder. Dit gebruikerstype heeft meer rechten dan deze plugin nodig heeft. Wij raden u aan om het gebruikerstype te veranderen naar API-Gebruiker.',

        // Register.
        'config_form_register' => 'U heeft nog geen accountgegevens ingevuld. Als u nog geen account heeft kunt u vanuit deze %1$s een proefaccount aanmaken:',
        'config_form_register_button' => '<a class="%2$s" href="%1$s">Nu vrijblijvend een gratis Acumulus proefaccount aanmaken</a>',

        // Account settings.
        'accountSettingsHeader' => 'Uw Acumulus account',
        'desc_accountSettings_N' => 'Als u al wel een account hebt, kunt u hieronder de gegevens invullen.',
        'desc_accountSettings_F' => 'De ingevulde accountgegevens zijn onjuist, verbeter ze.',
        'desc_accountSettings_auth' => 'Als u nog geen account heeft kunt u hier een <a href="%1$s">gratis proefaccount aanvragen</a>.',
        'desc_accountSettings_T' => 'Deze %s heeft zich succesvol aangemeld met deze gegevens.',

        'field_code' => 'Contractcode',
        'field_username' => 'Gebruikersnaam',
        'desc_username' => 'Let op dat u de gegevens van een gebruiker van het type "API-gebruiker" invoert. Als u die nog niet heeft, <a href="https://www.sielsystems.nl/" target="_blank">log dan in op Acumulus</a> en voeg een gebruiker toe onder "Beheer → Gebruikers → Gebruiker toevoegen". Vul "API - Gebruiker" in als "Gebruikerstype".',
        'field_password' => 'Wachtwoord',
        'field_emailonerror' => 'E-mail',
        'desc_emailonerror' => 'Het e-mailadres waarop u geïnformeerd wordt over fouten die zijn opgetreden tijdens het versturen van facturen. Omdat deze module niet kan weten of het vanuit een beheerscherm is aangeroepen, zal het geen berichten op het scherm plaatsen. Daarom is het invullen van een e-mailadres verplicht.',

        'message_validate_contractcode_0' => 'Het veld Contractcode is verplicht, vul de contractcode in die u ook gebruikt om in te loggen op Acumulus.',
        'message_validate_contractcode_1' => 'Het veld Contractcode is een numeriek veld, vul de contractcode in die u ook gebruikt om in te loggen op Acumulus.',
        'message_validate_username_0' => 'Het veld Gebruikersnaam is verplicht, vul een gebruikersnaam in waarmee u kan inloggen op de API van Acumulus.',
        'message_validate_username_1' => 'Het veld Gebruikersnaam bevat spaties aan het begin of eind. Dit is toegestaan, maar weet u zeker dat dit de bedoeling is?',
        'message_validate_password_0' => 'Het veld Wachtwoord is verplicht, vul het wachtwoord in dat u ook gebruikt om in te loggen op Acumulus.',
        'message_validate_password_1' => 'Het veld Wachtwoord bevat spaties aan het begin of eind. Dit is toegestaan, maar weet u zeker dat dit de bedoeling is?',
        'message_validate_password_2' => 'Het veld Wachtwoord bevat tekens die Acumulus verbiedt (`\'"#%&;<>\\). Weet u zeker dat u het juiste wachtwoord heeft ingetypt?',
        'message_validate_email_0' => 'Het veld E-mail bevat geen geldig e-mailadres, vul uw eigen e-mailadres in.',
        'message_validate_email_1' => 'Het veld E-mail is verplicht, vul uw eigen e-mailadres in.',
        'message_validate_required_field' => 'Het veld "%1$s" is verplicht, kies een waarde.',
        'message_validate_foreign_vat_classes_0' => 'Het veld "%1$s" is verplicht, kies één of meer waardes.',
        'message_validate_foreign_vat_classes_1' => 'De keuze "%2$s" voor het veld "%1$s" kan niet gecombineerd worden met andere waardes.',
        'message_validate_zero_vat_class_0' => 'U moet verschillende %1$s gebruiken om onderscheid te maken tussen producten of diensten aan 0%% btw en die zijn vrijgesteld van btw.',
        'message_validate_conflicting_shop_options_1' => 'Als u alleen diensten verkoopt, kunt u geen margegoederen verkopen. Pas één van deze opties aan.',
        'message_validate_conflicting_shop_options_2' => 'Als u alleen margegoederen verkoopt, vink dan bij "Soort" aan dat u alleen producten verkoopt.',

        // Shop settings.
        'shopSettingsHeader' => 'Over uw winkel',
        'desc_shopSettings' => 'Met behulp van deze instellingen kan de koppeling beter: het <a href="https://wiki.acumulus.nl/index.php?page=facturen-naar-het-buitenland" target="_blank">factuurtype</a> bepalen; controles uitvoeren; en btw tarieven terugrekenen.',

        'field_nature_shop' => 'Soort: wat verkoopt u?',
        'option_nature_1' => 'Zowel producten als diensten.',
        'option_nature_2' => 'Alleen producten.',
        'option_nature_3' => 'Alleen diensten.',
        'desc_nature_shop' => 'Geef aan of u in uw winkel producten en/of diensten aanbiedt. In Acumulus is dit het veld "Soort". LET OP: deze instelling betreft alleen de artikelen in uw catalogus, niet verzend, handling, verpakkings, of betaalkosten.',

        'field_marginProducts' => 'Verkoopt u margegoederen?',
        'option_marginProducts_1' => 'Zowel nieuwe producten en/of diensten als margegoederen.',
        'option_marginProducts_2' => 'Alleen nieuwe producten en/of diensten.',
        'option_marginProducts_3' => 'Alleen margegoederen.',
        'desc_marginProducts' => 'Geef aan of u in uw winkel margegoederen (bv. 2e-hands producten) verkoopt. Zie <a href="https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/bijzondere_regelingen/margeregeling/margeregeling" target="_blank">Margeregeling</a>.',

        'vat_class' => 'belastingklasse',
        'vat_classes' => 'belastingklasses',
        'vat_class_not_applicable' => 'niet van toepassing',
        'vat_class_left_empty' => '%1$s laat ik leeg',

        'field_foreignVatClasses' => 'Welke %1$s definiëren EU btw tarieven?',
        'desc_foreignVatClasses' => 'Geef aan welke van deze %1$s gebruikt worden wanneer u buitenlandse btw berekent voor (niet btw-plichtige) EU klanten.<br>
• Kies de 1e optie ("%2$s") als u uw winkel (nog) niet hebt ingericht op deze regeling.<br>
• Mbv de "Ctrl" toets kunt u meerdere %1$s kiezen of keuzes ongedaan maken.<br>
• NB: Sinds 1 juli 2021 gelden er <a href="https://www.belastingdienst.nl/wps/wcm/connect/nl/btw/content/e-commerce-en-diensten-in-de-eu-kijk-wat-er-verandert-voor-de-btw-x" target="_blank">nieuwe regels en drempels voor afstandsverkopen binnen de EU</a> en vallen bijna alle goederen (en diensten) hieronder.',

        'field_vatFreeClass' => 'Welke %1$s definieert btw-vrij?',
        'desc_vatFreeClass' => 'Geef aan welke %1$s u gebruikt om aan te geven dat een product of dienst btw-vrij is.<br>
• Kies de 1e optie ("%2$s") als u geen btw-vrije producten of diensten aanbiedt.<br>
• Kies de 2e optie ("%3$s") als u bij uw btw-vrije producten en diensten het veld %1$s leeg laat.<br>
• LET OP: het gaat er om of het product of de dienst btw-vrij is, uw bedrijf voor de <a href="https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/hoe_werkt_de_btw/nieuwe-kleineondernemersregeling/kleineondernemersregeling" target="_blank"">KOR regeling</a> heeft gekozen, of een btw vrijstelling heeft. Niet of u voor specifieke situaties een factuur zonder btw opstelt.
Ook is er een verschil met het 0%%-tarief hieronder, Dit verschil zit hem met name in de mogelijkheid tot aftrek van voorbelasting.',

        'field_zeroVatClass' => 'Welke %1$s definieert het 0%% btw-tarief?',
        'desc_zeroVatClass' => 'Geef aan welke %1$s u gebruikt om aan te geven dat een product of dienst onder het 0%%-tarief valt.<br>
• Kies de 1e optie ("%2$s") als u geen producten of diensten aan aanbiedt die onder het 0%%-tarief vallen.<br>
• LET OP 1: het 0%%-tarief is wat anders dan het btw-vrije tarief van hierboven en is in Nederland niet gebruikelijk. Momenteel (begin 2021) geldt er b.v. een uitzondering voor mondkapjes.<br>
• LET OP 2: het gaat er om of het product of de dienst onder het 0%%-tarief valt, niet of u 0%% btw op een factuur mag noteren vanwege b.v. verkoop aan het buitenland of een factuur met verlegde btw.',

        // Trigger settings.
        'triggerSettingsHeader' => 'Wanneer wilt u uw facturen automatisch naar Acumulus laten versturen',
        'desc_triggerSettings' => 'Met behulp van deze instelling kunt u aangeven op welk(e) moment(en) u de factuur voor een bestelling of creditfactuur naar Acumulus wilt versturen. Als u meerdere momenten selecteert, wordt de factuur naar Acumulus verstuurd zodra de bestelling één van de gekozen statussen bereikt. Een factuur zal altijd slechts 1 keer naar Acumulus worden verstuurd. Deze koppeling gebruikt alleen gegevens van de bestelling, dus u kunt elke status kiezen. De webwinkelfactuur hoeft dus nog niet aangemaakt te zijn, tenzij u voor de factuurdatum en nummer de webwinkelfactuurdatum en nummer wilt gebruiken. Als u voor "Niet automatisch versturen" kiest, dient u de facturen zelf over te zetten m.b.v. het <a href="%s">Acumulus batchverzendformulier</a>.',

        'field_triggerOrderStatus' => 'Bestelling, op basis van bestelstatus(sen)',
        'desc_triggerOrderStatus' => 'Mbv de "Ctrl" toets kunt u meerdere statussen kiezen.',
        'option_empty_triggerOrderStatus' => 'Niet automatisch versturen',

        'field_triggerInvoiceEvent' => 'Bestelling, op basis van webshopfactuur status',
        'option_triggerInvoiceEvent_0' => 'Niet automatisch versturen.',
        'option_triggerInvoiceEvent_1' => 'Als een factuur van de webwinkel wordt aangemaakt.',
        'option_triggerInvoiceEvent_2' => 'Als een factuur van de webwinkel wordt verzonden naar de klant.',
        'desc_triggerInvoiceEvent' => 'U kunt hier kiezen of en bij welke webwinkelfactuur-gebeurtenissen de factuur naar Acumulus wordt verstuurd. Als u voor "Niet automatisch versturen" kiest, kunt u de facturen zelf overzetten m.b.v. het batchformulier of op basis van één of meerdere bestelstatussen.',

        'field_triggerCreditNoteEvent' => 'Creditfactuur',
        'option_triggerCreditNoteEvent_0' => 'Niet automatisch versturen.',
        'option_triggerCreditNoteEvent_1' => 'Als de webshop een creditfactuur aanmaakt.',
        'desc_triggerCreditNoteEvent' => 'U kunt hier kiezen of u de factuur voor een creditfactuur automatisch naar Acumulus wilt versturen. Als u voor "Niet automatisch versturen" kiest, kunt u de facturen zelf overzetten m.b.v. het batchformulier. Merk op dat de aanmaak van een creditfactuur in de webwinkel plaatsvindt voordat de betaalprovider het bedrag daadwerkelijk terugstort.',

        // Tokens
        'tokenHelpHeader' => 'Uitleg over veldverwijzingen',
        'desc_tokens' => '<p>Op deze pagina staan een aantal velden die "veldverwijzingen" mogen bevatten.
Dit wil zeggen dat ze naast vrije tekst ook gegevens van de bestelling, de klant of het klantadres kunnen bevatten.
Veldverwijzingen worden ingegeven door de naam van de eigenschap van de bestelling tussen vierkante haken, dwz. [ en ], te plaatsen.
De eigenschappen die uw webshop kent worden hieronder opgesomd.</p>
<p>Om speciale situaties aan te kunnen, mogen veldverwijzingen op verschillende manieren samengevoegd worden:</p>
<ol class="property-list">
<dt>[property]:</dt><dd>Eenvoudigste vorm, vervang door de waarde van deze eigenschap of method (zonder argumenten).</dd>
<dt>[property(arguments)]:</dt><dd>Vervang door de waarde die de method property teruggeeft. Als property een method is wordt "arguments" (een komma-gescheiden reeks van argumenten zonder aanhalingstekens om tekenreeksen heen) meegegeven bij het aanroepen van de method.</dd>
<dt>[object::property]:</dt><dd>Vervang alleen door de eigenschap als die in het opgegeven object voorkomt (zie de lijst hieronder). Gebruik dit om verwarring te voorkomen als meerdere objecten een eigenschap met dezelfde naam hebben (bv id).</dd>
<dt>[property1|property2|...]:</dt><dd>Vervang door de waarde van property1 of als deze geen waarde heeft door die van property2, en zo verder. BV: handig om of het mobiele of het vaste telefoonnummer mee te sturen.</dd>
<dt>[property1+property2+...]:</dt><dd>Vervang door de waarde van property1 en die van property2 en plaats tussen de properties een spatie, maar alleen als de properties niet leeg zijn. BV: handig om de volledige naam, opgebouwd uit voornaam, tussenvoegsel en achternaam, te versturen zonder dat er meerdere spaties in terecht komen.</dd>
<dt>[property1&property2&...]:</dt><dd>Vervang door de waarde van property1 en die van property2 maar plaats geen spatie tussen de properties.</dd>
<dt>["letterlijke tekst"]:</dt><dd>Vervang door de letterlijke tekst (zonder aanhalingstekens) maar alleen als het samengevoegd wordt, middels een + of &, met een andere eigenschap die niet leeg is.</dd>
</ol>
<p><strong>Let op:</strong> in de meeste situaties zal de standaardwaarde goed zijn. Pas deze velden alleen aan in speciale omstandigheden en als u weet wat u doet.</p>
',
        'msg_token' => 'Dit veld mag veldverwijzingen bevatten.',
        'msg_tokens' => 'Deze velden mogen veldverwijzingen bevatten.',
        'see_class' => 'zie de class %1$s',
        'see_classes' => 'zie de classes %1$s',
        'see_file' => 'zie het bestand %1$s',
        'see_files' => 'zie de bestanden %1$s',
        'see_class_file' => 'zie de class %1$s in het bestand %2$s',
        'see_classes_files' => 'zie de classes %1$s in de bestanden %2$s',
        'see_table' => 'zie de tabel %1$s',
        'see_tables' => 'zie de tabellen %1$s',
        'and' => 'en',
        'or' => 'of',
        'see_class_more' => 'zie de class %1$s voor mogelijke andere properties en methods die als veldverwijzing gebruikt kunnen worden',
        'see_classes_more' => 'zie de classes %1$s voor mogelijke andere properties en methods die als veldverwijzing gebruikt kunnen worden',
        'see_table_more' => 'zie de tabel %1$s voor mogelijke andere velden die als veldverwijzing gebruikt kunnen worden',
        'see_tables_more' => 'zie de tabellen %1$s voor mogelijke andere velden die als veldverwijzing gebruikt kunnen worden',
        'invoice_source' => "een Acumulus specifieke representatie van een bestelling of creditnota.",
        'original_invoice_source' => "een Acumulus specifieke representatie van een bestelling zelf of de oorspronkelijke bestelling bij een creditnota.",
        'see_above' => 'zie hierboven.',
        'see_order_above' => "zie hierboven bij 'order'.",
        'see_invoice_source_above' => "zie hierboven bij 'invoiceSource'.",
        'order_or_refund' => 'De bestelling of creditnota waarvoor de factuur naar Acumulus verzonden wordt.',
        'original_order_for_refund' => 'De bestelling zelf of de oorspronkelijke bestelling bij een creditnota',
        'refund_only' => 'alleen bij een creditnota',
        'internal_id' => 'intern ID, ook wel technische sleutel genoemd',
        'external_id' => 'de voor iedereen zichtbare referentie',
        'internal_not_label' => 'waarde zoals die wordt opgeslagen in de database, geen (vertaald) label',
        'invoice_lines_only' => 'alleen beschikbaar bij de factuurregels',
        'payment_status_1' => 'Nog niet betaald',
        'payment_status_2' => 'Betaald',

        // Relation management settings.
        'relationSettingsHeader' => 'Relatiebeheer',
        'desc_relationSettingsHeader' => 'Met elke factuur die naar Acumulus verstuurd word, worden ook de klantgegevens meegestuurd. Hier kunt u instellen hoe dit precies dient te gebeuren. De meeste velden hieronder kunnen opgenomen worden in uw factuursjablonen. Daarom is het handig om er hier controle over te hebben over wat er in die velden komt te staan.',

        'field_defaultCustomerType' => 'Importeer klanten als',

        'field_contactStatus' => 'Actief',
        'desc_contactStatus' => 'Geef aan of relaties als actief of inactief opgeslagen moeten worden.',
        'option_contactStatus_Active' => 'Ja',
        'option_contactStatus_Disabled' => 'Nee',

        'field_contactYourId' => 'Klantreferentie v/d webshop',
        'desc_contactYourId' => 'Als u van een relatie in Acumulus de webshopgegevens wilt opzoeken is het handig als Acumulus het voor de webshop unieke klantnummer ook heeft. Met behulp van dit veld wordt deze referentie in Acumulus opgeslagen. Deze kan ook op factuursjablonen gebruikt worden.',

        'field_companyName1' => 'Bedrijfsnaam 1',
        'field_companyName2' => 'Bedrijfsnaam 2',

        'field_vatNumber' => 'btw-nummer',
        'desc_vatNumber' => 'Om een factuur met verlegde btw aan te kunnen maken dient zowel de bedrijfsnaam als het intracommunautaire btw-nummer bekend te zijn.',

        'field_fullName' => 'Volledige naam',
        'desc_fullName' => 'De volledige naam, meestal opgebouwd uit de voornaam, achternaam en evt. een tussenvoegsel.',

        'field_salutation' => 'Volledige aanhef',
        'desc_salutation' => 'U kunt hier de aanhef specificeren zoals u die wilt gebruiken als u communiceert met deze klant.',

        'field_address1' => 'Adresregel 1',
        'field_address2' => 'Adresregel 2',
        'desc_address' => 'Vul hier het adresgedeelte in, zijnde straatnaam, huisnummer en evt. gebouw of appartementsaanduiding binnen het huisnummer. Met postcode plugins kan deze informatie verspreid zijn over meerdere velden in de webshop.',

        'field_postalCode' => 'Postcode',
        'field_city' => 'Plaatsnaam',
        'field_telephone' => 'Telefoon',
        'desc_telephone' => 'Het telefoonnummer dat u in Acumulus wilt opslaan. Acumulus kan maar 1 nummer opslaan. Dus als uw webshop wel een vast en mobiel nummer opslaat, dient u te kiezen welk nummer uw voorkeur heeft. Gebruik het | teken voor als de klant maar 1 nummer heeft ingevuld.',
        'field_fax' => 'Fax',
        'desc_fax' => 'De meeste webshops slaan geen fax nummer meer op. U kunt dit veld dan evt. gebruiken om een vast EN een mobiel nummer in Acumulus op te slaan (als uw webshop die wel allebei opslaat).',
        'field_email' => 'Email',

        'field_mark' => 'Kenmerk',
        'desc_mark' => 'U knt hier extra informatie over de klant versturen, bv het BSN. Dit veld komt overeen met het veld "kenmerk" op blad 2 van het relatiebeheer.',

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
        'desc_defaultInvoiceTemplate' => 'Maakt u binnen Acumulus gebruik van meerdere factuur-sjablonen en wilt u de facturen uit uw webwinkel met een specifieke factuur-sjabloon printen, kies dan hier de factuur-sjablonen voor niet betaalde respectievelijk betaalde bestellingen.',

        'field_concept' => 'Concept',
        'desc_concept' => 'Geef aan hoe de factuur verstuurd moet worden. De 1e optie zal normaal gesproken voldoen. Echter, als u uw facturen altijd als concept wil versturen, kies dan de 3e optie. De 2e optie zou eigenlijk niet gekozen moeten worden, tenzij de waarschuwingen altijd onterecht blijken.',
        'option_concept_2' => 'Als definitieve factuur versturen, tenzij er onregelmatigheden zijn geconstateerd. Kies deze optie tenzij u weet wat u doet.',
        'option_concept_0' => 'Altijd als definitieve factuur versturen.',
        'option_concept_1' => 'Altijd als concept versturen.',

        'field_eu_commerce_threshold_percentage' => 'Percentage EU Omzetdrempel',
        'desc_eu_commerce_threshold_percentage' => 'Acumulus houdt bij hoeveel omzet binnen de EU u al heeft gedraaid. '
        . 'Deze %1$s kan een melding geven als u die drempel bijna heeft bereikt, en zal als u daadwerkelijk over de drempel heen gaat de factuur als concept versturen.<br>'
        . '• Vul een percentage in vanaf waar Acumulus een waarschuwing gaat geven. Gebroken percentages kunt u met een punt (.) aangeven. Het %-teken mag u weglaten.<br>'
        . '• Vul 100 in als u geen waarschuwing vooraf wil, maar wel als u wilt dat de factuur als concept verstuurd moet worden als u met deze factuur over de drempel heen zou gaan of al bent.<br>'
        . '• Laat leeg als u deze functie niet wilt gebruiken.',
        'message_validate_percentage_0' => 'Vul een percentage (een getal tussen 0 en 100) in voor het veld "%s".',

        'field_missing_amount' => 'Ontbrekend bedrag',
        'desc_missing_amount' => 'Geef aan wat te doen als er een verschil tussen het factuurtotaal en het totaal van de factuurregels geconstateerd wordt. Normaal gesproken zal de 1e optie voldoen. Als het vaak voorkomt en de ontbrekende bedragen zjn eigenlijk altijd correct (bv. betaalkosten van een specifieke payment provider worden niet als losse regel herkend) kan de 2e optie handiger zijn. Er wordt dan al een factuurregel toegevoegd zodat de factuur alleen maar definitief gemaakt hoeft te worden. Gebruik de 3e optie als dit verschil door een instelling of andere plugin optreedt maar de factuur eigenlijk toch altijd correct is.',
        'option_missing_amount_2' => 'Verstuur een waarschuwing, de factuur wordt als concept verstuurd.',
        'option_missing_amount_3' => 'Voeg een correctieregel toe, de factuur zal echter nog steeds als concept verstuurd worden.',
        'option_missing_amount_1' => 'Negeer het verschil.',

        'field_description' => 'Toelichting',
        'desc_description' => 'Toelichting op de factuur. Deze inhoud kan in Acumulus op een factuursjabloon getoond worden mbv de veldverwijzing [toelichting].',
        'field_descriptionText' => 'Uitgebreide toelichting',
        'desc_descriptionText' => 'Meerregelige toelichting op de factuur. Deze inhoud kan in Acumulus op een factuursjabloon getoond worden mbv de veldverwijzing [toelichting].',
        'field_invoiceNotes' => 'Notities',
        'desc_invoiceNotes' => 'Notities die u aan de factuur wilt toevoegen en die voor intern gebruik zijn bedoeld. Deze worden niet getoond op de factuursjabloon, in emails naar de klant, of op de pakbon.',

        // Invoice lines settings.
        'invoiceLinesSettingsHeader' => 'Uw factuurregelinstellingen',
        'field_itemNumber' => 'Artikelnummer',
        'desc_itemNumber' => 'Het artikelnummer of code of SKU die u op de factuurregel wilt tonen. U kunt dit leeg laten als uw productnamen uniek genoeg zijn en u uw klanten niet wilt vermoeien met interne codes of SKUs.',
        'field_productName' => 'Productnaam',
        'desc_productName' => 'De productnaam of omschrijving die u op de factuurregel wilt tonen.',
        'field_nature' => 'Soort product',
        'desc_nature' => 'Kan 2 waardes krijgen: "Product" of "Service". Als u alleen maar producten of alleen maar services verkoopt via deze webwinkel, stel u dit in op het Acumulus instellingen formulier en vult de plugin dit automatisch in. Als u zowel producten als services verkoopt en u slaat dit als een kenmerk op bij alle artikelen in uw catalogus, kunt u een veldverwijzing gebruiken naar dat kenmerk.',
        'field_costPrice' => 'Kostprijs',
        'desc_costPrice' => 'De kostprijs van een artikel. Dit wordt alleen gebruikt op margefacturen.',

        // Options settings.
        'optionsSettingsHeader' => 'Opties of varianten',
        'desc_optionsSettingsHeader' => 'Een product kan opties of varianten hebben of kan samengesteld zijn. Deze opties of deelproducten kunnen op dezelfde regel als het product komen of op aparte regels daaronder. U kunt het tonen ervan ook helemaal uitzetten.',
        'desc_composedProducts' => 'NB: als het een samengesteld product betreft en de subproducten hebben verschillende btw tarieven, dan komen alle subproducten op hun eigen regel, ongeacht deze instellingen.',
        'field_showOptions' => 'Tonen',
        'desc_showOptions' => 'Als u opties, varianten of deelproducten helemaal niet op de factuur terug wilt zien, vink deze optie dan uit. Dit kan bv. handig zijn als u de varianten of deelproducten alleen voor uw voorraadbeheer gebruikt. Als u deze instelling uitzet, dan worden de onderstaande instellingen genegeerd.',
        'option_optionsShow' => 'Opties en deelproducten op de factuur tonen',
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
        'desc_emailAsPdfSettings' => 'Bij het versturen van bestellinggegevens naar Acumulus, kunt u Acumulus een PDF factuur laten versturen naar uw klant. Deze wordt dan direct verstuurd naar het opgegeven emailadres.',

        'field_emailAsPdf' => 'Optie inschakelen',
        'option_emailAsPdf' => 'Verstuur de factuur als PDF vanuit Acumulus.',
        'desc_emailAsPdf' => 'Als u deze optie aanvinkt, kunt u de overige opties gebruiken om de emailverzending aan uw wensen aan te passen. Het bericht in de email body kunt u niet hier instellen, dat kunt u in Acumulus doen onder "Beheer - Factuur-sjablonen".',
        'message_validate_conflicting_options' => 'Als u geen klantgegevens naar Acumulus verstuurt, kunt u Acumulus geen PDF factuur laten versturen. Pas één van beide opties aan.',

        'field_emailTo' => 'Aan',
        'desc_emailTo' => 'Het email adres waar naartoe de factur verstuurd moet worden. Als u dit leeg laat wordt het emailadres uit de klantgegevens van de factuur gebruikt. Wij adviseren dit veld leeg te laten. U mag meerdere email adressen invullen, gescheiden door een komma (,) of een punt-komma (;).',
        'message_validate_email_5' => 'Het veld Aan bevat geen geldig e-mailadres, vul een correct e-mailadres in.',

        'field_emailBcc' => 'BCC',
        'desc_emailBcc' => 'Additioneel emailadres om de factuur naar toe te sturen, bv. het emailadres van uw eigen administratie-afdeling. Als u dit leeg laat wordt de factuur alleen naar de klant verstuurd. U mag meerdere email adressen invullen, gescheiden door een komma (,) of een punt-komma (;).',
        'message_validate_email_3' => 'Het veld BCC bevat geen geldig e-mailadres, vul een correct e-mailadres in.',

        'field_emailFrom' => 'Afzender',
        'desc_emailFrom' => 'Het email adres dat als afzender gebruikt moet worden. Als u dit leeg laat wordt het emailadres uit het Acumulus sjabloon gebruikt.',
        'message_validate_email_4' => 'Het veld Afzender bevat geen geldig e-mailadres, vul een correct e-mailadres in.',

        'field_subject' => 'Onderwerp',
        'desc_subject' => 'Het onderwerp van de email. Als u dit leeg laat wordt "Factuur [nummer] [omschrijving]" gebruikt. Let op: als u Acumulus het factuurnummer laat bepalen, is het helaas niet mogelijk om naar het factuurnnummer te verwijzen.',

        // Invoice status screen settings.
        'invoiceStatusScreenSettingsHeader' => 'Factuurstatusoverzicht',
        'desc_invoiceStatusScreenSettings' => 'Acumulus kan op de detailpagina van een bestelling de status tonen van de bijbehorende factuur in Acumulus.',
        'desc_invoiceStatusScreenSettings2' => 'Hierdoor ziet u in een oogopslag of de factuur correct is verstuurd naar Acumulus en of de betaalstatus correct is. Ook kunt u de factuurgegevens opnieuw naar Acumulus versturen of de betaalstatus aanpassen.',
        'desc_invoiceStatusScreen' => '• Met de eerste optie geeft u aan of u dit scherm getoond wil hebben.<br>•  Met de 2e en 3e optie geeft u aan of u downloadlinks naar deze pdf bestanden op dit scherm getoond wilt hebben. Dit zijn de pdf bestanden die Acumulus maakt, niet die van de webwinkel zelf. Als u deze bestanden niet gebruikt kun u opties 2 en 3 beter uitvinken.',
        'field_invoiceStatusScreen' => 'Welke onderdelen wilt u tonen.',
        'option_showInvoiceStatus' => 'Toon dit scherm.',
        'option_showPdfInvoice' => 'Toon de link naar de factuur.',
        'option_showPdfPackingSlip' => 'Toon de link naar de pakbon.',

        // Plugin settings.
        'pluginSettingsHeader' => 'Plugin instellingen',

        'field_debug' => 'Factuur verzendmodus',
        'option_debug_1' => 'Ontvang alleen een mail bij fouten, waarschuwingen, of opmerkingen tijdens het verzenden van een factuur naar Acumulus.',
        'option_debug_2' => 'Ontvang altijd een mail met de resultaten bij het verzenden van een factuur naar Acumulus.',
        'option_debug_3' => 'Verstuur facturen in testmodus naar Acumulus. Acumulus zal de factuur controleren op fouten en waarschuwingen maar zal deze niet opslaan. U ontvangt alijd een mail met de resultaten.',
        'option_debug_4' => 'Verzend berichten niet naar Acumulus maar ontvang wel een mail met het bericht zoals dat verstuurd zou zijn.',
        'desc_debug' => 'U kunt hier een verzendmodus kiezen. Kies voor de eerste optie tenzij u i.v.m. een supportverzoek bent geïnstrueerd om iets anders te kiezen.',

        'field_logLevel' => 'Logniveau',
        'option_logLevel_3' => 'Log foutmeldingen, waarschuwingen en operationele mededelingen.',
        'option_logLevel_4' => 'Log foutmeldingen, waarschuwingen en operationele en informatieve mededelingen.',
        'option_logLevel_5' => 'Log foutmeldingen, waarschuwingen, mededelingen, en communicatieberichten.',
        'desc_logLevel' => 'U kunt hier een logniveau kiezen. Kies voor de 1e of 2e optie tenzij u i.v.m. een supportverzoek bent geïnstrueerd om iets anders te kiezen.',

        // Link to other config form.
        'desc_advancedSettings' => 'Deze plugin kent veel instellingen en daarom bevat deze pagina niet alle instellingen. Een aantal minder gebruikte instellingen vindt u op het "%1$s" formulier onder "%2$s". Nadat u hier de gegevens hebt ingevuld en opgeslagen, kunt u het andere formulier bezoeken:',
        'menu_advancedSettings' => 'Instellingen → Acumulus geavanceerde instellingen',

        'desc_basicSettings' => 'U bevindt zich nu op het formulier met geavanceerde, ofwel minder gebruikte, instellingen. De basisinstellingen vindt u op het "%1$s" formulier onder "%2$s", of via de button hieronder. Let op: als u op deze button klikt worden de op deze pagina ingevulde of gewijzigde gegevens NIET opgeslagen!',
        'menu_basicSettings' => 'Instellingen → Acumulus',
    ];

    protected $en = [
        // Titles, headers, links, buttons and messages.
        'config_form_title' => 'Acumulus | Settings',
        'config_form_header' => 'Acumulus settings',
        'config_form_link_text' => 'Acumulus basic settings',

        'advanced_form_title' => 'Acumulus | Advanced settings',
        'advanced_form_header' => 'Acumulus advanced settings',
        'advanced_form_link_text' => 'Acumulus advanced settings',

        'button_submit_config'=> 'Save settings',
        'button_submit_advanced'=> 'Save settings',
        'button_cancel' => 'Back',

        'message_form_config_success' => 'The settings are saved.',
        'message_form_config_error' => 'an error occurred wile saving the settings.',

        'message_form_advanced_success' => 'The settings are saved.',
        'message_form_advanced_error' => 'an error occurred wile saving the settings.',

        'message_uninstall' => 'Are you sure to delete the configuration settings?',

        'message_error_header' => 'Error in your Acumulus connection settings',
        'message_error_auth' => 'Your Acumulus connection settings are incorrect. Please check them. After you have entered the correct connection settings %2$s, the %1$s settings will be shown.',
        'message_error_forb' => 'Your Acumulus connection settings are correct but do not allow access via the web service. After you have entered correct connection settings %2$s, the %1$s settings will be shown.',
        'message_error_comm' => 'The module encountered an error retrieving your Acumulus configuration. Please try again. When the connection is restored the %1$s settings will be shown as well.',
        'message_auth_unknown' => 'When you have filled in your Acumulus connection settings %2$s, the %1$s settings will be shown as well.',
        'message_error_arg1_config' => 'other',
        'message_error_arg1_advanced' => 'advanced',
        'message_error_arg2_config' => 'here',
        'message_error_arg2_advanced' => 'in the "Acumulus basic settings form"',
        'message_warning_role_deprecated' => 'You are using a deprecated user role to connect to the Acumulus API. Please add another user with an API-compliant role or change the role for the current user.',
        'message_warning_role_insufficient' => 'You are using the user role API-Creator. This role does not have all permissions this plugin needs. Change the role of the current user to API-User or create a new user.',
        'message_warning_role_overkill' => 'You are using the user role API-Manager. This role has mre permissions than this plugin needs. We advice you to change the role to API-User.',

        // Register.
        'config_form_register' => 'You have not entered your account details yet. If you don\'t have an account yet you can create a trial account with this %1$s',
        'config_form_register_button' => '<a class="%2$s" href="%1$s">Create a free Acumulus trial account now without any obligation</a>',

        // Account settings.
        'accountSettingsHeader' => 'Your Acumulus connection settings',
        'desc_accountSettings_N' => 'If you already do have an Acumulus account, you can fill in your details below.',
        'desc_accountSettings_F' => 'The entered account details are not correct, please correct them.',
        'desc_accountSettings_auth' => 'If you do not have an account yet, you can <a href="%1$s">register a free trial account</a>.',
        'desc_accountSettings_T' => 'The %s could successfully connect to Acumulus with these details.',

        'field_code' => 'Contract code',
        'field_username' => 'User name',
        'desc_username' => 'Make sure you enter the data of an "API user" user type. If you don\'t have them yet, <a href="https://www.sielsystems.nl/" target="_blank">log in to Acumulus</a> and add a user under "Beheer → Gebruikers → Gebruiker toevoegen". Fill in "API - Gebruiker" as "Gebruikerstype".',
        'field_password' => 'Password',
        'field_emailonerror' => 'E-mail',
        'desc_emailonerror' => 'The e-mail address at which you will be informed about any errors that occur during invoice sending. As this module cannot know if it is called from an interactive administrator screen, it will not display any messages in the user interface. Therefore you have to fill in an e-mail address.',

        'message_validate_contractcode_0' => 'The field Contract code is required, please fill in the contract code you use to log in to Acumulus.',
        'message_validate_contractcode_1' => 'The field Contract code is a numeric field, please fill in the contract code you use to log in to Acumulus.',
        'message_validate_username_0' => 'The field User name is required, please fill in the user name you use to log in to Acumulus.',
        'message_validate_username_1' => 'The field User name contains spaces at the start or end. This is allowed but are you sure that you meant to do so?',
        'message_validate_password_0' => 'The field Password is required, please fill in the password you use to log in to Acumulus.',
        'message_validate_password_1' => 'The field Password contains spaces at the start or end. This is allowed but are you sure that you meant to do so?',
        'message_validate_password_2' => 'The field Password contains a character that is forbidden by Acumulus (`\'"#%&;<>\\). Are you sure that you typed the correct password?',
        'message_validate_email_0' => 'The field E-mail is not a valid e-mail address, please fill in your own e-mail address.',
        'message_validate_email_1' => 'The field E-mail is required, please fill in your own e-mail address.',
        'message_validate_required_field' => 'the field "%1$s" is required, please select a value.',
        'message_validate_foreign_vat_classes_0' => 'The field \'%1$s\' is required, please select one or more values.',
        'message_validate_foreign_vat_classes_1' => 'The choice "%2$s" for the field "%1$s" cannot be combined with other values.',
        'message_validate_zero_vat_class_0' => 'You must use different %1$s to distinguish between products or services subject to the 0%% VAT rate and those that are VAT free.',
        'message_validate_conflicting_shop_options_1' => 'If you only sell services, you cannot sell using the margin scheme. Change one of these options.',
        'message_validate_conflicting_shop_options_2' => 'If you only sell using the margin scheme, you should select that you only sell goods on the "Nature" field.',

        // Shop settings.
        'shopSettingsHeader' => 'About your shop',
        'desc_shopSettings' => 'With these settings, this plugin is better able to: determine the <a href="https://wiki.acumulus.nl/index.php?page=facturen-naar-het-buitenland" target="_blank">invoice type</a>; perform some sanity checks; and to compute VAT rates.',

        'field_nature_shop' => 'Nature: what do you sell?',
        'option_nature_1' => 'Products and services.',
        'option_nature_2' => 'Only products.',
        'option_nature_3' => 'Only services.',
        'desc_nature_shop' => 'Select whether you sell products and/or services. In Acumulus this is the field "Nature" ("Soort"). NOTE: this settings only concerns the items in your "catalog", not shipping, handling, packing, or payment fees.',

        'field_marginProducts' => 'Do you sell products using the margin scheme?',
        'option_marginProducts_1' => 'New products and/or services as well as products that use the margin scheme.',
        'option_marginProducts_2' => 'Only new products and/or services.',
        'option_marginProducts_3' => 'Only products that use the margin scheme.',
        'desc_marginProducts' => 'Select whether your store sells (2nd hand) products using the margin scheme. See <a href="https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/bijzondere_regelingen/margeregeling/margeregeling" target="_blank">Dutch tax office: margin goods (in Dutch)</a>.',

        'vat_class' => 'tax class',
        'vat_classes' => 'tax classes',
        'vat_class_not_applicable' => 'not applicable',
        'vat_class_left_empty' => 'I leave the %1$s empty',

        'field_foreignVatClasses' => 'Which %1$s define EU vat rates?',
        'desc_foreignVatClasses' => 'Indicate which of these %1$s are used when you charge foreign VAT to (non vat subjected) EU customers.
Select the 1st option ("%2$s") when you have not (yet) configured your shop to use foreign vat.<br>
Using the "Ctrl" key, you can select/deselect multiple %1$s.<br>
NB: As of 1 july 2021 <a href="https://www.belastingdienst.nl/wps/wcm/connect/nl/btw/content/e-commerce-en-diensten-in-de-eu-kijk-wat-er-verandert-voor-de-btw-x" target="_blank">new rules and thresholds for E-commerce sales within the EU (in Dutch)</a> apply to almost all goods (and services).',

        'field_vatFreeClass' => 'Which %1$s defines VAT free?',
        'desc_vatFreeClass' => 'Indicate which %1$s you use to indicate that a product or service is VAT free.<br>
Select the 1st option ("%2$s") if you do not sell VAT free goods or services.<br>
Select the 2nd option ("%3$s") if you leave the field %1$s empty on VAT free products.<br>
NOTE: this setting concerns whether the goods or services you offer are inherently VAT free, or because your company has chosen to use the <a href="https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/hoe_werkt_de_btw/nieuwe-kleineondernemersregeling/kleineondernemersregeling" target="_blank"">KOR regulations (in Dutch)</a>, or is for some other reason not VAT liable. Not whether you create an invoice with no or reversed VAT.
Also note that VAT free differs from the 0%% VAT rate below. This difference mainly concerns the right to deduct VAT paid on your purchases.',

        'field_zeroVatClass' => 'Which %1$s defines the 0%% vat rate?',
        'desc_zeroVatClass' => 'Indicate which %1$s you use to indicate that a product or service is subject to the 0%% vat rate.<br>
Select the 1st option ("%2$s") if you do not sell goods or services at the 0%% rate.<br>
NOTE 1: the 0%% rate differs from vat free as above and is not common in the Netherlands. E.g, currently (early 2021), masks fall under the 0% vat rate.<br>
NOTE 2: this setting concerns whether the products or services you offer are inherently subject to the 0% vat rate, not if you make invoices without vat (e.g. sometimes when selling abroad) or reversed vat.',

        // Trigger settings.
        'triggerSettingsHeader' => 'When to have your invoices sent to Acumulus.',
        'desc_triggerSettings' => 'This(these) setting(s) determine(s) at what instants the invoice for an order or credit note should be sent to Acumulus. If you select multiple instants, the invoice wil be sent as soon as the order reaches one of the selected statuses. Note that an invoice will only be sent once to Acumulus. This extension only uses order data, so you may select any status, the webshop invoice does not already have to be created,unless you want to use the webshop\'s invoice date and number as invoice date and number for the Acumulus invoice. If you select "Do not send automatically" you will have to use the <a href="%s">Acumulus batch send form</a>.',

        'field_triggerOrderStatus' => 'Order',
        'desc_triggerOrderStatus' => 'Using the "Ctrl" key, you can select/deselect multiple items.',
        'option_empty_triggerOrderStatus' => 'Do not send automatically.',

        'field_triggerInvoiceEvent' => 'Order, based on webshop invoice',
        'option_triggerInvoiceEvent_0' => 'Do not send automatically.',
        'option_triggerInvoiceEvent_1' => 'When the webshop invoice gets created.',
        'option_triggerInvoiceEvent_2' => 'When the webshop invoice gets sent to the customer.',
        'desc_triggerInvoiceEvent' => 'Select if and on which webshop invoice event to send the invoice to Acumulus. If you select "Do not send automatically" you can use the send batch form, or you can set one or more order statuses above to trigger the sending of the invoice.',

        'field_triggerCreditNoteEvent' => 'Credit Note',
        'option_triggerCreditNoteEvent_0' => 'Do not send automatically.',
        'option_triggerCreditNoteEvent_1' => 'When the credit note gets created.',
        'desc_triggerCreditNoteEvent' => 'Select if to send the invoice for a credit note automatically to Acumulus. If you select "Do not send automatically" you can use the send batch form. Note that creation of the web shop credit note normally takes place before the payment gateway actually refunds the money to your client.',

        // Tokens
        'tokenHelpHeader' => 'Explanation of field references',
        'desc_tokens' => '<p>This form contains a number of fields that may contain "field references".
This means that besides free literal text, these fields can contain data from the order, customer and customer address.
Field references are denoted by placing the name of the property between square brackets, ie. [ and ].
The properties known by your web shop are listed below.</p>
<p>To handle some special situations, field references can be combined as follows:</p>
<ol class="property-list">
<dt>[property]:</dt><dd>Simplest form, replace by the value of the property or method (without arguments).</dd>
<dt>[property(arguments)]:</dt><dd>Replace by the return value of the method. "arguments" is a comma-separated list of arguments to pass to the method. Do not use quotes around strings.</dd>
<dt>[object::property]:</dt><dd>Replace by the value of the property but only if that property is part of the given object (see list below). Use this to get the correct value if multiple objects have a property with the same name (e.g. id).</dd>
<dt>[property1|property2|...]:</dt><dd>Replace by the value of property1, or if that does not have a value by that of property2, etc. Example: useful to get either the mobile OR land line number.</dd>
<dt>[property1+property2+...]:</dt><dd>Replace by the value of property1 and that of property2 with 1 space between it, but only if both values are not empty. Example: useful to get the full name, constructed of first, middle and last name.</dd>
<dt>[property1&property2&...]:</dt><dd>Replace by the value of property1 and that of property2 but with no space between it.</dd>
<dt>["literal text"]:</dt><dd>Replace by the literal text (without quotes) but only if it is combined, using + or &, with another non-empty property.</dd>
</ol>
<p><strong>Attention:</strong> in most situations the default value will do fine! Only change these fields in special situations and when you know what you are doing.</p>
',
        'msg_token' => 'This field may contain field references.',
        'msg_tokens' => 'These fields may contain field references.',
        'see_class' => 'see class %1$s',
        'see_classes' => 'see the classes %1$s',
        'see_file' => 'see file %1$s',
        'see_files' => 'see the files %1$s',
        'see_class_file' => 'see the class %1$s in file %2$s',
        'see_classes_files' => 'see the classes %1$s in the files %2$s',
        'see_table' => 'see table %1$s',
        'see_tables' => 'see the tables %1$s',
        'and' => 'and',
        'or' => 'or',
        'see_class_more' => 'see the class %1$s for possible other properties and methods that can be used as field reference',
        'see_classes_more' => 'see the classes %1$s for possible other properties and methods that can be used as field reference',
        'see_table_more' => 'see the table %1$s for possible other fields that can be used as field reference',
        'see_tables_more' => 'see the tables %1$s for possible other fields that can be used as field reference',
        'invoice_source' => "An Acumulus specific representation of an order or credit note.",
        'see_above' => 'see above.',
        'see_order_above' => "see above with 'order'.",
        'see_invoice_source_above' => "see above with 'invoiceSource'.",
        'order_or_refund' => 'The order or credit note for which the invoice is sent to Acumulus.',
        'original_order_for_refund' => 'The order itself or the original order for a refund.',
        'refund_only' => 'only for refunds',
        'internal_id' => 'internal ID, the so-called technical key',
        'external_id' => 'A reference used in external communication',
        'internal_not_label' => 'value as stored in the database, not a (translated) label',
        'invoice_lines_only' => 'only available with the invoice lines',
        'payment_status_1' => 'Due',
        'payment_status_2' => 'Paid',

        // Relation management settings.
        'relationSettingsHeader' => 'Relation management',
        'desc_relationSettingsHeader' => 'With each invoice sent to Acumulus, its client data is sent as well. With these settings you can influence how this is done. Most fields below can be added to your invoice templates. That is why you can control its contents here.',

        'field_defaultCustomerType' => 'Create customers as',

        'field_contactStatus' => 'Active',
        'desc_contactStatus' => 'Indicate whether relations should be saved as active or inactive',
        'option_contactStatus_Active' => 'Yes',
        'option_contactStatus_Disabled' => 'No',

        'field_contactYourId' => 'Web shop customer reference',
        'desc_contactYourId' => 'If you want to search the customer data of the webshop for a relation in Acumulus, it can be handy to have its unique reference as used by your webhop ready in Acumulus. Use this field to define which field the web shop uses as customer reference.',
        'field_companyName1' => 'Company name 1',
        'field_companyName2' => 'Company name 2',

        'field_vatNumber' => 'VAT number',
        'desc_vatNumber' => 'To create a reversed VAT invoice, Acumulus must know the company name and EU VAT number. So be sure to ask for it and store it in your webshop, so it can be sent to Acumulus.',

        'field_fullName' => 'Full name',
        'desc_fullName' => 'The full name, normally constructed using the first, middle and last name and any pre or suffix. What and how this is stored, depends on the web shop you use.',

        'field_salutation' => 'Full salutations',
        'desc_salutation' => 'Specify the salutations you want to use when communicating with this client.',

        'field_address1' => 'Address 1',
        'field_address2' => 'Address 2',
        'desc_address' => 'Enter the address parts as stored in your webshop. E.g. postal code plugins can use a separate field (often address 2) to store the house number separately from the street name.',

        'field_postalCode' => 'Postal code',
        'field_city' => 'City',
        'field_telephone' => 'Phone',
        'desc_telephone' => 'The phone numnber you want ot store in Acumulus. Acumulus only stores 1 phone number. So if your web shop stores both a land line and mobile number you will have to choose which one you prefer to store in Acumulus. Use the | character to list alternative phone number fields, so you get a phone number regardless in which field it was filled in.',
        'field_fax' => 'Fax',
        'desc_fax' => 'Most web shops do not store a fax number. So leave empty or "use" it to store both mobile and land line mumber (if your web shop does store both).',
        'field_email' => 'Email',

        'field_mark' => 'Mark',
        'desc_mark' => 'Use this field to send any additional information about your customer, e.g. its BSN. This field fills the "kenmerk" on page 2 of the Acumulus relation management dialog.',

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

        'field_concept' => 'Concept',
        'desc_concept' => 'Indicate how to send the invoice to Acumulus. Normally, the 1st option will do fine, unless you want to send all your invoices as concept to Acumulus. The 2nd option should normally not be chosen, unless the warnings always turn out to be incorrect.',
        'option_concept_2' => 'Send the invoice as final unless the plugin discovered irregularities. Choose this option unless you know what you are doing.',
        'option_concept_0' => 'Always send as final.',
        'option_concept_1' => 'Always send as concept.',

        // @todo: translate
//        'field_eu_commerce_threshold_percentage' => 'Percentage EU Omzetdrempel',
//        'desc_eu_commerce_threshold_percentage' => 'Acumulus houdt bij hoeveel omzet binnen de EU u al heeft gedraaid. '
//                                                   . 'Deze %1$s kan een melding geven als u die drempel bijna heeft bereikt, en zal als u daadwerkelijk over de drempel heen gaat de factuur als concept versturen.<br>'
//                                                   . '• Vul een percentage in vanaf waar Acumulus een waarschuwing gaat geven. Gebroken percentages kunt u met een punt (.) aangeven. Het %-teken mag u weglaten.<br>'
//                                                   . '• Vul 100 in als u geen waarschuwing vooraf wil, maar wel als u wilt dat de factuur als concept verstuurd moet worden als u met deze factuur over de drempel heen zou gaan of al bent.<br>'
//                                                   . '• Laat leeg als u deze functie niet wilt gebruiken.',
//        'message_validate_percentage_0' => 'Vul een percentage (een getal tussen 0 en 100) in voor het veld "%s".',

        'field_missing_amount' => 'Missing amount',
        'desc_missing_amount' => 'Indicate what to do when the invoice total and the total of the invoice lines differ. Normally, the 1st option will do fine. However, if this happens often and the missing amounts are always correct (e.g. payment fees for a specific payment provider that are not recognised as a separate invoice line), you\'d better use the 2nd option. This will already add an invoice line to the invoice, so you only have to make it final. Use the 3rd option if a setting or another plugin causes this difference but the invoice turns out to be correct anyway.',
        'option_missing_amount_2' => 'Send a warning, the invoice will be sent as concept.',
        'option_missing_amount_3' => 'Add a correction line, note that the invoice will still be sent as concept.',
        'option_missing_amount_1' => 'Ignore.',

        'field_description' => 'Description',
        'desc_description' => 'Invoice description. In Acumulus, you can use the contents of this field in invoice templates using the field reference [toelichting].',
        'field_descriptionText' => 'Extended description',
        'desc_descriptionText' => 'Multi line invoice description. In Acumulus, you can use the contents of this field in invoice templates using the field reference [toelichting].',
        'field_invoiceNotes' => 'Notes',
        'desc_invoiceNotes' => 'Internal notes that you want to add to the invoice. These notes will not be shown on invoice templates, emails to the client, or on the packing slip.',

        // Invoice lines settings.
        'invoiceLinesSettingsHeader' => 'Your invoice lines settings',
        'field_itemNumber' => 'Article number',
        'desc_itemNumber' => 'The article number, code or SKU you want to show on the invoice. You may leave this empty if your product names are sufficiently identifying and you don\'t want to bother your customer with internal codes or SKU\'s.',
        'field_productName' => 'Product name',
        'desc_productName' => 'The product name or description you want to show on the invoice.',
        'field_nature' => 'Nature',
        'desc_nature' => 'The nature of the article sold. This can be either "Product" or "Service". If your shop only sells products or only sells services, you should indicate so in the Acumulus settings form and the plugin will fill this in automatically. If you sell both and this is stored as a property for all items in your catalog you can use a field reference to use that property.',
        'field_costPrice' => 'Cost price',
        'desc_costPrice' => 'The cost price of this article. This is only used on margin scheme invoices.',

        // Options settings.
        'optionsSettingsHeader' => 'Options or variants',
        'desc_optionsSettingsHeader' => 'Products can have options or variants, or can be composed. These options or sub products can be placed on the same line as the main product or on separate lines below. You can also switch this off altogether.',
        'desc_composedProducts' => 'btw: if this is a composed product and the sub products have different vat rates, all sub products will always be placed on their own line and the settings below will be ignored.',
        'field_showOptions' => 'Show',
        'desc_showOptions' => 'Uncheck this setting if you do not want to place options, variants or sub products on the invoice at all. E.g. this can occur when you use the variants or sub products only for your own stock management. If you uncheck this setting the following settings will be ignored.',
        'option_optionsShow' => 'Show options and sub products on the invoice',
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
        'desc_paymentMethodCostCenterFieldset' => 'Below you can enter a cost center to use per (active) payment method. The default above serves as fallback for payment methods for which you do not specify a cost center.',

        // Email as pdf settings.
        'emailAsPdfSettingsHeader' => 'PDF Invoice',
        'desc_emailAsPdfSettings' => 'On sending the order details to Acumulus, Acumulus can send a PDF invoice to your customer. The mail will immediately be sent to the given email address.',

        'field_emailAsPdf' => 'Enable the feature',
        'option_emailAsPdf' => 'Have Acumulus send the invoice as PDF.',
        'desc_emailAsPdf' => 'If you check this option, you can use the other options below to configure the emails to your preferences. However, to configure the text in the mail body, go to Acumulus to "Settings » Invoice templates".',
        'message_validate_conflicting_options' => 'If you don\'t send customer data to Acumulus, Acumulus cannot send PDF invoices. Change one of the options.',

        'field_emailTo' => 'To',
        'desc_emailTo' => 'The email address to send the invoice to. If you leave this empty the email address from the invoice\'s customer data will be used. We recommend you to leave this empty. You may enter multiple email addresses separated by a comma (,) or a semi-colon (;).',
        'message_validate_email_5' => 'The field To is not a valid e-mail address, please fill in a valid e-mail address.',

        'field_subject' => 'Subject',
        'desc_subject' => 'The subject line of the email. If you leave this empty "Invoice [number] [description]" will be used. Note: if you have Acumulus assign the invoice number, it is unfortunately not possible to refer to that invoice number in the subject.',

        'field_emailBcc' => 'BCC',
        'desc_emailBcc' => 'Additional email addresses to send the invoice to, e.g. the email address of your own administration department. If you leave this empty the invoice email will only be sent to your client. You may enter multiple email addresses separated by a comma (,) or a semi-colon (;).',
        'message_validate_email_3' => 'The field BCC is not a valid e-mail address, please fill in a valid e-mail address.',

        'field_emailFrom' => 'Sender',
        'desc_emailFrom' => 'The email address to use as sender. If you leave this empty, the email address of the Acumulus template will be used. We recommend you to leave this empty.',
        'message_validate_email_4' => 'The field Sender is not a valid e-mail address, please fill in a valid e-mail address.',

        // Plugin settings.
        'pluginSettingsHeader' => 'Plugin settings',

        'field_debug' => 'Invoice send mode',
        'option_debug_1' => 'Only receive a mail when there were errors, warnings, or notices on sending an invoice to Acumulus.',
        'option_debug_2' => 'Always receive a mail with the results on sending an invoice to Acumulus.',
        'option_debug_3' => 'Send invoices to Acumulus in test mode. Acumulus will only check the input for errors and warnings but not store the invoice. You will always receive a mail with the results.',
        'option_debug_4' => 'Do not send messages to Acumulus, but receive a mail with the message as would have been sent.',
        'desc_debug' => 'Select a mode that defines how to react on sending invoices to Acumulus. Choose for the 1st option unless otherwise instructed by support staff.',

        'field_logLevel' => 'Log level',
        'option_logLevel_3' => 'Log error messages, warnings, and operational notices.',
        'option_logLevel_4' => 'Log error messages, warnings, and operational and informational notices.',
        'option_logLevel_5' => 'Log error messages, warnings, notices, and communication messages.',
        'desc_logLevel' => 'Select a log level. Choose for the 1st or 2nd option unless otherwise instructed by support staff.',

        // Invoice status screen settings.
        'invoiceStatusScreenSettingsHeader' => 'Invoice status overview',
        'desc_invoiceStatusScreenSettings' => 'On the detail page of an order, Acumulus can show the status of the accompanying invoice in Acumulus.',
        'desc_invoiceStatusScreenSettings2' => 'This allows you to easily check if the invoice was correctly sent and if the payment status is still correct. You can also resend the invoice data or change the payment status.',
        'desc_invoiceStatusScreen' => 'The 1st option indicates whether you want to show this screen at all. Use the 2nd and 3rd option to indicate whether you want to show download links to these pdf files. These are the pdf files from Acumulus, not from your webshop! If you don\'t use them, you better uncheck these options.',
        'field_invoiceStatusScreen' => 'What parts do you want to show.',
        'option_showInvoiceStatus' => 'Show this screen.',
        'option_showPdfInvoice' => 'Show the link to the invoice.',
        'option_showPdfPackingSlip' => 'Show the link to the packing slip.',

        // Link to other config form.
        'desc_advancedSettings' => 'This plugin is highly configurable and therefore this form does not contain all settings. You can find the other settings in the "%1$s" under "%2$s". Once you have completed and saved the settings over here, you can visit that form to fill in the advanced settings.',
        'menu_advancedSettings' => 'Settings → Acumulus advanced settings',

        'desc_basicSettings' => 'This is the form with advanced, i.e. less commonly used, settings. You can find the basic settings in the "%1$s" under "%2$s", or via the button below. Note: if you click on this button, changes you made to this page will NOT be saved!',
        'menu_basicSettings' => 'Settings → Acumulus',
    ];
}
