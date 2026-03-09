<?php
/**
 * @noinspection LongLine
 * @noinspection HtmlUnknownTarget
 */

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Helpers;

use Siel\Acumulus\Helpers\ModuleSpecificTranslations as BaseModuleSpecificTranslations;

/**
 * Contains plugin specific overrides.
 *
 * @noinspection PhpUnused
 */
class ModuleSpecificTranslations extends BaseModuleSpecificTranslations
{
    protected array $nl = [
        'button_link' => '<a href="%2$s" class="button button-primary button-large">%1$s</a>',

        // @todo: Address used for vat calculations.
        'fiscal_address_setting' => 'Instellingen » tab Belasting » Bereken belasting gebaseerd op',

//        'custom_field' => \__('Custom Field', 'woocommerce'),
        // Rate our plugin message.
        'review_on_marketplace' => 'Zou jij ons een review willen geven op de WHMCS marketplace?',
        // These are the same for English thus no need to copy them.
        'module' => 'addon',
        'review_url' => 'https://marketplace.whmcs.com/product/6765-siel-acumulus#reviews',
    ];

    protected array $en = [

        // @todo: Address used for vat calculations.
        'fiscal_address_setting' => 'Settings » tab Tax » Calculate tax based on',

        // Rate our plugin message.
        'review_on_marketplace' => 'Would you please give us a review on the WHMCS marketplace?',
    ];
}
