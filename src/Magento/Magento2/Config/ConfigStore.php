<?php
namespace Siel\Acumulus\Magento\Magento2\Config;

use Magento\Framework\App\ObjectManager;
use Siel\Acumulus\Magento\Magento2\Helpers\Registry;
use Siel\Acumulus\Config\ConfigStore as BaseConfigStore;

/**
 * Implements the connection to the Magento 2 config component.
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
        $config = $this->getConfigInterface();
        // Load the values from the web shop specific configuration.
        foreach ($keys as $key) {
            $value = $config->getValue($this->configKey . $key);
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
        $resourceConfig = $this->getResourceConfig();
        $defaults = $this->acumulusConfig->getDefaults();
        foreach ($values as $key => $value) {
            if ((isset($defaults[$key]) && $defaults[$key] === $value) || $value === null) {
                $resourceConfig->deleteConfig($this->configKey . $key, 'default', 0);
            } else {
                if (is_bool($value)) {
                    $value = $value ? 1 : 0;
                } elseif (is_array($value)) {
                    $value = serialize($value);
                }
                $resourceConfig->saveConfig($this->configKey . $key, $value, 'default', 0);
            }
        }

        /** @var \Magento\Framework\App\Config $config */
	    $config = ObjectManager::getInstance()->get(\Magento\Framework\App\Config::class);
	    $config->clean();
        return true;
    }

    /**
     * @return \Magento\Backend\App\ConfigInterface
     */
    protected function getConfigInterface()
    {
        return Registry::getInstance()->getConfigInterface();
    }

    /**
     * @return \Magento\Config\Model\ResourceModel\Config
     */
    protected function getResourceConfig()
    {
        return Registry::getInstance()->getResourceConfig();
    }

}
