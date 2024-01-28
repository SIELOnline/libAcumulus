<?php
/**
 * @noinspection HtmlUnknownTarget
 */

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Helpers;

use Siel\Acumulus\Helpers\ModuleSpecificTranslations as BaseModuleSpecificTranslations;

/**
 * Contains plugin specific overrides.
 *
 * @noinspection PhpUnused
 */
class ModuleSpecificTranslations extends BaseModuleSpecificTranslations
{
    protected array $nl = [
        'module' => 'module',
        'button_link' => '<a href="%2$s" class="btn btn-default"><i class="process-icon-cogs"></i>%1$s</a>',
        'button_class' => 'btn btn-primary',
        'menu_advancedSettings' => 'Geavanceerde instellingen → Acumulus geavanceerde instellingen',
        'menu_basicSettings' => 'Instellingen → Acumulus → Configureer',

        'see_billing_address' => 'Verzendadres, bevat dezelfde eigenschappen als het "address_invoice" object hierboven',

        'vat_class' => 'belastingregel',
        'vat_classes' => 'belastingregels',

        // Address used for vat calculations.
        'fiscal_address_setting' => 'Internationaal » BTW » kopje Btw-opties » Gebaseerd op',
    ];

    protected array $en = [
        'module' => 'module',
        'menu_advancedSettings' => 'Advanced Parameters → Acumulus advanced settings',
        'menu_basicSettings' => 'Settings → Acumulus → Configure',

        'see_billing_address' => 'Shipping address, contains the same properties as the "address_invoice" object above',

        'vat_class' => 'tax rule',
        'vat_classes' => 'tax rules',

        // Address used for vat calculations.
        'fiscal_address_setting' => 'International » VAT » Heading VAT options » Based on',
    ];
}
