<?php
namespace Siel\Acumulus\Joomla\Helpers;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains plugin specific overrides.
 */
class ModuleSpecificTranslations extends TranslationCollection
{
    protected $nl = array(
        'button_link' => '<a href="%2$s" class="btn btn-default"><span class="icon-cog"></span> %1$s</a>',

        'menu_advancedSettings' => 'Componenten → Acumulus → Geavanceerde instellingen',
        'menu_basicSettings' => 'Componenten → Acumulus → Instellingen',
    );
}
