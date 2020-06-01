<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Class RegistrationFormTranslations
 */
class RegistrationFormTranslations extends TranslationCollection
{
    protected $nl = [
        'registration_form_title' => 'Acumulus | Vrijblijvend proefaccount aanmaken',
        'registration_form_header' => 'Een vrijblijvend Acumulus proefaccount aanmaken',
        'button_submit_registration'=> 'Gratis account aanmaken',
        'message_form_registration_success' => 'Uw tijdelijke proefaccount is met succes aangemaakt.',

        'introHeader' => 'Een Acumulus proefaccount aanmaken',
        'registration_form_intro' => '<p>Door dit formulier in te vullen kunt u een gratis en vrijblijvend proefaccount aanmaken bij Acumulus.</p>
            <p>Het gratis proefaccount is 30 dagen geldig en volledig functioneel (met een maximum van 50 boekingen).
            Zodra u het proefaccount omzet in een abonnement is het aantal boekingen onbeperkt en de reeds gedane instellingen en boekingen blijven behouden.
            Het proefaccount wordt NIET automatisch omgezet in een abonnement! U ontvangt een e-mail vlak voordat het proefaccount verloopt.</p>',

        'personSettingsHeader' => 'Over u, contactpersoon',

        'field_gender' => 'Geslacht',
        'desc_gender' => 'Uw geslacht. Dit wordt gebruikt om sjablonen in Acumulus in te stellen en in de aanhef in communicatie naar u toe.',
        'option_gender_neutral' => 'Neutraal',
        'option_gender_female' => 'Vrouw',
        'option_gender_male' => 'Man',

        'field_fullName' => 'Naam',
        'desc_fullName' => 'Uw voor- en achternaam.',

        'field_loginName' => 'Gebruikersnaam',
        'desc_loginName' => 'Minimaal 6 tekens. De gebruikersnaam die u wilt gebruiken om zelf in te loggen op Acumulus. Deze %s zal een eigen gebruikersnaam en wachtwoord krijgen.',

        'companySettingsHeader' => 'Over uw bedrijf',
        'desc_companySettings' => 'Met onderstaande informatie kunnen we uw proefaccount beter inrichten, zo kunnen we b.v. een factuursjabloon maken. Uiteraard kunt u deze gegevens later nog aanpassen.',

        'field_companyTypeId' => 'Rechtsvorm',
        'field_companyName' => 'Bedrijfsnaam',
        'field_address' => 'Adres',
        'field_postalCode' => 'Postcode',
        'field_city' => 'Plaats',

        'field_emailRegistration' => 'E-mail',
        'desc_emailRegistration' => 'Uw e-mailadres. Dit wordt gebruikt om u een bevestiging te sturen met de details van het proefaccount en voor verdere communicatie vanuit Acumulus naar u toe.
           Het zal ook ingesteld worden als e-mailadres waar deze %s foutberichten naar toe stuurt.',

        'field_telephone' => 'Telefoon',
        'desc_telephone' => 'Uw telefoonnummer. Als u dit invult kunnen wij u eventueel bellen als u ondersteuning wenst.',

        'field_bankAccount' => 'Rekeningnummer',
        'desc_bankAccount' => 'Het bankrekeningnummer (IBAN) van uw bedrijfsrekening. Dit wordt alleen gebruikt voor het aanmaken van een factuursjabloon voor uw bedrijf, er zal GEEN automatische incasso plaatsvinden voordat u dit contract definitief heeft gemaakt en u toestemming voor een automatische incasso heeft gegeven.',

        'notesSettingsHeader' => 'Over uw aanvraag',

        'field_notes' => 'Opmerkingen',
        'desc_notes' => 'Als u een vraag of opmerking heeft over Acumulus of deze %s, dan kunt u deze hier invullen. Er wordt dan een ticket geopend in ons supportsysteem en u krijgt antwoord op het door u opgegeven e-mailadres.',

        'message_validate_required_field' => 'Het veld %s is verplicht.',
        'message_validate_loginname_0' => 'Uw gebruikersnaam moet tenminste 6 karakters lang zijn.',
        'message_validate_email_0' => 'Het veld E-mail bevat geen geldig e-mailadres, vul uw eigen e-mailadres in.',
        'message_validate_postalCode_0' => 'Het veld postcode bevat geen geldige postcode, vul uw postcode in: formaat: "1234 AB".',

        'registration_form_success_title' => 'Hartelijk dank voor uw aanmelding!',
        'registration_form_success_text1' => 'U kunt Acumulus 30 dagen (tot %s) gratis en vrijblijvend proberen. Uw proefaccount is volledig functioneel met een maximum van 50 boekingen. Indien u vragen hebt, vernemen wij dat graag.',
        'registration_form_success_text2' => 'Uw inloggegevens zijn verstuurd naar %s, maar wij hebben ze hier ook voor u genoteerd. Bewaar deze bij voorkeur in een wachtwoordbeheerder.',
        'registration_form_success_text3' => 'Hebt u geen e-mail ontvangen? Mogelijk wordt ons e-mailbericht met uw inlogcodes gefilterd door uw spamfilter. Controleer uw spamfilter en de map met gefilterde e-mail. Neem anders contact met ons op zodat wij u uw gegevens opnieuw kunnen toesturen.',
        'loginDetailsHeader' => 'Klik om uw inlogcodes te zien',

        'field_code' => 'Contractcode',
        'field_password' => 'Wachtwoord',

        'registration_form_success_api_account' => 'Deze %1$s zal met zijn eigen aanmeldingsgegevens communiceren met Acumulus.
           Deze zijn al opgeslagen in de instellingen van deze %1$s, maar staan voor u ook hieronder genoteerd zodat u ze kunt opslaan in een wachtwoordbeheerder.
           Voor de beveiliging van uw Acumulus account heeft dit speciale account een ander gebruikerstype, dat alleen via de API met Acumulus mag communiceren. Er kan niet mee worden ingelogd op Acumulus zelf.',
        'moduleLoginDetailsHeader' => 'Klik om de inlogcodes van deze %s te zien',
        'desc_apiloginDetails' => 'Deze inlogcodes zijn toegevoegd aan de instellingen van deze %1$s',

        'whatsNextHeader' => 'Volgende stappen',
        'registration_form_success_configure_acumulus' => 'U kunt <strong>Acumulus verder instellen</strong> door o.a. rekeningen, kostenplaatsen en factuursjablonen toe te voegen.',
        'registration_form_success_login_button' => '<a class="button" target="_blank" href="https://www.sielsystems.nl/">Nu inloggen op Acumulus</a> (opent in een nieuwe tab en gaat naar de Acumulus website).',
        'registration_form_success_configure_module' => 'U dient <strong>deze %1$s verder in te stellen</strong> op de "instellingen" en "geavanceerde instellingen" schermen.',
        'registration_form_success_config_button' => '<a class="button" target="_blank" href="%2$s">Acumulus %1$s instellen</a> (opent in een nieuwe tab maar blijft in uw webwinkel).',
        'registration_form_success_batch' => 'Nadat u deze %1$s heeft ingeregeld worden de factuurgegevens van uw nieuwe bestellingen automatisch naar Acumulus verstuurd.
           Om al afgeronde bestellingen alsnog toe te voegen aan uw administratie, kunt u het batchverzendformulier van deze %1$s gebruiken.',
    ];

    protected $en = [
        'message_form_registration_success' => 'Your temporary account has been created successfully.',

        'desc_gender' => 'Indication of gender. Used to predefine some strings within Acumulus.',
    ];
}
