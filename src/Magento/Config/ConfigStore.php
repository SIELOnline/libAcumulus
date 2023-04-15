<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Config;

use Magento\Backend\App\ConfigInterface;
use Magento\Config\Model\ResourceModel\Config as MagentoModelConfig;
use Magento\Framework\App\Config as MagentoAppConfig;
use Magento\Framework\App\ObjectManager;
use Siel\Acumulus\Magento\Helpers\Registry;
use Siel\Acumulus\Config\ConfigStore as BaseConfigStore;

use function is_array;
use function is_string;

/**
 * Implements the connection to the Magento 2 config component.
 */
class ConfigStore extends BaSeConfigStore
{
    protected string $configPath = 'siel_acumulus/';

    public function load(): array
    {
        $values = $this->getConfigInterface()->getValue($this->configPath . $this->configKey);
        if (!empty($values) && is_string($values)) {
            $values = unserialize($values, ['allowed_classes' => false]);
        }
        return is_array($values) ? $values : [];
    }

    public function save(array $values): bool
    {
        // @todo: switch to json.
        $serializedValues = serialize($values);
        $this->getResourceConfig()->saveConfig($this->configPath . $this->configKey, $serializedValues, 'default', 0);

        // Force a cache clear.
        /** @var \Magento\Framework\App\Config $config */
        $config = ObjectManager::getInstance()->get(MagentoAppConfig::class);
        $config->clean();
        return true;
    }

    protected function getConfigInterface(): ConfigInterface
    {
        return Registry::getInstance()->get(ConfigInterface::class);
    }

    protected function getResourceConfig(): MagentoModelConfig
    {
        return Registry::getInstance()->get(MagentoModelConfig::class);
    }
}
