<?php
namespace Siel\Acumulus\Magento\Helpers;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains plugin specific overrides.
 */
class ModuleSpecificTranslations extends TranslationCollection
{
    protected $nl = array(
        'see_billing_address' => 'Verzendadresgegevens, bevat dezelfde eigenschappen als het "billingAddress" object hierboven',

        'field_triggerOrderStatus' => 'Bestelling, op basis van bestelstatus(sen)',
    );

    protected $en = array(
        'see_billing_address' => 'Shipping address, contains the same properties as the "billingAddress" object above',

        'field_triggerOrderStatus' => 'Order, based on status(es)',
    );
}
