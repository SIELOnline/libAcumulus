<?php
namespace Siel\Acumulus\OpenCart\Config;

use DB;
use Siel\Acumulus\Config\Environment as EnvironmentBase;
use Siel\Acumulus\OpenCart\Helpers\Registry;

use const Siel\Acumulus\Version;

/**
 * Defines the OpenCart web shop specific environment.
 */
class Environment extends EnvironmentBase
{
    /**
     * {@inheritdoc}
     */
    public function setShopEnvironment(): void
    {
        // Module has same version as library.
        $this->data['moduleVersion'] = Version;
        $this->data['shopVersion'] = VERSION;
    }

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
