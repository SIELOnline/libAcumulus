<?php
/**
 * @file Contains class Siel\Acumulus\WooCommerce\WooCommerceAcumulusConfig.
 */
namespace Siel\Acumulus\WooCommerce;

use Siel\Acumulus\BaseConfig;

/**
 * Class WooCommerceAcumulusConfig
 *
 * A WooCommerce specific implementation of the Acumulus ConfigInterface that
 * the WebAPI and the InvoiceAdd classes need.
 *
 * This class uses the WP options mechanism.
 */
class WooCommerceAcumulusConfig extends BaseConfig {

  /**
   * @inheritdoc
   */
  public function __construct($language) {
    parent::__construct($language);

    global $wp_version, $woocommerce;
    $this->values = array_merge($this->values, array(
      'moduleVersion' => WOOCOMMERCE_ACUMULUS_VERSION,
      'shopName' => 'WooCommerce',
      'shopVersion' => (isset($woocommerce) ? $woocommerce->version : 'unknown') . ' (WordPress: ' . $wp_version . ')',
      'debug' => true, // Uncomment to debug.
    ));
  }

  /**
   * @inheritdoc
   */
  public function load() {
    $configurationValues = get_option('woocommerce_acumulus');
    if (is_array($configurationValues)) {
      foreach ($this->getKeys() as $key) {
        $this->values[$key] = array_key_exists($key, $configurationValues) ? $configurationValues[$key] : null;
      }
      // And cast them to their correct types.
      $this->castValues($this->values);
      return true;
    }
    return false;
  }

  /**
   * @inheritdoc
   */
  public function save(array $values) {
    $configurationValues = array();
    foreach ($values as $key => $value) {
      // Only emailonerror may be an empty string.
      if ($value !== null && ($value !== '' || $key === 'emailonerror')) {
        $configurationValues[$key] = $value;
      }
    }
    update_option('woocommerce_acumulus', $configurationValues);
    return parent::save($values);
  }

}
