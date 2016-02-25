<?php
namespace Siel\Acumulus\Shop;

/**
 * Defines an interface to access the shop specific's config store.
 */
interface ConfigStoreInterface
{
    /**
     * Returns an array with shop specific environment settings.
     *
     * @return array
     *   An array with keys:
     *   - moduleVersion
     *   - shopName
     *   - shopVersion
     */
    public function getShopEnvironment();

    /**
     * Loads the configuration from the actual configuration provider.
     *
     * @param array $keys
     *   An array of keys that are expected to be loaded.
     *
     * @return array
     *   An array with the raw (not necessarily casted) configuration values keyed
     *   by their name. Note that these values will overwrite the default values,
     *   so no NULL values should be returned, though other "empty" values (false,
     *   0) may be returned.
     */
    public function load(array $keys);

    /**
     * Stores the values to the actual configuration provider.
     *
     * @param array $values
     *   A keyed array that contains the values to store. Note that values may be
     *   "empty", eg 0 or false. Only NULL values may be ignored
     *
     * @return bool
     *   Success.
     */
    public function save(array $values);
}
