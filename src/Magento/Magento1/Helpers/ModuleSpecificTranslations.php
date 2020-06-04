<?php
namespace Siel\Acumulus\Magento\Magento1\Helpers;

use Siel\Acumulus\Magento\Helpers\ModuleSpecificTranslations as ModuleSpecificTranslationsBase;

/**
 * Contains plugin specific overrides.
 */
class ModuleSpecificTranslations extends ModuleSpecificTranslationsBase
{
    public function __construct()
    {
        $this->nl += [
            'button_link' => '<a href="%2$s" class="form-button" style="text-decoration: none; color: #fff; padding: 2px 7px 3px" onmouseover="this.style.background=\'#f77c16 url(../../../../skin/adminhtml/default/default/images/btn_over_bg.gif) repeat-x scroll 0 0\'" onmouseout="this.style.background=\'#ffac47 url(../../../../skin/adminhtml/default/default/images/btn_bg.gif) repeat-x scroll 0 0\'">%1$s</a>',

            'menu_advancedSettings' => 'Acumulus → Acumulus geavanceerde instellingen',
            'menu_basicSettings' => 'Acumulus → Acumulus instellingen',
        ];

        $this->en += [
            'menu_advancedSettings' => 'Acumulus → Acumulus advanced settings',
            'menu_basicSettings' => 'Acumulus → Acumulus settings',
        ];
    }
}
