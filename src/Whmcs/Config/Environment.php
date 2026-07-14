<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Config;

use Siel\Acumulus\Config\Environment as EnvironmentBase;
use WHMCS\Database\Capsule;

/**
 * Defines the WHMCS web shop specific environment.
 */
class Environment extends EnvironmentBase
{
    protected function setShopEnvironment(): void
    {
        $this->data['shopName'] = strtoupper($this->data['shopName']);
        $this->data['moduleVersion'] = acumulus_get()->getVersion();
        $results = localAPI('WhmcsDetails', []);
        $this->data['shopVersion'] = $results['whmcs']['version'] ?? static::Unknown;
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection
     */
    protected function executeQuery(string $query): array
    {
        return Capsule::connection()->select($query);
    }
}
