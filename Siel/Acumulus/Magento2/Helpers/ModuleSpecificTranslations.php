<?php
namespace Siel\Acumulus\Magento2\Helpers;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains plugin specific overrides.
 */
class ModuleSpecificTranslations extends TranslationCollection
{
    protected $nl = array(
        'button_link' => '<a href="%2$s" class="abs-action-primary" style="text-decoration: none; color: #fff">%1$s</a>',

        'menu_advancedSettings' => 'Winkels → Overige instellingen → Acumulus Advanced Config',
        'menu_basicSettings' => 'Winkels → Overige instellingen → Acumulus Config',
    );

    protected $en = array(
        'menu_advancedSettings' => 'Stores → Other settings → Acumulus Advanced Config',
        'menu_basicSettings' => 'Stores → Other settings → Acumulus Config',
    );
}
