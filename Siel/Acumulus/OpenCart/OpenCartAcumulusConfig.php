<?php
/**
 * @file Contains class OpenCartAcumulusConfig.
 */
namespace Siel\Acumulus\OpenCart;

use Siel\Acumulus\BaseConfig;
use Siel\Acumulus\TranslatorInterface;

/**
 * Class OpenCartAcumulusConfig
 *
 * An OpenCart specific implementation of the Acumulus ConfigInterface that the
 * WebAPI and the OrderAdd classes need.
 */
class OpenCartAcumulusConfig extends BaseConfig {
  /**
   * Increase this value on each change:
   * - point release: bug fixes
   * - minor version: addition of minor features, backwards compatible
   * - major version: major or backwards incompatible changes
   *
   * @var string
   */
  public static $module_version = '3.3.0';


  /** @var \ModelSettingSetting */
  protected $configuration;

  /**
   * @param string|TranslatorInterface $language
   * @param \ModelSettingSetting $configuration
   *   The object to access OpenCart's configuration.
   */
  public function __construct($language, \ModelSettingSetting $configuration) {
    parent::__construct($language);
    $this->values = array_merge($this->values, array(
      'moduleVersion' => self::$module_version,
      'shopName' => 'OpenCart',
      'shopVersion' => VERSION,
    ));
    $this->configuration = $configuration;
  }

  /**
   * @inheritdoc
   */
  public function load() {
    // Load the values from the web shop specific configuration.
    $openCartValues = $this->configuration->getSetting('acumulus');
    foreach ($this->getKeys() as $key) {
      $this->values[$key] = array_key_exists($key, $openCartValues) ? $openCartValues[$key] : null;
    }
    // And cast them to their correct types.
    $this->castValues($this->values);
    return true;
  }

  /**
   * @inheritdoc
   */
  public function save(array $values) {
    $storeValues = array();
    foreach ($this->getKeys() as $key) {
      $storeValues[$key] = isset($values[$key]) && ($values[$key] !== '' || $key === 'emailonerror')
        ? $values[$key]
        : $this->get($key);
    }
    $this->configuration->editSetting('acumulus', $storeValues);
    return parent::save($values);
  }

}
