<?php
/**
 * @file Contains Siel\PrestashopTest\TestConfig.
 */

namespace Siel\Acumulus\Prestashop\Test;

use PrestashopAcumulusConfig;

/**
 * TestConfig defines a configuration object for test purposes.
 *
 * @package Siel\PrestashopTest
 */
class TestConfig extends PrestashopAcumulusConfig {

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
