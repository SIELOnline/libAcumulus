<?php
namespace Siel\Acumulus\PrestaShop\Config;

use Db;
use Module;
use Siel\Acumulus\Config\Environment as EnvironmentBase;

/**
 * Defines the PrestaShop web shop specific environment.
 */
class Environment extends EnvironmentBase
{
    /**
     * {@inheritdoc}
     */
    protected function setShopEnvironment(): void
    {
        $this->data['moduleVersion'] = Module::getInstanceByName('acumulus')->version;
        $this->data['shopVersion'] = _PS_VERSION_;
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
