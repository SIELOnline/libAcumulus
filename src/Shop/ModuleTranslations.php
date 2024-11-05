<?php
/**
 * @noinspection LongLine
 * @noinspection HtmlUnknownTarget
 */

declare(strict_types=1);

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
    protected array $nl = [
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

        // Documents.
        'documents' => 'Documenten',
        'document' => 'Document',
        'document_invoice' => 'factuur',
        'document_packing_slip' => 'pakbon',
        'document_show' => '%1$s tonen',
        'document_mail' => '%1$s mailen',
        'document_show_title' => 'Acumulus %1$s openen in uw browser',
        'document_mail_title' => 'Acumulus %1$s mailen',

        // Vat type.
        'vat_type' => 'soort factuur',
        'vat_type_1' => 'Normaal, Nederlandse btw',
        'vat_type_2' => 'Btw-verlegd binnen Nederland',
        'vat_type_3' => 'Btw-verlegd in de EU',
        'vat_type_4' => 'Goederen buiten de EU',
        'vat_type_5' => 'Margeregeling (2e-hands producten)',
        'vat_type_6' => 'EU btw',
        'vat_type_7' => 'Andere buitenlandse (b.v. GB) btw',
        'netherlands' => 'Nederland',

        'wait' => 'Even wachten',

        // @todo: start using these 3 parameters, for now this text is
        //   overridden in all shops with only 2 parameters.
        'button_link' => '<a href="%2$s">%1$s</a>',
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

    protected array $en = [
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

        // Documents.
        'documents' => 'Documents',
        'document' => 'Document',
        'document_invoice' => 'invoice',
        'document_packing_slip' => 'packing slip',
        'document_show' => 'Show %1$s',
        'document_mail' => 'Mail %1$s',
        'document_show_title' => 'Open Acumulus %1$s in your browser',
        'document_mail_title' => 'Mail Acumulus %1$s',

        // Vat type.
        'vat_type' => 'invoice type',
        'vat_type_1' => 'Normal, Dutch vat',
        'vat_type_2' => 'Reversed vat within the Netherlands',
        'vat_type_3' => 'Reversed vat within the EU',
        'vat_type_4' => 'Goods outside the EU',
        'vat_type_5' => 'Margin invoice (2nd hand goods)',
        'vat_type_6' => 'EU vat',
        'vat_type_7' => 'Other foreign (e.g. GB) vat',
        'netherlands' => 'the Netherlands',

        'wait' => 'Please wait',
    ];
}
