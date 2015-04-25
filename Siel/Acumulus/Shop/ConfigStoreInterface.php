<?php
namespace Siel\Acumulus\Shop;

/**
 * Defines an interface to access the shop specific's config store.
 */
interface ConfigStoreInterface {

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
   *   An array with the configuration values keyed by their name.
   */
  public function load(array $keys);

  /**
   * Stores the values to the actual configuration provider.
   *
   * @param array $values
   *   A keyed array that contains the values to store.
   *
   * @return bool
   *   Success.
   */
  public function save(array $values);

}
