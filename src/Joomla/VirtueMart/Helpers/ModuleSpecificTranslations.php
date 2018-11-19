<?php
namespace Siel\Acumulus\Joomla\VirtueMart\Helpers;

use Siel\Acumulus\Joomla\Helpers\ModuleSpecificTranslations as BaseModuleSpecificTranslations;

/**
 * Contains plugin specific overrides.
 */
class ModuleSpecificTranslations extends BaseModuleSpecificTranslations
{
    protected $nl = array(
        'see_properties_below' => 'Een bestelling bevat geen velden van zichzelf, gebruik de verzamelingen hieronder.',
        'see_bt' => 'Verzendadresgegevens (ST = Ship To), bevat dezelfde eigenschappen als het BT object - ofwel de factuuradresgegevens (BT = Bill To) - hierboven',
    );

    protected $en = array(
        'see_properties_below' => 'An order does not contain fields of its own, use the collections below.',
        'see_bt' => 'Shipping address (ST = Ship To), contains the same properties as the BT object - i.e. the invoice address (BT = Bill To) - above',
    );
}
