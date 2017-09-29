<?php
namespace Siel\Acumulus\Joomla\HikaShop\Helpers;

use Siel\Acumulus\Joomla\Helpers\ModuleSpecificTranslations as BaseModuleSpecificTranslations;

/**
 * Contains plugin specific overrides.
 */
class ModuleSpecificTranslations extends BaseModuleSpecificTranslations
{
    protected $nl = array(
        'see_billing_address' => 'Verzendadresgegevens, bevat dezelfde eigenschappen als het "billing_address" object hierboven',
    );

    protected $en = array(
        'see_billing_address' => 'Shipping address, contains the same properties as the "billing_address" object above',
    );
}
