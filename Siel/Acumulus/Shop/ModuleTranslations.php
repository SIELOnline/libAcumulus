<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains module related translations, like module name, buttons, requirement
 * messages, and save/cancel/failure messages.
 */
class ModuleTranslations extends TranslationCollection
{
    protected $nl = array(
        // Linking into the shop's extension system, standard buttons and
        // messages.
        'extensions' => 'Extensies',
        'modules' => 'Modules',
        'page_title' => 'Acumulus instellingen',
        'advanced_page_title' => 'Acumulus geavanceerde instellingen',
        'module_name' => 'Acumulus',
        'module_description' => 'Verstuurt uw facturen automatisch naar Acumulus',
        'text_home' => 'Home',
        'button_settings' => 'Instellingen',
        'button_advanced_settings' => 'Geavanceerde Instellingen',
        'button_save' => 'Opslaan',
        'button_back' => 'Terug naar overzicht',
        'button_confirm_uninstall' => 'Ja, verwijder data en instellingen',
        'button_cancel_uninstall' => 'Nee, alleen uitschakelen, bewaar data en instellingen',
        'button_cancel' => 'Annuleren',
        'button_send' => 'Verzenden',
        'message_config_saved' => 'De instellingen zijn opgeslagen.',
        'message_uninstall' => 'Wilt u de configuratie-instellingen verwijderen?',

        // Requirements.
        'message_error_req_curl' => 'Voor het gebruik van deze module dient de CURL PHP extensie actief te zijn op uw server.',
        'message_error_req_xml' => 'Voor het gebruik van deze module met het output format XML, dient de SimpleXML PHP extensie actief te zijn op uw server.',
        'message_error_req_dom' => 'Voor het gebruik van deze module dient de DOM PHP extensie actief te zijn op uw server.',

    );

    protected $en = array(
        'extensions' => 'Extensions',
        'modules' => 'Modules',
        'page_title' => 'Acumulus settings',
        'advanced_page_title' => 'Acumulus advanced settings',
        'module_name' => 'Acumulus',
        'module_description' => 'Automatically sends your invoices to Acumulus',
        'text_home' => 'Home',
        'button_settings' => 'Settings',
        'button_advanced_settings' => 'Advanced Settings',
        'button_save' => 'Save',
        'button_back' => 'Back to list',
        'button_confirm_uninstall' => 'Yes, uninstall data and settings',
        'button_cancel_uninstall' => 'No, disable only, keep data and settings',
        'button_cancel' => 'Cancel',
        'button_send' => 'Send',
        'message_config_saved' => 'The settings are saved.',
        'message_uninstall' => 'Are you sure that you want to delete the configuration settings?',

        // Requirements.
        'message_error_req_curl' => 'The CURL PHP extension needs to be activated on your server for this module to work.',
        'message_error_req_xml' => 'The SimpleXML extension needs to be activated on your server for this module to be able to work with the XML format.',
        'message_error_req_dom' => 'The DOM PHP extension needs to be activated on your server for this module to work.',

    );
}
