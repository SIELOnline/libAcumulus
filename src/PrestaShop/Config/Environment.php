<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Config;

use Db;
use Module;
use PrestaShop\PrestaShop\Core\Version;
use Siel\Acumulus\Config\Environment as EnvironmentBase;

/**
 * Defines the PrestaShop web shop specific environment.
 */
class Environment extends EnvironmentBase
{
    protected function setShopEnvironment(): void
    {
        $this->data['moduleVersion'] = Module::getInstanceByName('acumulus')->version;
        $this->data['shopVersion'] = Version::VERSION;
    }

    /**
     * Returns the values of the database variables 'version' and 'version_comment'.
     *
     * @throws \PrestaShopException
     *
     * @noinspection PhpMissingParentCallCommonInspection
     */
    protected function executeQuery(string $query): array
    {
        return $this->getDb()->executeS($query);
    }

    /**
     * Helper method to get the db object.
     *
     * @throws \PrestaShopException
     */
    protected function getDb(): Db
    {
        return Db::getInstance();
    }
}
