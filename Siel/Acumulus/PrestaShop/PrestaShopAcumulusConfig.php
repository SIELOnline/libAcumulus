<?php
/**
 * @file Contains class Siel\Acumulus\PrestaShop\PrestaShopAcumulusConfig.
 */
namespace Siel\Acumulus\PrestaShop;

use Configuration;
use Acumulus;
use Siel\Acumulus\BaseConfig;

/**
 * Class PrestaShopAcumulusConfig
 *
 * A Prestashop specific implementation of the Acumulus ConfigInterface that
 * the WebAPI and the InvoiceAdd classes need.
 */
class PrestaShopAcumulusConfig extends BaseConfig {

  /**
   * @inheritdoc
   */
  public function __construct($language) {
    parent::__construct($language);
    $this->values = array_merge($this->values, array(
      'moduleVersion' => Acumulus::$module_version,
      'shopName' => 'PrestaShop',
      'shopVersion' => _PS_VERSION_,
      //'debug' => true, // Uncomment to debug.
    ));
  }

  /**
   * @inheritdoc
   */
  public function load() {
    foreach ($this->getKeys() as $key) {
      $value = Configuration::get("ACUMULUS_$key");
      if ($value === false) {
        $value = null;
      }
      $this->values[$key] = $value;
    }
    return true;
  }

  /**
   * @inheritdoc
   */
  public function save(array $values) {
    foreach ($values as $key => $value) {
      // Only emailonerror may be an empty string.
      if ($value !== null && ($value !== '' || $key === 'emailonerror')) {
        Configuration::updateValue("ACUMULUS_$key", $value);
      }
    }
    return parent::save($values);
  }

}
