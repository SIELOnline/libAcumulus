<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for the "Activate pro-support" form.
 */
class ActivateSupportFormTranslations extends TranslationCollection
{
    protected $nl = [
        'activate_form_title' => 'Acumulus | Activeer Pro-support',
        'activate_form_header' => 'Activeer Pro-support voor uw Acumulus webshopkoppeling',
        'activate_form_link_text' => 'Activeer Acumulus pro-support',
        'message_form_activate_success' => 'Ondersteuning voor uw webshopkoppeling is met success geactiveerd. Zie het "Over" blok verderop op deze pagina.',

        'button_submit_activate'=> 'Activeer',
        'button_cancel' => 'Annuleren',

        'activateFieldsHeader' => 'Activeer uw aangekochte pro-support voor deze website %1$s',
        'field_activate_token' => 'Token',
        'desc_activate_token' => 'Vul hier het token in dat u ontvangen heeft nadat u pro-support voor deze webshop %1$s heeft aangeschaft.',
        'field_activate_website' => 'Webshop',
        'desc_activate_website' => 'Aankoop van 1 jaar pro-support is in principe voor 1 webshop. Deze domeinnaam van de webshop (zonder evt. "www.") wordt meegestuurd bij de activatie.',

        'message_validate_invalid_token' => 'Het token is geen geldig token.',
        'message_validate_activate_hostname_changed' => 'De domeinnaam van uw website is anders dan tijdens het invullen van dit formulier.',
   ];

    protected $en = [
        'activate_form_title' => 'Acumulus | Activeer Pro-support',
        'activate_form_header' => 'Activeer Pro-support voor uw Acumulus webshop koppeling',
        'activate_form_link_text' => 'Activeer Acumulus pro-support',

        'button_submit_activate'=> 'Activeer',
        'button_cancel' => 'Annuleren',

        'activateFieldsHeader' => 'Activeer uw aangekochte pro-support voor deze website',
        'field_activate_token' => 'Token',
        'desc_activate_token' => 'Vul hier het token in dat u ontvangen heeft nadat u online bij Siel de pro-support voor deze webshop aankocht.',
        'field_activate_webshop' => 'Webshop',
        'desc_activate_webshop' => 'Aankoop van 1 jaar pro-support is in principe voor 1 webshop. Het domeinnaam van deze webshop (zonder een evt. "www.") wordt meegestuurd bij de activatie.',

        'supportInfoHeader' => 'Toelichting op Pro-support',
        'support_info' => <<<LONGSTRING
<p>TODO1: Leg uit wat dit formulier doet.</p>
<p>TODO2: Leg uit wat pro-support is: verwijs naar https://www.siel.nl/acumulus/koppelingen/support/.</p>
<p>TODO3: neem link naar webshop specifieke pro-support aankoop in de siel winkel op.</p>
LONGSTRING
        ,
    ];
}
