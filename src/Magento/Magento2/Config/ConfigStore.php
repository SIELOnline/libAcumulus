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
    protected $configPath = 'siel_acumulus/';

    /**
     * {@inheritdoc}
     */
    public function load()
    {
        $values = $this->getConfigInterface()->getValue($this->configPath . $this->configKey);
        if (!empty($values) && is_string($values)) {
            $values = unserialize($values);
        }
        return is_array($values) ? $values : [];
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $values)
    {
        // @todo: switch to json.
        $values = serialize($values);
        $this->getResourceConfig()->saveConfig($this->configPath . $this->configKey, $values, 'default', 0);

        // Force a cache clear.
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
        $config = $this->getConfigInterface();
        // Load the values from the web shop specific configuration.
        foreach ($keys as $key) {
            $value = $config->getValue($this->configPath . $key);
            // Delete the value, this will only be used one more time: during
            // updating to 5.4.0.
            $this->getResourceConfig()->deleteConfig($this->configPath . $key, 'default', 0);
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
