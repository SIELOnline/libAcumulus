<?php
namespace Siel\Acumulus\Magento\Helpers;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains plugin specific overrides.
 */
class ModuleSpecificTranslations extends TranslationCollection
{
    protected $nl = array(
        'module' => 'extensie',
        // Config form.
        'field_triggerOrderStatus' => 'Bestelling, op basis van bestelstatus(sen)',
        // Advanced config form.
        'see_billing_address' => 'Verzendadresgegevens, bevat dezelfde eigenschappen als het "billingAddress" object hierboven',
    );

    protected $en = array(
        'module' => 'extension',
        // Config form.
        'field_triggerOrderStatus' => 'Order, based on status(es)',
        // Advanced config form.
        'see_billing_address' => 'Shipping address, contains the same properties as the "billingAddress" object above',
    );
}
