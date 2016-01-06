<?php
namespace Siel\Acumulus\WooCommerce\Shop;

use Siel\Acumulus\Shop\ConfigStore as BaseConfigStore;

/**
 * Implements the connection to the WordPress config component.
 */
class ConfigStore extends BaSeConfigStore {

  /**
   * {@inheritdoc}
   */
  public function getShopEnvironment() {
    global $wp_version, $woocommerce;
    $environment = array(
      // Lazy load is no longer needed (as in L3) as this method will only be
      // called when the config gets actually queried.
      'moduleVersion' => \Acumulus::create()->getVersionNumber(),
      'shopName' => $this->shopName,
      'shopVersion' => (isset($woocommerce) ? $woocommerce->version : 'unknown') . ' (WordPress: ' . $wp_version . ')',
    );
    return $environment;
  }

  /**
   * {@inheritdoc}
   */
  public function load(array $keys) {
    $result = array();
    // Load the values from the web shop specific configuration.
    $configurationValues = get_option('acumulus');
    if (is_array($configurationValues)) {
      foreach ($keys as $key) {
        // Do not overwrite defaults if no value is set.
        if (isset($configurationValues[$key])) {
          $result[$key] = $configurationValues[$key];
        }
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $values) {
    $configurationValues = array();
    foreach ($values as $key => $value) {
      if ($value !== NULL) {
        $configurationValues[$key] = $value;
      }
    }
    return update_option('acumulus', $configurationValues);
  }

}
