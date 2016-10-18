<?php
namespace Siel\Acumulus\OpenCart\Helpers;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains plugin specific overrides.
 */
class ModuleSpecificTranslations extends TranslationCollection
{
    protected $nl = array(
        'button_link' => '<a href="%2$s" class="button btn btn-primary"><i class="fa fa-cog"></i> %1$s</a>',
        'advanced_form_link_text' => 'Geavanceerde instellingen',
        'batch_form_link_text' => 'Batchverzending',

        'desc_advancedSettings' => 'Deze plugin kent veel instellingen en daarom bevat deze pagina niet alle instellingen. Een aantal minder gebruikte instellingen vindt u in het "%1$s". Nadat u hier de gegevens hebt ingevuld en opgeslagen, kunt u het andere formulier bezoeken:',
        'menu_basicSettings' => 'Extensies → Modules → Acumulus → Wijzigen',
    );

    protected $en = array(
        'advanced_form_link_text' => 'Advanced settings',
        'batch_form_link_text' => 'Send batch',

        'desc_advancedSettings' => 'This plugin is highly configurable and therefore this form does not contain all settings. You can find the other settings in the "%1$s". Once you have completed and saved the settings over here, you can visit that form to fill in the advanced settings.',
        'menu_basicSettings' => 'Extensions → Modules → Acumulus → Edit',
    );
}
