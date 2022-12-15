<?php

declare(strict_types=1);

namespace Siel\Acumulus\MyWebShop\Config;

use Db;
use Module;
use Siel\Acumulus\Config\Environment as EnvironmentBase;

/**
 * Defines the MyWebShop web shop specific environment.
 */
class Environment extends EnvironmentBase
{
    /**
     * {@inheritdoc}
     */
    protected function setShopEnvironment(): void
    {
        $this->data['moduleVersion'] = Module::getInstanceByName('acumulus')->version;
        $this->data['shopVersion'] = SHOP_VERSION;
    }

    /**
     * Returns the values of the database variables 'version' and 'version_comment'.
     */
    protected function executeQuery(string $query): array
    {
        return Db::getInstance()->executeS($query);
    }
}
