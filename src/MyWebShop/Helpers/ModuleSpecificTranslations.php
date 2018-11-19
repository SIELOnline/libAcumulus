<?php
namespace Siel\Acumulus\MyWebShop\Helpers;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains plugin specific translation overrides.
 *
 * @todo: You can override any text you want to make it conform with the
 *  vocabulary as used in yor webshop. The examples below are ones you probably
 *  should override anyway (but not all of them). If you see any text that you
 *  want to rephrase, search for it, and copy the key over here and give it
 *  your own value.
 */
class ModuleSpecificTranslations extends TranslationCollection
{
    protected $nl = array(
        'button_link' => '<a href="%2$s" class="btn btn-default"><i class="process-icon-cogs"></i>%1$s</a>',

        'menu_advancedSettings' => 'Geavanceerde instellingen → Acumulus geavanceerde instellingen',
        'menu_basicSettings' => 'Instellingen → Acumulus → Configureer',
    );

    protected $en = array(
        'menu_advancedSettings' => 'Advanced Parameters → Acumulus advanced settings',
        'menu_basicSettings' => 'Settings → Acumulus → Configure',
    );
}
