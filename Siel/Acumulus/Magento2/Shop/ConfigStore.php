<?php
namespace Siel\Acumulus\Magento2\Shop;

use Magento\Framework\AppInterface;
use Siel\Acumulus\Magento2\Helpers\Registry;
use Siel\Acumulus\Shop\ConfigStore as BaseConfigStore;

/**
 * Implements the connection to the Magento 2 config component.
 */
class ConfigStore extends BaSeConfigStore
{
    protected $configKey = 'siel_acumulus/';

    /**
     * {@inheritdoc}
     */
    public function getShopEnvironment()
    {
        $moduleResource = Registry::getInstance()->getModuleResource();
        $environment = array(
            'moduleVersion' => $moduleResource->getDbVersion('Siel_AcumulusMa2'),
            'shopName' => $this->shopName,
            'shopVersion' => AppInterface::VERSION,
        );

        return $environment;
    }

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
        $values = $this->saveCommon($values);
        foreach ($values as $key => $value) {
            if ($value !== null) {
                if (is_bool($value)) {
                    $value = $value ? 1 : 0;
                } elseif (is_array($value)) {
                    $value = serialize($value);
                }
                $resourceConfig->saveConfig($this->configKey . $key, $value, 'default', 0);
            }
        }

        /** @var \Magento\Framework\App\Cache\Frontend\Pool $cacheFrontendPool */
        $cacheFrontendPool = Registry::getInstance()->get('\Magento\Framework\App\Cache\Frontend\Pool');
        $cacheFrontendPool->get('default')->clean();
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
