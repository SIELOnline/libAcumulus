<?php
namespace Siel\Acumulus\Magento\Magento1\Config;

use Mage;
use Siel\Acumulus\Config\ConfigStore as BaseConfigStore;

/**
 * Implements the connection to the Magento config component.
 */
class ConfigStore extends BaSeConfigStore
{
    protected $configPath = 'siel_acumulus/';

    /**
     * {@inheritdoc}
     */
    public function load()
    {
        // Load the values from the web shop specific configuration.
        $values = Mage::getStoreConfig($this->configPath . $this->configKey);
        $values = unserialize($values);
        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $values)
    {
        $values = serialize($values);
        /** @var \Mage_Core_Model_Config $configModel */
        $configModel = Mage::getModel('core/config');
        $configModel->saveConfig($this->configPath . $this->configKey, $values);
        Mage::getConfig()->reinit();
        return true;
    }

    /**
     * {@deprecated} Only still here for use during update.
     *
     * @param array $keys
     *
     * @return array
     */
    public function loadOld(array $keys)
    {
        $result = array();
        /** @var \Mage_Core_Model_Config $configModel */
        $configModel = Mage::getModel('core/config');
        // Load the values from the web shop specific configuration.
        foreach ($keys as $key) {
            $value = Mage::getStoreConfig($this->configPath . $key);
            // Delete the value, this will only be used one more time: during
            // updating to 5.4.0.
            $configModel->deleteConfig($this->configPath . $key);
            // Do not overwrite defaults if no value is set.
            if (isset($value)) {
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
