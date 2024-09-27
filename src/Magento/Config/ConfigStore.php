<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Config;

use Magento\Backend\App\ConfigInterface;
use Magento\Config\Model\ResourceModel\Config as MagentoModelConfig;
use Magento\Framework\App\Config as MagentoAppConfig;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\ObjectManager;
use Siel\Acumulus\Magento\Helpers\Registry;
use Siel\Acumulus\Config\ConfigStore as BaseConfigStore;

use Siel\Acumulus\Meta;

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

        // Force a cache clear., see test method saveNew() below
        /** @var \Magento\Framework\App\Config $config */
        $config = ObjectManager::getInstance()->get(MagentoAppConfig::class);
        $config->clean();
        return true;
    }

    public function saveNew(array $values): bool
    {
        // I tried a various number of cache clean solutions, but I can't get any of them
        // to work. Saving config on the Magento own config pages doesn't work either, so
        // I give up.

        \Siel\Acumulus\Helpers\Container::getContainer()->getLog()
            ->notice('ConfigStore::save(): saving %s', json_encode($values, Meta::JsonFlags));
        $serializedValues = serialize($values);

        $configWriter = $this->getWriterInterface();
        $configWriter->save($this->configPath . $this->configKey, $serializedValues);

        $cacheTypeList = Registry::getInstance()->get(\Magento\Framework\App\Cache\TypeListInterface::class);
        $cacheTypeList->cleanType(\Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER);

        $reinitableConfig = Registry::getInstance()->get(\Magento\Framework\App\Config\ReinitableConfigInterface::class);
        $reinitableConfig->reinit();

        /** @var \Magento\Framework\App\Cache\Manager $cacheManager */
        \Siel\Acumulus\Helpers\Container::getContainer()->getLog()
            ->notice('ConfigStore::save(): flushing all caches');
        $cacheManager = Registry::getInstance()->get(\Magento\Framework\App\Cache\Manager::class);
        $cacheManager->flush(['config']);
        $cacheManager->flush($cacheManager->getAvailableTypes());

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

    protected function getWriterInterface(): WriterInterface
    {
        return Registry::getInstance()->get(WriterInterface::class);
    }
}
