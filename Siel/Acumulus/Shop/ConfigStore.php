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
     * ConfigStore constructor.
     *
     * @param \Siel\Acumulus\Shop\ConfigInterface $config
     */
    public function __construct(ConfigInterface $config)
    {
        $this->acumulusConfig = $config;
    }
}
