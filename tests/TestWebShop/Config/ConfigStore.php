<?php
namespace Siel\Acumulus\TestWebShop\Config;

use Siel\Acumulus\Config\ConfigStore as BaseConfigStore;

/**
 * Implements the connection to the TestWebShop config component.
 *
 * Create your own config.json with the following properties (values to be
 * filled in):
 * {"contractcode":"","username":"","password":"","emailonerror":""}
 */
class ConfigStore extends BaSeConfigStore
{
    private $configFile = __DIR__ . '/../../../../config/config.json';

    /**
     * {@inheritdoc}
     */
    public function load()
    {
        return is_readable($this->configFile) ? json_decode(file_get_contents($this->configFile), true) : [];
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $values)
    {
        file_put_contents($this->configFile, json_encode($values));
        return true;
    }
}
