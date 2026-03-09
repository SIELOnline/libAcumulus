<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Config;

use Siel\Acumulus\Config\Environment as EnvironmentBase;
use Siel\Acumulus\Meta;
use WHMCS\Application;

use function dirname;

/**
 * Defines the WHMCS web shop specific environment.
 */
class Environment extends EnvironmentBase
{
    protected function setShopEnvironment(): void
    {
        $addonFolder = dirname(__FILE__, 7); // @todo
        $composer = json_decode(file_get_contents($addonFolder . '/composer.json'), true, flags: Meta::JsonFlags);
        $this->data['moduleVersion'] = $composer->version ?? 'unknown';
        $this->data['shopVersion'] = Application::getVersion(); // @todo
    }
}
