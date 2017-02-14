<?php
namespace Siel\Acumulus\Shop;

/**
 * Defines an interface to access the shop specific's config store.
 */
abstract class ConfigStore implements ConfigStoreInterface
{
    /** @var ConfigInterface */
    protected $acumulusConfig;

    /** @var string */
    protected $shopName;

    /**
     * ConfigStore constructor.
     *
     * @param \Siel\Acumulus\Shop\ConfigInterface $config
     * @param string $shopNamespace
     */
    public function __construct(ConfigInterface $config, $shopNamespace)
    {
        $this->acumulusConfig = $config;
        $pos = strrpos($shopNamespace, '\\');
        $this->shopName = $pos !== false ? substr($shopNamespace, $pos + 1) : $shopNamespace;
    }
}
