<?php
/**
 * @file Contains Siel\Acumulus\Test\TestConfig.
 */

namespace Siel\Acumulus\Test;

use Siel\Acumulus\Common\BaseConfig;
use Siel\Acumulus\Common\ConfigInterface;
use Siel\Acumulus\Common\WebAPI;

/**
 * TestConfig defines a configuration object for test purposes.
 *
 * @package Siel\AcumulusTest
 */
class TestConfig extends BaseConfig {
  /**
   * @inheritdoc
   */
  public function __construct($language) {
    parent::__construct($language);
    $this->values = array_merge($this->values, array(
      'moduleVersion' => $this->values['libraryVersion'],
      'shopName' => 'Test',
      'shopVersion' => $this->values['libraryVersion'],
      'baseUri' => 'https://api.sielsystems.nl/acumulus',
      //'apiVersion' => 'stable',
      //'apiVersion' => 'dev',
      'apiVersion' => '1.22',
      'debug' => ConfigInterface::Debug_TestMode,
    ));
  }

  /**
   * @inheritdoc
   */
  public function load() {
    $this->values = array_merge($this->values, array(
      'contractcode' => '288252',
      'username' => 'erwind',
      'password' => 'yLWT8PFz',
      'emailonerror' => 'erwin@burorader.com',
      'emailonwarning' => 'erwin@burorader.com',
    ));
    $this->values['defaultCustomerType'] = 3;
    $this->values['defaultAccountNumber'] = 70582;
    $this->values['invoiceNrSource'] = true;
    $this->values['dateToUse'] = true;
    $this->values['defaultCostHeading'] = 48663;
    $this->values['defaultInvoiceTemplate'] = 39851;
    $this->values['triggerOrderStatus'] = 'paid';
    $this->values['useMargin'] = false;
    $this->values['overwriteIfExists'] = 1;
    return TRUE;
  }

  /**
   * @inheritdoc
   */
  public function save(array $values) {
    return TRUE;
  }
}
