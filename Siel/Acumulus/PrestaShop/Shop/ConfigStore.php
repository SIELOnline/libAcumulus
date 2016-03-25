<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use Acumulus;
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
    public function getShopEnvironment()
    {
        $environment = array(
            'moduleVersion' => Acumulus::$module_version,
            'shopName' => $this->shopName,
            'shopVersion' => _PS_VERSION_,
        );
        return $environment;
    }

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
        $values = $this->saveCommon($values);

        $result = true;
        foreach ($values as $key => $value) {
            if ($value !== null) {
                $dbKey = substr(static::CONFIG_KEY . $key, 0, 32);
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
