<?php
namespace Siel\Acumulus\Shop;

/**
 * Defines an interface to access the shop specific's config store.
 */
abstract class ConfigStore implements ConfigStoreInterface
{
    /** @var ConfigInterface */
    protected $acumulusConfig;

    /**
     * Config setter.
     *
     * @param \Siel\Acumulus\Shop\ConfigInterface $config
     */
    public function setConfig(ConfigInterface $config)
    {
        $this->acumulusConfig = $config;
    }
}
