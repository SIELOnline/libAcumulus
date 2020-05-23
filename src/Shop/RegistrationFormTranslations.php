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

        'introHeader' => 'Een Acumulus proefaccount',
        'registration_form_intro' => '<p>Door dit formulier in te vullen kunt u een gratis en vrijblijvend proefaccount aanmaken bij Acumulus.</p>
            <p>Het gratis proefaccount is 30 dagen geldig en volledig functioneel (met een maximum van 50 boekingen).
            Zodra u het proefaccount omzet in een abonnement is het aantal boekingen onbeperkt en de reeds gedane instellingen en boekingen blijven behouden.</p>
            <p>Een proefaccount wordt NIET automatisch omgezet in een abonnement! U ontvangt een e-mail vlak voordat het proefaccount verloopt.</p>',

        'personSettingsHeader' => 'Over u, contactpersoon',

        'field_gender' => 'Geslacht',
        'desc_gender' => 'Uw geslacht. Dit wordt gebruikt om sjablonen in Acumulus in te stellen en in de aanhef in communicatie naar u toe.',
        'option_gender_neutral' => 'Neutraal',
        'option_gender_female' => 'Vrouw',
        'option_gender_male' => 'Man',

        'field_fullName' => 'Naam',
        'desc_fullName' => 'Uw voor en achternaam.',

        'field_loginName' => 'Gebruikersnaam',
        'desc_loginName' => 'Minimaal 6 tekens. De gebruikersnaam die u wilt gebruiken om zelf in te loggen op Acumulus. Deze %s zal een eigen gebruikersnaam en wachtwoord krijgen.',

        'companySettingsHeader' => 'Over uw bedrijf',
        'desc_companySettings' => 'Met onderstaande info kunnen we uw proefaccount beter inrichten, zo kunnen we b.v. een factuursjabloon maken. Uiteraard kunt u deze gegevens later nog aanpassen.',

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
        'desc_notes' => 'Als u een vraag heeft over Acumulus of over deze %s dan kunt u deze hier stellen. Er wordt dan een ticket geopend in ons supportsysteem en u krijgt antwoord op het door u opgegeven e-mailadres.',

        'message_validate_required_field' => 'Het veld %s is verplicht.',
        'message_validate_email_0' => 'Het veld E-mail is geen valide e-mailadres, vul uw eigen e-mailadres in.',
        'message_validate_postalCode_0' => 'Het veld postcode is geen valide postcode, vul uw postcode in: formaat: "1234 AB".',
    ];

    protected $en = [
        'message_form_registration_success' => 'Your temporary account has been created successfully.',

        'desc_gender' => 'Indication of gender. Used to predefine some strings within Acumulus.',
    ];
}
