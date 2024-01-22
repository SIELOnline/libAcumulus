<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Config;

use AppKernel;
use Db;
use Module;
use Siel\Acumulus\Config\Environment as EnvironmentBase;

/**
 * Defines the PrestaShop web shop specific environment.
 */
class Environment extends EnvironmentBase
{
    protected function setShopEnvironment(): void
    {
        $this->data['moduleVersion'] = Module::getInstanceByName('acumulus')->version;
        $this->data['shopVersion'] = AppKernel::VERSION;
    }

    /**
     * Returns the values of the database variables 'version' and 'version_comment'.
     *
     * @throws \PrestaShopDatabaseException
     */
    protected function executeQuery(string $query): array
    {
        return $this->getDb()->executeS($query);
    }

    /**
     * Helper method to get the db object.
     */
    protected function getDb(): Db
    {
        return Db::getInstance();
    }
}
