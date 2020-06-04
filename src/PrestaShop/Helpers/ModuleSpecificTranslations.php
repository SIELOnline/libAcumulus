<?php
namespace Siel\Acumulus\PrestaShop\Helpers;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains plugin specific overrides.
 */
class ModuleSpecificTranslations extends TranslationCollection
{
    protected $nl = [
        'module' => 'module',
        'button_link' => '<a href="%2$s" class="btn btn-default"><i class="process-icon-cogs"></i>%1$s</a>',

        'menu_advancedSettings' => 'Geavanceerde instellingen → Acumulus geavanceerde instellingen',
        'menu_basicSettings' => 'Instellingen → Acumulus → Configureer',

        'see_billing_address' => 'Verzendadresgegevens, bevat dezelfde eigenschappen als het "address_invoice" object hierboven',
    ];

    protected $en = [
        'module' => 'module',
        'menu_advancedSettings' => 'Advanced Parameters → Acumulus advanced settings',
        'menu_basicSettings' => 'Settings → Acumulus → Configure',

        'see_billing_address' => 'Shipping address, contains the same properties as the "address_invoice" object above',
    ];
}
