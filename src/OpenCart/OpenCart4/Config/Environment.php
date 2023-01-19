<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart4\Config;

use DB;
use Siel\Acumulus\Config\Environment as EnvironmentBase;
use Siel\Acumulus\OpenCart\OpenCart4\Helpers\Registry;

use function is_object;

use const Siel\Acumulus\Version;

/**
 * Defines the OpenCart web shop specific environment.
 */
class Environment extends EnvironmentBase
{
    /**
     * {@inheritdoc}
     */
    protected function setShopEnvironment(): void
    {
        // Module has same version as library.
        $this->data['moduleVersion'] = Version;
        $this->data['shopVersion'] = VERSION;
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection
     */
    protected function executeQuery(string $query): array
    {
        $result = $this->getDb()->query($query);
        return is_object($result) ? $result->rows : [];
    }

    /**
     * Helper method to get the db object.
     */
    protected function getDb(): DB
    {
        return Registry::getInstance()->db;
    }
}
