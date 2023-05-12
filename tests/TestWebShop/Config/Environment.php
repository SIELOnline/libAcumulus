<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Config;

use Siel\Acumulus\Config\Environment as EnvironmentBase;

use const Siel\Acumulus\Version;

/**
 * Defines the PrestaShop web shop specific environment.
 */
class Environment extends EnvironmentBase
{
    protected function setShopEnvironment(): void
    {
        $this->data['moduleVersion'] = Version;
        $this->data['shopVersion'] = Version;
    }

    /**
     * Returns values for the database variables 'version' and
     * 'version_comment'.
     */
    protected function executeQuery(string $query): array
    {
        // 'show variables where Variable_name in ("version", "version_comment")'
        return [
          ['Variable_name' => 'version', 'Value' => '8.0.27'],
          ['Variable_name' => 'version_comment', 'Value' => 'MySQL Community Server - GPL'],
        ];
    }
}
