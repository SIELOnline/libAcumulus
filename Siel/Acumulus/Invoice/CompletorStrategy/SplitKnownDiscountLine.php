<?php
namespace Siel\Acumulus\Invoice\CompletorStrategy;

use Siel\Acumulus\Invoice\CompletorInvoiceLines;
use Siel\Acumulus\Invoice\CompletorStrategyBase;
use Siel\Acumulus\Invoice\Creator;

/**
 * Class SplitKnownDiscountLine implements a vat completor strategy by using the
 * 'meta-linediscountamount' tag to split a discount line over several lines
 * with different vat rates as it may be considered as the total discount over
 * multiple products that may have different vat rates.
 *
 * Preconditions:
 * - lines2Complete contains 1 line that may be split.
 * - There should be other lines that have a 'meta-linediscountamount' tag and
 *   an exact vat rate, and these amounts must add up to the amount of the line
 *   that is to be split.
 * - This strategy should be executed early as it is as sure win and can even
 *   be used as a partial solution.
 *
 * Strategy:
 * The amounts in the lines that have a 'meta-linediscountamount' tag are summed
 * by their vat rates and these "discount amounts per vat rate" are used to
 * create the lines that replace the single discount line.
 *
 * Current usages:
 * - Magento
 */
class SplitKnownDiscountLine extends CompletorStrategyBase {

  /**
   * @var int
   *   This strategy should be tried last before the fail strategy as there
   *   are chances of returning a wrong true result.
   */
  static public $tryOrder = 1;

  /** @var array[] */
  protected $splitLines;

  /** @var array */
  protected $splitLine;

  /** @var float */
  protected $knownDiscountAmount;

  /** @var float */
  protected $knownDiscountVatAmount;

  /** @var float[] */
  protected $discountsPerVatRate;

  /**
   * {@inheritdoc}
   */
  protected function init() {
    $splitLines = array();
    foreach ($this->lines2Complete as $line) {
      if (isset($line2Complete['meta-strategy-split']) && $line2Complete['meta-strategy-split']) {
        $splitLines[] = $line;
      }
    }
    if (count($splitLines) === 1) {
      $this->splitLine = reset($splitLines);
    }

    $this->knownDiscountAmount = 0.0;
    $this->knownDiscountVatAmount = 0.0;
    foreach ($this->invoice['customer']['invoice']['line'] as $line) {
      if (isset($line['meta-linediscountamount']) &&
        ($line['meta-vatrate-source'] === Creator::VatRateSource_Exact || $line['meta-vatrate-source'] === CompletorInvoiceLines::VatRateSource_Calculated_Corrected)) {
        $this->knownDiscountAmount += $line['meta-linediscountamount'];
        $this->knownDiscountVatAmount += $line['unitprice'] * $line['quantity'] * ($line['vatrate'] / 100.0);
        if (isset($this->discountsPerVatRate[$line['vatrate']])) {
          $this->discountsPerVatRate[$line['vatrate']] += $line['meta-linediscountamount'];
        }
        else {
          $this->discountsPerVatRate[$line['vatrate']] = $line['meta-linediscountamount'];
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkPreconditions() {
    return isset($this->splitLine)
      && (   (isset($this->splitLine['unitprice']) && $this->floatsAreEqual($this->splitLine['unitprice'], $this->knownDiscountAmount))
          || (isset($this->splitLine['unitpriceinc']) && $this->floatsAreEqual($this->splitLine['unitpriceinc'], $this->knownDiscountAmount + $this->knownDiscountVatAmount)));
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    return $this->splitDiscountLine();
  }

  /**
   * @return bool
   */
  protected function splitDiscountLine() {
    $this->description = "SplitKnownDiscountLine({$this->knownDiscountAmount}, {$this->knownDiscountVatAmount})";
    $this->completedLines = array();
    foreach ($this->discountsPerVatRate as $vatRate => $discountAmount) {
      $line = array(
        'itemnumber' => $this->splitLine['itemnumber'],
        'product' => "{$this->splitLine['product']} ($vatRate%)",
        'unitprice' => $discountAmount
      );
      $this->completeLine($line, $vatRate);
    }
  }

}
