<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Config;

use Siel\Acumulus\Config\Environment as EnvironmentBase;
use Siel\Acumulus\Meta;
use Siel\Whmcs\Acumulus\Acumulus;
use WHMCS\Application;
use WHMCS\Database\Capsule;

use function dirname;

/**
 * Defines the WHMCS web shop specific environment.
 */
class Environment extends EnvironmentBase
{
    protected function setShopEnvironment(): void
    {
        $this->data['moduleVersion'] = acumulus_get()->getVersion();
        $results = localAPI('WhmcsDetails', []);
        $this->data['shopVersion'] = $results['whmcs']['version'] ?? 'unknown';
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection
     */
    protected function executeQuery(string $query): array
    {
        return Capsule::connection()->select($query);
    }
}
