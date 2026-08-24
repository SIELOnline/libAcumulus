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
        'config_form_link_text' => 'Instellingen',
        'mappings_form_link_text' => 'Veldverwijzingen',
        'advanced_form_link_text' => 'Geavanceerde instellingen',
        'activate_form_link_text' => 'Activeer Pro-support',
        'batch_form_link_text' => 'Batchverzending',
        'register_form_link_text' => 'Registreer',

        'activate_new' => 'Activeer Pro-support voor deze site',
        'activate_renew' => 'Vernieuw Pro-support voor deze site',
    ];

    protected array $baseEn = [
        'settings_form_link_text' => 'Settings',
        'config_form_link_text' => 'Settings',
        'mappings_form_link_text' => 'Mappings',
        'advanced_form_link_text' => 'Advanced settings',
        'batch_form_link_text' => 'Send batch',
        'activate_form_link_text' => 'Activate Pro-support',
        'register_form_link_text' => 'Register',

        'activate_new' => 'Activate Pro-support for this site',
        'activate_renew' => 'Renew Pro-support for this site',
    ];

}
