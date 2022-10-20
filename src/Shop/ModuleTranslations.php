<?php
/**
 * @noinspection HtmlUnknownTarget
 */

namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains module related translations, like module name, buttons, requirement
 * messages, and save/cancel/failure messages.
 *
 * @noinspection PhpUnused
 */
class ModuleTranslations extends TranslationCollection
{
    protected $nl = [
        // Linking into the shop's extension system, standard buttons and
        // messages.
        'shop' => 'Webwinkel',
        'about_environment' => 'Over uw webwinkel',
        'about_error' => 'Foutmelding',
        'extensions' => 'Extensies',
        'module_name' => 'Acumulus',
        'module_description' => 'Verstuurt uw facturen automatisch naar Acumulus',
        'text_home' => 'Home',
        // @todo: which ones are still used?
        'button_settings' => 'Instellingen',
        'button_advanced_settings' => 'Geavanceerde Instellingen',
        'button_save' => 'Opslaan',
        'button_back' => 'Terug naar overzicht',
        'button_confirm_uninstall' => 'Ja, verwijder data en instellingen',
        'button_cancel_uninstall' => 'Nee, alleen uitschakelen, bewaar data en instellingen',
        'button_cancel' => 'Annuleren',

        'documents' => 'Documenten',
        'document' => 'Document',
        'document_invoice' => 'factuur',
        'document_packingSlip' => 'pakbon',
        'document_show' => 'Acumulus %1$s openen in uw browser',
        'document_mail' => 'Acumulus %1$s mailen',

        'wait' => 'Even wachten',

        // @todo: start using these 3 parameters, for now this text is
        //   overridden in all shops with only 2 parameters.
        'button_link' => '<a href="%2$s" class="%3$s">%1$s</a>',
        'button_class' => 'button',
        'message_config_saved' => 'De instellingen zijn opgeslagen.',
        'message_update_failed' => 'De interne upgrade naar versie %s is mislukt. Als deze melding terug blijft komen, neem dan contact op met support.',
        'message_uninstall' => 'Wilt u de configuratie-instellingen verwijderen?',
        'unknown' => 'onbekend',
        'option_empty' => 'Maak uw keuze',
        'click_to_toggle' => '<span>(klik om te tonen of te verbergen)</span>',
        'date_format' => 'jjjj-mm-dd',
        'crash_admin_message' => 'Er is een fout opgetreden. De foutmelding is gelogd en als mail verstuurd. Als de fout blijft aanhouden neem dan contact op met support. Foutmelding: %s',
    ];

    protected $en = [
        'shop' => 'Web shop',
        'about_environment' => 'About your webshop',
        'about_error' => 'Error message',
        'extensions' => 'Extensions',
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
        'message_config_saved' => 'The settings are saved.',
        'message_update_failed' => 'The internal upgrade to version %s failed. Please contact support, if this message keeps being displayed.',
        'message_uninstall' => 'Are you sure that you want to delete the configuration settings?',
        'unknown' => 'unknown',
        'option_empty' => 'Select one',
        'click_to_toggle' => '<span>(click to show or hide)</span>',
        'date_format' => 'yyyy-mm-dd',
        'crash_admin_message' => 'An error occurred. the error message has been logged and mailed. If the error keeps occurring, please contact support. Error message: %s',

        'documents' => 'Documents',
        'document' => 'Document',
        'document_invoice' => 'invoice',
        'document_packingSlip' => 'packing slip',
        'document_show' => 'Open Acumulus %1$s in your browser',
        'document_mail' => 'Mail Acumulus %1$s',

        'wait' => 'Please wait',
    ];
}
