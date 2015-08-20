<?php
namespace Siel\Acumulus\Magento\Shop;

use Mage;
use Siel\Acumulus\Shop\ConfigStoreInterface;

/**
 * Implements the connection to the Magento config component.
 */
class ConfigStore implements ConfigStoreInterface {

  protected $configKey = 'siel_acumulus/';

  /**
   * {@inheritdoc}
   */
  public function getShopEnvironment() {
    $environment = array(
      'moduleVersion' => Mage::getConfig()->getModuleConfig("Siel_Acumulus")->version,
      'shopName' => 'Magento',
      'shopVersion' => Mage::getVersion(),
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
      $value = Mage::getStoreConfig($this->configKey . $key);
      // Do not overwrite defaults if no value is set.
      if (isset($value)) {
        $result[$key] = $value;
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $values) {
    foreach ($values as $key => $value) {
      if ($value !== NULL) {
        Mage::getModel('core/config')->saveConfig($this->configKey . $key, $value);
      }
    }
    Mage::getConfig()->reinit();
    return TRUE;
  }

}
