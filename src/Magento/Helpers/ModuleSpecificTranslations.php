<?php
namespace Siel\Acumulus\Magento\Helpers;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains plugin specific overrides.
 */
class ModuleSpecificTranslations extends TranslationCollection
{
    protected $nl = [
        'module' => 'extensie',
        'button_link' => '<a href="%2$s" class="action-secondary">%1$s</a>',
        'button_class' => 'action-secondary',
        'menu_advancedSettings' => 'Winkels → Overige instellingen → Acumulus Advanced Config',
        'menu_basicSettings' => 'Winkels → Overige instellingen → Acumulus Config',

        // Config form.
        'field_triggerOrderStatus' => 'Bestelling, op basis van bestelstatus(sen)',
        'vat_class' => 'BTW-tariefgroep',
        'vat_classes' => 'BTW-tariefgroepen',

        // Advanced config form.
        'see_billing_address' => 'Verzendadresgegevens, bevat dezelfde eigenschappen als het "billingAddress" object hierboven',

        // Rate our plugin message.
        'review_on_marketplace' => 'Zou jij ons een review willen geven op Magento Marketplace?',
        'review_url' => 'https://marketplace.magento.com/siel-acumulus-ma2.html',
    ];

    protected $en = [
        'module' => 'extension',
        'menu_advancedSettings' => 'Stores → Other settings → Acumulus Advanced Config',
        'menu_basicSettings' => 'Stores → Other settings → Acumulus Config',

        // Config form.
        'field_triggerOrderStatus' => 'Order, based on status(es)',
        'vat_class' => 'tax class',
        'vat_classes' => 'tax classes',

        // Advanced config form.
        'see_billing_address' => 'Shipping address, contains the same properties as the "billingAddress" object above',

        // Rate our plugin message.
        'review_on_marketplace' => 'Would you please give us a review on Magento Marketplace?',
    ];
}
