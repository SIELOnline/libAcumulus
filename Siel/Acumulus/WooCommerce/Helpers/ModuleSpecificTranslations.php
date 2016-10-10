<?php
namespace Siel\Acumulus\Woocommerce\Helpers;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains plugin specific overrides.
 */
class ModuleSpecificTranslations extends TranslationCollection
{
    protected $nl = array(
        'button_link' => '<a href="%2$s" class="button button-primary button-large">%1$s</a>',
    );
}
