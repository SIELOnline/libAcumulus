<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use Configuration;
use Siel\Acumulus\Shop\ConfigStore as BaseConfigStore;

/**
 * Implements the connection to the PrestaShop config component.
 */
class ConfigStore extends BaSeConfigStore
{
    const CONFIG_KEY = 'ACUMULUS_';

    /**
     * {@inheritdoc}
     */
    public function load(array $keys)
    {
        $result = array();
        // Load the values from the web shop specific configuration.
        foreach ($keys as $key) {
            $dbKey = substr(static::CONFIG_KEY . $key, 0, 32);
            $value = Configuration::get($dbKey);
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

    /**
     * {@inheritdoc}
     */
    public function save(array $values)
    {
        $result = true;
        $defaults = $this->acumulusConfig->getDefaults();
        foreach ($values as $key => $value) {
            $dbKey = substr(static::CONFIG_KEY . $key, 0, 32);
            if ((isset($defaults[$key]) && $defaults[$key] === $value) || $value === null) {
              $result = Configuration::deleteByName($dbKey) && $result;
            } else {
                if (is_bool($value)) {
                    $value = $value ? '1' : '0';
                } elseif (is_array($value)) {
                    $value = serialize($value);
                }
                $result = Configuration::updateValue($dbKey, $value) && $result;
            }
        }
        return $result;
    }
}
