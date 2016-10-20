<?php
namespace Siel\Acumulus\Shop;

/**
 * Defines an interface to access the shop specific's config store.
 */
abstract class ConfigStore implements ConfigStoreInterface
{
    /** @var string */
    protected $shopName;

    /**
     * ConfigStore constructor.
     *
     * @param string $shopNamespace
     */
    public function __construct($shopNamespace)
    {
        $pos = strrpos($shopNamespace, '\\');
        $this->shopName = $pos !== false ? substr($shopNamespace, $pos + 1) : $shopNamespace;
    }

    /**
     * {@inheritdoc}
     *
     * This base implementation returns an empty array: no shop specific default
     * overrides.
     */
    public function getShopDefaults()
    {
        return array();
    }
}
