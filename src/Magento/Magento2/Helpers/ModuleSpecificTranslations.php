<?php
namespace Siel\Acumulus\Magento\Magento2\Helpers;

use Siel\Acumulus\Magento\Helpers\ModuleSpecificTranslations as ModuleSpecificTranslationsBase;

/**
 * Contains plugin specific overrides.
 */
class ModuleSpecificTranslations extends ModuleSpecificTranslationsBase
{
    public function __construct()
    {
        $this->nl += [
            'button_link' => '<a href="%2$s" class="abs-action-primary" style="text-decoration: none; color: #fff">%1$s</a>',

            'menu_advancedSettings' => 'Winkels → Overige instellingen → Acumulus Advanced Config',
            'menu_basicSettings' => 'Winkels → Overige instellingen → Acumulus Config',

            // Rate our plugin message.
            'review_on_marketplace' => 'Zou jij ons een review willen geven op Magento Marketplace?',

            'module' => 'module',
            'review_url' => 'https://marketplace.magento.com/siel-acumulus-ma2.html',
        ];

        $this->en += [
            'menu_advancedSettings' => 'Stores → Other settings → Acumulus Advanced Config',
            'menu_basicSettings' => 'Stores → Other settings → Acumulus Config',

            // Rate our plugin message.
            'review_on_marketplace' => 'Would you please give us a review on Magento Marketplace?',

            'module' => 'module',
        ];
    }
}
