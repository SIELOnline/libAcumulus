<?php
namespace Siel\Acumulus\TestWebShop\Config;

use Siel\Acumulus\Config\Environment as EnvironmentBase;

use const Siel\Acumulus\Version;

/**
 * Defines the PrestaShop web shop specific environment.
 */
class Environment extends EnvironmentBase
{
    private $configFile = __DIR__ . '/../../../../config/db.json';

    /**
     * {@inheritdoc}
     */
    protected function setShopEnvironment(): void
    {
        $this->data['moduleVersion'] = Version;
        $this->data['shopVersion'] = Version;
    }

    /**
     * Returns the values of the database variables 'version' and 'version_comment'.
     */
    protected function executeQuery(string $query): array
    {
        $parameters = $this->loadConfig();
        $mysqli = new \mysqli($parameters->hostName, $parameters->user, $parameters->password);
        return $mysqli->query($query)->fetch_all();
    }

    private function loadConfig(): object
    {
        return json_decode(file_get_contents($this->configFile), false);
    }
}
