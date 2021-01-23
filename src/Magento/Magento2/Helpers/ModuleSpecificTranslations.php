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
            'button_link' => '<a href="%2$s" class="action-secondary">%1$s</a>',
            'button_class' => 'action-secondary',

            'menu_advancedSettings' => 'Winkels → Overige instellingen → Acumulus Advanced Config',
            'menu_basicSettings' => 'Winkels → Overige instellingen → Acumulus Config',

            // Rate our plugin message.
            'review_on_marketplace' => 'Zou jij ons een review willen geven op Magento Marketplace?',

            'module' => 'module',
            'review_url' => 'https://marketplace.magento.com/siel-acumulus-ma2.html',

            'vat_class' => 'BTW-tariefgroep',
            'vat_classes' => 'BTW-tariefgroepen',
        ];

        $this->en += [
            'menu_advancedSettings' => 'Stores → Other settings → Acumulus Advanced Config',
            'menu_basicSettings' => 'Stores → Other settings → Acumulus Config',

            // Rate our plugin message.
            'review_on_marketplace' => 'Would you please give us a review on Magento Marketplace?',

            'module' => 'module',

            'vat_class' => 'tax class',
            'vat_classes' => 'tax classes',
        ];
    }
}
