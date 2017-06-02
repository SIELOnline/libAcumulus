<?php
namespace Siel\Acumulus\Magento\Magento1\Shop;

use Mage;
use Siel\Acumulus\Shop\ConfigStore as BaseConfigStore;

/**
 * Implements the connection to the Magento config component.
 */
class ConfigStore extends BaSeConfigStore
{
    protected $configKey = 'siel_acumulus/';

    /**
     * {@inheritdoc}
     */
    public function load(array $keys)
    {
        $result = array();
        // Load the values from the web shop specific configuration.
        foreach ($keys as $key) {
            $value = Mage::getStoreConfig($this->configKey . $key);
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

    /**
     * {@inheritdoc}
     */
    public function save(array $values)
    {
        /** @var \Mage_Core_Model_Config $configModel */
        $configModel = Mage::getModel('core/config');
        $defaults = $this->acumulusConfig->getDefaults();
        foreach ($values as $key => $value) {
            if ((isset($defaults[$key]) && $defaults[$key] === $value) || $value === null) {
                $configModel->deleteConfig($this->configKey . $key);
            } else {
                if (is_bool($value)) {
                    $value = $value ? 1 : 0;
                } elseif (is_array($value)) {
                    $value = serialize($value);
                }
                $configModel->saveConfig($this->configKey . $key, $value);
            }
        }
        Mage::getConfig()->reinit();
        return true;
    }
}
