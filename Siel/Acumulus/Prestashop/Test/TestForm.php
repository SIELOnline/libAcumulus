<?php

/**
 * @file Contains Siel\Acumulus\Prestashop\Test\TestForm.
 */
namespace Siel\Acumulus\Prestashop\Test;

use Acumulus;
use Order;
use PrestashopAcumulusConfig;
use Siel\Acumulus\WebAPI;
use Tools;


/**
 * Class TestForm
 */
class TestForm {

  /** @var Acumulus */
  protected $module;

  /** @var PrestashopAcumulusConfig */
  protected $acumulusConfig;

  /** @var WebAPI */
  protected $webAPI;

  /**
   * @param PrestashopAcumulusConfig $config
   * @param Acumulus $module
   */
  public function __construct(PrestashopAcumulusConfig $config, Acumulus $module) {
    $this->module = $module;
    $this->acumulusConfig = $config;
    $this->webAPI = new WebAPI($this->acumulusConfig);
  }

  public function getContent() {
    $output = array();

    if (Tools::isSubmit('submitTestForm')) {
      $output = array_merge($output, $this->processForm());
    }
    $output = $output = array_merge($output, $this->getForm());

    return $output;
  }

  private function processForm() {
    $orderIds = Order::getOrdersIdByDate('2010-01-01', '2020-31-12');
    $local = Tools::getValue('local_1', false);
    $oldValue = $this->acumulusConfig->setLocal($local);
    $oldDebug = $this->acumulusConfig->setDebug(true);
    foreach ($orderIds as $orderId) {
      $this->module->sendOrderToAcumulus(new Order($orderId));
    }
    $this->acumulusConfig->setDebug($oldDebug);
    $this->acumulusConfig->setLocal($oldValue);

    $result = array(array(
      'type' => 'free',
      'label' => $this->module->displayConfirmation('Er zijn ' . count($orderIds) . ' orders verstuurd. Zie de logfile voor de resultaten'),
      'name' => 'legendTest',
      'required' => false,
    ));
    return $result;
  }

  private function getForm() {
    $result = array();

    $fieldset = array();

    $fieldset[] = array(
      'type' => 'free',
      'label' => '<p>Klik op onderstaande button om alle orders te versturen.</p>',
      'name' => 'legendTest',
      'required' => false,
    );

    $options = array(
      array(
        'id' => 'staylocal',
        'name' => 'Do not send orders to Acumulus but only log them',
        'val' => '1',
      ),
    );
    $fieldset[] = array(
      'type' => 'checkbox',
      'label' => 'Stay local',
      'name' => 'local',
      'values' => array(
        'query' => $options,
        'id' => 'val',
        'name' => 'name'
      ),
    );

    $fieldset[] = array(
      'type' => 'free',
      'label' => '<p><input type="submit" name="submitTestForm" formAction="" value="Test sending orders ..."/></p>',
      'name' => 'freeSubmitTestForm',
      'required' => false,
    );

    // Using <fieldset>s seem to be impossible. Add all fields at the same level
    // with a free field before them.
    $result[] = array(
      'type' => 'free',
      'label' => '<h2>Test Form</h2>',
      'name' => 'legendTest',
      'required' => false,
    );
    $result = array_merge($result, $fieldset);

    return $result;
  }
}
