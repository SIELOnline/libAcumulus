<?php
/**
 * @noinspection HtmlUnknownTarget
 */

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\Helpers;

use Siel\Acumulus\Helpers\ModuleSpecificTranslations as BaseModuleSpecificTranslations;

/**
 * Contains plugin specific overrides.
 *
 * @noinspection PhpUnused
 */
class ModuleSpecificTranslations extends BaseModuleSpecificTranslations
{
    protected array $nl = [
        'module' => 'extensie',
        'button_link' => '<a href="%2$s" class="btn btn-default"><span class="icon-cog"></span> %1$s</a>',
        'button_class' => 'btn btn-primary',

        'menu_advancedSettings' => 'Componenten → Acumulus → Geavanceerde instellingen',
        'menu_basicSettings' => 'Componenten → Acumulus → Instellingen',
    ];

    protected array $en = [
        'module' => 'extension',
    ];
}
