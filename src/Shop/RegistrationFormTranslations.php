<?php
/** @noinspection HtmlUnknownTarget */

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
        'option_gender_neutral' => 'Genderneutraal',
        'option_gender_female' => 'Vrouw',
        'option_gender_male' => 'Man',

        'field_fullName' => 'Naam',
        'desc_fullName' => 'Uw voor- en achternaam.',

        'field_loginName' => 'Gebruikersnaam',
        'desc_loginName' => 'De gebruikersnaam, minimaal 6 tekens, die u wilt gebruiken om zelf in te loggen op Acumulus. Deze %s zal een eigen gebruikersnaam en wachtwoord krijgen.',

        'companySettingsHeader' => 'Over uw bedrijf',
        'desc_companySettings' => 'Met onderstaande informatie kunnen we uw proefaccount beter inrichten, zo kunnen we b.v. een factuursjabloon maken. Uiteraard kunt u deze gegevens later nog aanpassen.',

        'field_companyName' => 'Bedrijfsnaam',
        'field_companyTypeId' => 'Rechtsvorm',
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
        'registration_form_title' => 'Acumulus | Apply for a free trial account',
        'registration_form_header' => 'Apply for a free trial account for Acumulus',
        'button_submit_registration'=> 'Create free account',
        'message_form_registration_success' => 'Your temporary account has been created successfully.',

        'introHeader' => 'Apply for a free Acumulus trial account',
        'registration_form_intro' => '<p>By filling in this form you can create a free trial account for Acumulus.</p>
            <p>This trial account remains active for 30 days and is fully functional, though with a limit of 50 bookings.
            As soon as you convert the trial account into a subscription, the number of bookings is unlimited and the settings and bookings already made are retained.
            The trial account is NOT automatically converted into a subscription! You will receive an email just before the trial account expires.</p>',

        'personSettingsHeader' => 'About you, contact person',

        'field_gender' => 'Gender',
        'desc_gender' => 'Your gender. This is used to fill some templates in Acumulus and in the introduction in communication to you.',
        'option_gender_neutral' => 'Gender neutral',
        'option_gender_female' => 'Female',
        'option_gender_male' => 'Male',

        'field_fullName' => 'Name',
        'desc_fullName' => 'Your first and last name.',

        'field_loginName' => 'Username',
        'desc_loginName' => 'The username, at least 6 characters, that you want to use to login to Acumulus yourself. This %s will have its own username and password.',

        'companySettingsHeader' => 'About your company',
        'desc_companySettings' => 'With the information below we can better set up your trial account, e.g. we can create an invoice template. Of course you can change this information later on.',

        'field_companyName' => 'Company name',
        'field_companyTypeId' => 'Legal form',
        'field_address' => 'Address',
        'field_postalCode' => 'Zip code',
        'field_city' => 'City',

        'field_emailRegistration' => 'E-mail',
        'desc_emailRegistration' => 'Your e-mail address. This will be used to send you a confirmation with the details of the trial account and for further communication from Acumulus to you.
           It will also be set up as the email address to which this %s will send any error messages.',

        'field_telephone' => 'Phone',
        'desc_telephone' => 'Your phone number. If you fill this out, we can call you if you need support.',

        'field_bankAccount' => 'Bank account number',
        'desc_bankAccount' => 'The bank account number (IBAN) of your company bank account. This will only be used to create an invoice template for your company, no direct debit will take place before you have made this contract final and have given your consent for a direct debit.',

        'notesSettingsHeader' => 'About your application',

        'field_notes' => 'Remarks',
        'desc_notes' => 'If you have a question or remark about Acumulus or this %s, you can fill it out here. A ticket will be opened in our support system and you will receive an answer on the email address you provided.',

        'message_validate_required_field' => 'The field %s is required.',
        'message_validate_loginname_0' => 'Your username must be at least 6 characters long.',
        'message_validate_email_0' => 'The field E-mail does not contain a valid e-mail address, please enter your own e-mail address.',
        'message_validate_postalCode_0' => 'The zip code field does not contain a valid zip code, enter your zip code in the format: "1234 AB".',

        'registration_form_success_title' => 'Thank you very much for your registration!',
        'registration_form_success_text1' => 'You can try Acumulus 30 days (up to %s) free of charge and without any obligation. Your trial account is fully functional with a maximum of 50 bookings. If you have any questions, please let us know.',
        'registration_form_success_text2' => 'Your login details have been sent to %s, but we have listed them for you here as well. Preferably keep them in a password manager.',
        'registration_form_success_text3' => 'Didn\'t receive an e-mail? It is possible that our e-mail with your login codes will be filtered by your spam filter. Please check your spam folder. If you still did not receive it, please contact us so that we can resend you your details.',
        'loginDetailsHeader' => 'Click to view your login codes',

        'field_password' => 'Password',

        'registration_form_success_api_account' => 'This %1$s will communicate with Acumulus with its own login details.
           These are already stored in the settings of this %1$s, but are also listed below so you can save them in a password manager.
           For the security of your Acumulus account, this special account has a different user type, which is only allowed to communicate with Acumulus via the API. It cannot be used to log in to Acumulus itself.',
        'moduleLoginDetailsHeader' => 'Click to view the login codes of this %s',
        'desc_apiloginDetails' => 'These login codes have been added to the settings of this %1$s',

        'whatsNextHeader' => 'Next steps',
        'registration_form_success_configure_acumulus' => 'You can further <strong>configure Acumulus</strong> by adding accounts, cost centers and invoice templates.',
        'registration_form_success_login_button' => '<a class="button" target="_blank" href="https://www.sielsystems.nl/">Login on Acumulus</a> (opens in a new tab and goes to the Acumulus website).',
        'registration_form_success_configure_module' => 'You need to <strong>configure this %1$s</strong> further on the "settings" and "advanced settings" screens.',
        'registration_form_success_config_button' => '<a class="button" target="_blank" href="%2$s">Configure Acumulus %1$s</a> (opens in a new tab but remains in your webshop).',
        'registration_form_success_batch' => 'After you have set up this %1$s, the invoice details of your new orders will automatically be sent to Acumulus.
           To add already completed orders to your administration, you can use the batch shipping form of this %1$s.',
    ];
}
