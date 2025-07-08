<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Config;

use Magento\Backend\App\ConfigInterface;
use Magento\Framework\App\Config as MagentoConfig;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\ObjectManager;
use SensitiveParameter;
use Siel\Acumulus\Magento\Helpers\Registry;
use Siel\Acumulus\Config\ConfigStore as BaseConfigStore;

use function is_string;

/**
 * Implements the connection to the Magento 2 config component.
 *
 * Note: When testing this module with phpunit, the cache location differs from when
 * running the site:
 *   - phpunit: \\wsl.localhost\Ubuntu\home\erwin\Projecten\Acumulus\Magento\www24linux\dev\tests\integration\tmp\sandbox-acumulus\var\cache
 *              \\wsl.localhost\Ubuntu\home\erwin\Projecten\Acumulus\Magento\www24linux\app\etc\config.php
 *   - website: \\wsl.localhost\Ubuntu\home\erwin\Projecten\Acumulus\Magento\www24linux\var\cache
 * So be sure to clear all these caches. use these commands:
 * - sudo chmod a+rwX -R *
 * - sudo rm -R *
 */
class ConfigStore extends BaseConfigStore
{
    protected string $configPath = 'siel_acumulus/';
    protected array $values;

    public function load(): array
    {
        $values = $this->getConfigInterface()->getValue($this->configPath . $this->configKey);
        if (empty($values)) {
            // This may happen after installation.
            $values = [];
        } elseif (is_string($values)) {
            $values = unserialize($values, ['allowed_classes' => false]);
        }
        return $values;
    }

    public function save(#[SensitiveParameter] array $values): bool
    {
        $serializedValues = serialize($values);
        $this->getConfigWriterInterface()->save($this->configPath . $this->configKey, $serializedValues);

        // Force a cache clear as this is not done automatically after an update.
        $this->getMagentoConfig()->clean();

        return true;
    }

    protected function getConfigInterface(): ConfigInterface
    {
        return Registry::getInstance()->get(ConfigInterface::class);
    }

    protected function getConfigWriterInterface(): WriterInterface
    {
        return Registry::getInstance()->get(WriterInterface::class);
    }

    protected function getMagentoConfig(): MagentoConfig
    {
        return ObjectManager::getInstance()->get(MagentoConfig::class);
    }
}
