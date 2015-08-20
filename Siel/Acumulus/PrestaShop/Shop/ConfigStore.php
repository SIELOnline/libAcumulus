<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use Acumulus;
use Configuration;
use Siel\Acumulus\Shop\ConfigStoreInterface;

/**
 * Implements the connection to the PrestaShop config component.
 */
class ConfigStore implements ConfigStoreInterface {

  const CONFIG_KEY = 'ACUMULUS_';

  /**
   * {@inheritdoc}
   */
  public function getShopEnvironment() {
    $environment = array(
      'moduleVersion' => Acumulus::$module_version,
      'shopName' => 'PrestaShop',
      'shopVersion' => _PS_VERSION_,
    );
    return $environment;
  }

  /**
   * {@inheritdoc}
   */
  public function load(array $keys) {
    $result = array();
    // Load the values from the web shop specific configuration.
    foreach ($keys as $key) {
      $dbKey = substr(static::CONFIG_KEY . $key, 0, 32);
      $value = Configuration::get($dbKey);
      // Do not overwrite defaults if no value is stored.
      if ($value !== false) {
        $result[$key] = $value;
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $values) {
    $result = true;
    foreach ($values as $key => $value) {
      if ($value !== null) {
        $dbKey = substr(static::CONFIG_KEY . $key, 0, 32);
        $result = Configuration::updateValue($dbKey, is_bool($value) ? ($value ? 1 : 0) : $value) && $result;
      }
    }
    return $result;
  }

}