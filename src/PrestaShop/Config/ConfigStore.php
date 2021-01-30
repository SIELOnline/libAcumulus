<?php
namespace Siel\Acumulus\PrestaShop\Config;

use Configuration;
use Siel\Acumulus\Config\ConfigStore as BaseConfigStore;

/**
 * Implements the connection to the PrestaShop config component.
 */
class ConfigStore extends BaSeConfigStore
{
    const CONFIG_KEY = 'ACUMULUS_';

    /**
     * {@inheritdoc}
     */
    public function load()
    {
        $values = Configuration::get(strtoupper($this->configKey));
        $values = unserialize($values);
        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $values)
    {
        $values = serialize($values);
        $result = Configuration::updateValue(strtoupper($this->configKey), $values);
        return $result;
    }

    /**
     * @deprecated Only still here for use during update.
     *
     * @param array $keys
     *
     * @return array
     */
    public function loadOld(array $keys)
    {
        $result = [];
        // Load the values from the web shop specific configuration.
        foreach ($keys as $key) {
            $dbKey = substr('ACUMULUS_' . $key, 0, 32);
            $value = Configuration::get($dbKey);
            Configuration::deleteByName($dbKey);
            // Do not overwrite defaults if no value is stored.
            if ($value !== false) {
                if (is_string($value) && strpos($value, '{') !== false) {
                    $unserialized = @unserialize($value);
                    if ($unserialized !== false) {
                        $value = $unserialized;
                    }
                }
                $result[$key] = $value;
            }
        }
        return $result;
    }
}
