<?php

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for the "Activate pro-support" form.
 */
class ActivateSupportFormTranslations extends TranslationCollection
{
    protected array $nl = [
        'activate_form_title' => 'Acumulus | Activeer Pro-support',
        'activate_form_header' => 'Activeer Pro-support voor uw Acumulus webshopkoppeling',
        'activate_form_link_text' => 'Activeer Acumulus pro-support',
        'message_form_activate_success' => 'Pro-support voor uw webshopkoppeling is met success geactiveerd. Zie het "Over" blok verderop op deze pagina.',

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

    protected array $en = [
        'activate_form_title' => 'Acumulus | Activate Pro-support',
        'activate_form_header' => 'Activate Pro-support for your Acumulus webshop connector',
        'activate_form_link_text' => 'Activate Acumulus pro-support',
        'message_form_activate_success' => 'Pro-support for your webshop connector has been successfully activated. See the "About" block further down this page.',

        'button_submit_activate'=> 'Activate',
        'button_cancel' => 'Cancel',

        'activateFieldsHeader' => 'Activate the pro-support you bought for this webshop %1$s',
        'field_activate_token' => 'Token',
        'desc_activate_token' => 'Fill in the token that you received after buying pro-support for this webshop %1$s.',
        'field_activate_website' => 'Webshop',
        'desc_activate_website' => 'The purchase of 1 year of pro-support is normally for 1 webshop. The domain name of the webshop (without any "www.") will be sent along with the activation.',

        'message_validate_invalid_token' => 'The token is invalid.',
        'message_validate_activate_hostname_changed' => 'The domain name of your website differs from when you filled in this form.',
    ];
}
