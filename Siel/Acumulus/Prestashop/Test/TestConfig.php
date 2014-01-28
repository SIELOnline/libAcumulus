<?php
/**
 * @file Contains Siel\PrestaShopTest\TestConfig.
 */

namespace Siel\Acumulus\PrestaShop\Test;

use Siel\Acumulus\PrestaShop\PrestaShopAcumulusConfig;

/**
 * TestConfig defines a configuration object for test purposes.
 *
 * @package Siel\PrestashopTest
 */
class TestConfig extends PrestaShopAcumulusConfig {

  /**
   * @inheritdoc
   */
  public function __construct($language) {
    parent::__construct($language);
    $this->values = array_merge($this->values, array(
      'local' => true,
      'debug' => true,
    ));
  }

  /**
   * @inheritdoc
   */
  public function save(array $values) {
    return TRUE;
  }
}
