<?php
/**
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
        'module' => 'addon',
        'desc_module' => 'The Acumulus addon koppelt uw WHMCS site aan uw Acumulus online financiële administratie.',

        'button_link' => '<a href="%2$s" class="btn btn-default"><span class="icon-cog"></span> %1$s</a>',
        'button_class' => 'btn btn-primary',

        // Rate our plugin message.
        'review_on_marketplace' => 'Zou jij ons een review willen geven op de WHMCS marketplace?',
        'review_url' => 'https://marketplace.whmcs.com/product/6765-siel-acumulus#reviews',

        'install_success' => 'De Acumulus addon is succesvol geïnstalleerd, Vul nu meteen de basis-instellingen in.',
        'install_failure' => 'Er is een fout opgetreden tijdens het installeren van de Acumulus addon: %1$s',

        'uninstall_success' => 'De Acumulus addon is succesvol gedeactiveerd, de addon tabel in de database is verwijderd.',
        'uninstall_failure' => 'Er is een fout opgetreden tijdens het deactiveren van de Acumulus addon: %1$s',
    ];

    protected array $en = [
        'desc_module' => 'The Acumulus addon connects your WHMCS site to your Acumulus online financial administration.',

        // Rate our plugin message.
        'review_on_marketplace' => 'Would you please give us a review on the WHMCS marketplace?',
        'review_url' => 'https://marketplace.whmcs.com/product/6765-siel-acumulus#reviews',

        'install_success' => 'The Acumulus addon has been installed successfully, please continue by filling in the configuration data.',
        'install_failure' => 'Installing the addon failed: %1$s',

        'uninstall_success' => 'The Acumulus addon has been deactivated successfully, the addon specific data has been deleted.',
        'uninstall_failure' => 'Deactivating the Acumulus addon failed: %1$s',
    ];
}
