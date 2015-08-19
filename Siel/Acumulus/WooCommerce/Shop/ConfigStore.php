<?php
namespace Siel\Acumulus\WooCommerce\Shop;

use Siel\Acumulus\Shop\ConfigStoreInterface;

/**
 * Implements the connection to the WordPress config component.
 */
class ConfigStore implements ConfigStoreInterface {

  /**
   * {@inheritdoc}
   */
  public function getShopEnvironment() {
    global $wp_version, $woocommerce;
    $environment = array(
      'moduleVersion' => 'lazy-load',
      'shopName' => 'WooCommerce',
      'shopVersion' => (isset($woocommerce) ? $woocommerce->version : 'unknown') . ' (WordPress: ' . $wp_version . ')',
    );

    // @todo:'this code should get its place:
//    if ($key === 'moduleVersion' && $this->values[$key] === 'lazy-load') {
//      $this->values[$key] = \Acumulus::create()->getVersionNumber();
//    }


    return $environment;
  }

  /**
   * {@inheritdoc}
   */
  public function load(array $keys) {
    $result = array();
    // Load the values from the web shop specific configuration.
    $configurationValues = get_option('woocommerce_acumulus');
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
    return update_option('woocommerce_acumulus', $configurationValues);
  }

}
