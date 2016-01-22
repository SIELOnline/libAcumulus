<?php
namespace Siel\Acumulus\OpenCart\Shop;

use Siel\Acumulus\OpenCart\Helpers\Registry;
use Siel\Acumulus\Shop\ConfigStore as BaseConfigStore;
use Siel\Acumulus\Web\ConfigInterface as ServiceConfigInterface;

/**
 * Implements the connection to the PrestaShop config component.
 */
class ConfigStore extends BaSeConfigStore {

  const CONFIG_KEY = 'ACUMULUS_';

  /**
   * {@inheritdoc}
   */
  public function getShopEnvironment() {
    $environment = array(
      'moduleVersion' => ServiceConfigInterface::libraryVersion,
      'shopName' => $this->shopName,
      'shopVersion' => VERSION,
    );
    return $environment;
  }

  /**
   * @return \ModelSettingSetting
   */
  public function getSettings() {
    Registry::getInstance()->load->model('setting/setting');
    return Registry::getInstance()->model_setting_setting;
  }

  /**
   * {@inheritdoc}
   */
  public function load(array $keys) {
    $result = array();
    // Load the values from the web shop specific configuration.
    $configurationValues = $this->getSettings()->getSetting('acumulus_siel');
    $configurationValues = isset($configurationValues['acumulus_siel_module']) ? $configurationValues['acumulus_siel_module'] : array();
    foreach ($keys as $key) {
      if (array_key_exists($key, $configurationValues)) {
        $result[$key] = $configurationValues[$key];
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $values) {
    parent::save($values);

    $configurationValues = $this->getSettings()->getSetting('acumulus_siel');
    $configurationValues = isset($configurationValues['acumulus_siel_module']) ? $configurationValues['acumulus_siel_module'] : array();
    $storeValues = array_merge($configurationValues, $values);
    return $this->getSettings()->editSetting('acumulus_siel', array('acumulus_siel_module' => $storeValues));
  }

}
