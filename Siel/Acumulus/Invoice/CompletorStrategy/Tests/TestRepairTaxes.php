<?php

namespace  Siel\Acumulus\OpenCart\RepairTaxes\Tests;

use Siel\Acumulus\Web\Service;
use Siel\Acumulus\OpenCar\RepairTaxes\RepairTaxes;
use Siel\Acumulus\OpenCart\RepairTaxes\Test\TestConfig;

class TestRepairTaxes {
  /** @var \Siel\Acumulus\OpenCar\RepairTaxes\RepairTaxes */
  protected $repairTaxes;

  /** @var array */
  protected $repairLines;
  /** @var array */
  protected $productTaxes;
  /** @var array */
  protected $taxLines;
  /** @var string */
  protected $expectedStrategy;
  /** @var bool */
  protected $expectedSuccess;

  function __construct() {
    require_once(dirname(__FILE__) . '/../RepairTaxes.php');
    require_once(dirname(__FILE__) . '/TestConfig.php');
    $this->repairTaxes = new RepairTaxes();
  }

  protected function executeTest() {
    $success = $this->repairTaxes->execute($this->repairLines, $this->productTaxes, $this->taxLines, date('Y-m-d'));
    $strategyUsed = $this->repairTaxes->getTaxStrategyUsed();
    return $this->expectedSuccess === $success && $this->expectedStrategy === $strategyUsed;
  }

  public function test1() {
    $this->repairLines = array(
      array(
        'itemnumber' => 'shipping',
        'product' => 'Afleveren tot achter de deur',
        'unitprice' => '15.7024',
        'vatrate' => null,
        'quantity' => 1,
      ),
      array(
        'itemnumber' => 'cod_fee',
        'product' => 'Onder rembours',
        'unitprice' => '4.7500',
        'vatrate' => null,
        'quantity' => 1,
      ),
    );
    $this->productTaxes = array(
      21 => 18.7438,
    );
    $this->taxLines = array(22.041300);
    $this->expectedStrategy = 'TryAllTaxRatePermutations(21, 0)';
    $this->expectedSuccess = true;

    return $this->executeTest();
  }

  public function test2() {
    $this->repairLines = array(
      array(
        'itemnumber' => 'shipping',
        'product' => 'Afleveren tot achter de deur',
        'unitprice' => '15.7024',
        'vatrate' => null,
        'quantity' => 1,
      ),
      array(
        'itemnumber' => 'cod_fee',
        'product' => 'Onder rembours',
        'unitprice' => '4.7500',
        'vatrate' => null,
        'quantity' => 1,
      ),
    );
    $this->productTaxes = array(
      21 => 18.74,
    );
    $this->taxLines = array(23.04);
    $this->expectedStrategy = 'ApplySameTaxRate(21)';
    $this->expectedSuccess = true;

    return $this->executeTest();
  }

  public function test3() {
    $this->repairLines = array(
      array(
        'itemnumber' => 'shipping',
        'product' => 'Afleveren tot achter de deur',
        'unitprice' => '20.0000',
        'vatrate' => null,
        'quantity' => 1,
      ),
      array(
        'itemnumber' => 'cod_fee',
        'product' => 'Onder rembours',
        'unitprice' => '10',
        'vatrate' => null,
        'quantity' => 1,
      ),
    );
    $this->productTaxes = array(
      21 => 1,
    );
    $this->taxLines = array(1.0 + 4.20 + 0.60);
    $this->expectedStrategy = 'TryAllTaxRatePermutations(21.0000, 6.0000)';
    $this->expectedSuccess = true;

    return $this->executeTest();
  }
}
