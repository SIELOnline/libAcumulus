<?php
/**
 * @noinspection PhpUnused
 */

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

/**
 * Contains plugin-specific overrides.
 *
 * This class should be overridden to translate terminology specific for your webshop
 * environment. A typical example is the word for an extension: plugin, module, extension,
 * or whatever it is called in your environment.
 *
 * This base class itself contains some translations that are valid for most, if not all,
 * shops and do not fit to another translation collection.
 * @todo: this is code smell: define a translations class for basic translations?
 *   (Not specific to a feature.) Should also remove the need to load all form translation
 *    classes just to create menu links,
 */
class ModuleSpecificTranslations extends TranslationCollection
{
    protected array $baseNl = [
        'settings_form_link_text' => 'Instellingen',
        'settings_form_description' => 'Configureer de Acumulus addon',
        'mappings_form_link_text' => 'Veldverwijzingen',
        'mappings_form_description' => 'Benoem de relaties tussen velden van de WHMCS en Acumulus facturen',
        'activate_form_link_text' => 'Activeer Acumulus Pro-support',
        'batch_form_link_text' => 'Batchverzending',
        'register_form_link_text' => 'Registreer',

        'activate_new' => 'Activeer Pro-support voor deze site',
        'activate_renew' => 'Koop/Hernieuw Pro-support voor deze site',
    ];

    protected array $baseEn = [
        'settings_form_link_text' => 'Settings',
        'settings_form_description' => 'Setup and Configure the Acumulus addon',
        'mappings_form_link_text' => 'Mappings',
        'mappings_form_description' => 'Specify relations between fields from the WHMCS and Acumulus invoices',
        'batch_form_link_text' => 'Send batch',
        'activate_form_link_text' => 'Activate Acumulus Pro-support',
        'register_form_link_text' => 'Register',

        'activate_new' => 'Activate Pro-support for this site',
        'activate_renew' => 'Buy/Renew Pro-support for this site',
    ];

}
