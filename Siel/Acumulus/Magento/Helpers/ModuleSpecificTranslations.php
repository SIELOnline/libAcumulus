<?php
namespace Siel\Acumulus\Magento\Helpers;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains plugin specific overrides.
 */
class ModuleSpecificTranslations extends TranslationCollection
{
    protected $nl = array(
        'button_link' => '<a href="%2$s" class="form-button" style="text-decoration: none; color: #fff; padding: 2px 7px 3px" onmouseover="this.style.background=\'#f77c16 url(../../../../skin/adminhtml/default/default/images/btn_over_bg.gif) repeat-x scroll 0 0\'" onmouseout="this.style.background=\'#ffac47 url(../../../../skin/adminhtml/default/default/images/btn_bg.gif) repeat-x scroll 0 0\'">%1$s</a>',

        'menu_advancedSettings' => 'Acumulus → Acumulus geavanceerde instellingen',
        'menu_basicSettings' => 'Acumulus → Acumulus instellingen',

        'see_billing_address' => 'Verzendadresgegevens, bevat dezelfde eigenschappen als het "billingAddress" object hierboven',
    );

    protected $en = array(
        'menu_advancedSettings' => 'Acumulus → Acumulus advanced settings',
        'menu_basicSettings' => 'Acumulus → Acumulus settings',
    );
}
