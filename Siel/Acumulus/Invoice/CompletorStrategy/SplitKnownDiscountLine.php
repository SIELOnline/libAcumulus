<?php
namespace Siel\Acumulus\Invoice\CompletorStrategy;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\CompletorInvoiceLines;
use Siel\Acumulus\Invoice\CompletorStrategyBase;
use Siel\Acumulus\Invoice\Creator;

/**
 * Class SplitKnownDiscountLine implements a vat completor strategy by using the
 * 'meta-linediscountamountinc' tag to split a discount line over several lines
 * with different vat rates as it may be considered as the total discount over
 * multiple products that may have different vat rates.
 *
 * Preconditions:
 * - lines2Complete contains 1 line that may be split.
 * - There should be other lines that have a 'meta-linediscountamountinc' tag
 *   and an exact vat rate, and these amounts must add up to the amount of the
 *   line that is to be split.
 * - This strategy should be executed early as it is as sure win and can even
 *   be used as a partial solution.
 *
 * Strategy:
 * The amounts in the lines that have a 'meta-linediscountamountinc' tag are
 * summed by their vat rates and these "discount amounts per vat rate" are used
 * to create the lines that replace the single discount line.
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
  protected $knownDiscountAmountInc;

  /** @var float */
  protected $knownDiscountVatAmount;

  /** @var float[] */
  protected $discountsPerVatRate;

  /**
   * {@inheritdoc}
   */
  protected function init() {
    $splitLines = array();
    foreach ($this->lines2Complete as $line2Complete) {
      if (isset($line2Complete['meta-strategy-split']) && $line2Complete['meta-strategy-split']) {
        $splitLines[] = $line2Complete;
      }
    }
    if (count($splitLines) === 1) {
      $this->splitLine = reset($splitLines);
    }

    $this->discountsPerVatRate = array();
    $this->knownDiscountAmountInc = 0.0;
    $this->knownDiscountVatAmount = 0.0;
    foreach ($this->invoice['customer']['invoice']['line'] as $line) {
      if (isset($line['meta-linediscountamountinc']) &&
        (in_array($line['meta-vatrate-source'], array(Creator::VatRateSource_Exact, Creator::VatRateSource_Exact0, CompletorInvoiceLines::VatRateSource_Calculated_Corrected)))) {
        $this->knownDiscountAmountInc += $line['meta-linediscountamountinc'];
        $this->knownDiscountVatAmount += $line['meta-linediscountamountinc'] * $line['vatrate'] / (100 + $line['vatrate']);
        $vatRate = sprintf('%.3f', $line['vatrate']);
        if (isset($this->discountsPerVatRate[$vatRate])) {
          $this->discountsPerVatRate[$vatRate] += $line['meta-linediscountamountinc'];
        }
        else {
          $this->discountsPerVatRate[$vatRate] = $line['meta-linediscountamountinc'];
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkPreconditions() {
    $result = FALSE;
    if (isset($this->splitLine)) {
      if ((isset($this->splitLine['unitprice']) && Number::floatsAreEqual($this->splitLine['unitprice'], $this->knownDiscountAmountInc - $this->knownDiscountVatAmount))
        || (isset($this->splitLine['unitpriceinc']) && Number::floatsAreEqual($this->splitLine['unitpriceinc'], $this->knownDiscountAmountInc))) {
        $result = TRUE;
      }
      // !Magento bug!
      // In credit memos, the DiscountAmount may differ from the summed discount
      // amounts per line, a.o. because refunded shipping costs do not advertise
      // any discount amount. Thus if the above comparison fails, we change the
      // discount line by "correcting" the discount amount.
      else if (defined('MAGENTO_ROOT') && substr($this->invoice['customer']['invoice']['number'], 0, strlen('CM')) === 'CM') {
        if (isset($this->splitLine['unitprice'])) {
          $this->splitLine['meta-magento-bug'] = sprintf('DiscountAmountEx = %f', $this->splitLine['unitprice']);
          $this->splitLine['unitprice'] = $this->knownDiscountAmountInc - $this->knownDiscountVatAmount;
          $result = TRUE;
        }
        if (isset($this->splitLine['unitprice']) || isset($this->splitLine['unitpriceinc'])) {
          if (isset($this->splitLine['meta-magento-bug'])) {
            $this->splitLine['meta-magento-bug'] .= ',';
          }
          else {
            $this->splitLine['meta-magento-bug'] = '';
          }
          $this->splitLine['meta-magento-bug'] .= sprintf('DiscountAmount = %f', $this->splitLine['unitpriceinc']);
          $this->splitLine['unitpriceinc'] = $this->knownDiscountAmountInc;
          $result = TRUE;
        }
      }
      // !End of Magento bug!
    }
    return $result;
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
    $this->description = "SplitKnownDiscountLine({$this->knownDiscountAmountInc}, {$this->knownDiscountVatAmount})";
    $this->completedLines = array();
    foreach ($this->discountsPerVatRate as $vatRate => $discountAmountInc) {
      $line = $this->splitLine;
      $line['product'] = "{$line['product']} ($vatRate%)";
      $this->completeLine($line, $vatRate);
    }
    return TRUE;
  }

}
