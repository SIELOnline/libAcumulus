<?php
namespace Siel\Acumulus\Invoice;

/**
 * CompletorStrategyBase is the base class for all strategies that might
 * be able to complete invoice lines by applying a strategy to divide a
 * remaining vat amount over lines without a vat rate yet.
 */
abstract class CompletorStrategyBase {

  /** @var int Indication of the order of execution of the strategy. */
  static $tryOrder = 50;

  /** @var array[] */
  protected $invoice;

  /** @var array[] */
  protected $possibleVatTypes;

  /** @var array[] */
  protected $possibleVatRates;

  /** @var array[] */
  protected $lines2Complete;

  /** @var array[] */
  protected $completedLines;

  /** @var float */
  protected $vat2Divide;

  /** @var float */
  protected $vatAmount;

  /** @var float */
  protected $invoiceAmount;

  /** @var string */
  protected $description = 'Not yet set';

  /** @var array[] */
  protected $vatBreakdown;

  /**
   * @param array $invoice
   * @param array $possibleVatTypes
   * @param array $possibleVatRates
   */
  public function __construct(array $invoice, array $possibleVatTypes, array $possibleVatRates) {
    $this->invoice = $invoice;
    $this->possibleVatTypes = $possibleVatTypes;
    $this->possibleVatRates = $possibleVatRates;
    $this->initAmounts();
    $this->initLines2Complete();
    $this->initVatBreakdown();
  }

  /**
   * Returns the (parameterised) description of the latest tried strategy.
   *
   * @return string
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * Returns the completed lines, should only be called after success.
   *
   * @return array[]
   */
  public function getCompletedLines() {
    return $this->completedLines;
  }

  /**
   * Initializes the amount properties.
   *
   * to be able to calculate the amounts, at least 2 of the 3 meta amounts
   * meta-invoicevatamount, meta-invoiceamountinc, or meta-invoiceamount must
   * be known.
   */
  protected function initAmounts() {
    $invoicePart = &$this->invoice['customer']['invoice'];
    $this->vatAmount = isset($invoicePart['meta-invoicevatamount']) ? $invoicePart['meta-invoicevatamount'] : $invoicePart['meta-invoiceamountinc'] - $invoicePart['meta-invoiceamount'];
    $this->invoiceAmount = isset($invoicePart['meta-invoiceamount']) ? $invoicePart['meta-invoiceamount'] : $invoicePart['meta-invoiceamountinc'] - $invoicePart['meta-invoicevatamount'];

    // The vat amount to divide over the non completed lines is the total vat
    // amount of the invoice minus all known vat amounts per line.
    $this->vat2Divide = (float) $this->vatAmount;
    foreach ($invoicePart['line'] as $line) {
      if ($line['meta-vatrate-source'] !== Creator::VatRateSource_Strategy) {
        // Deduct the vat amount from this line: if set, deduct it directly,
        // otherwise calculate the vat amount using the vat rate and unit price.
        if (isset($line['vatamount'])) {
          $this->vat2Divide -= $line['vatamount'] * $line['quantity'];
        }
        else {
          $this->vat2Divide -= ($line['vatrate'] / 100.0) * $line['unitprice'] * $line['quantity'];
        }
      }
    }
  }

  /**
   * Initializes $this->lines2Complete with all strategy lines.
   */
  protected function initLines2Complete() {
    $this->lines2Complete = array();
    foreach ($this->invoice['customer']['invoice']['line'] as $line) {
      if ($line['meta-vatrate-source'] === Creator::VatRateSource_Strategy) {
        $this->lines2Complete[] = $line;
      }
    }
  }

  /**
   * Initializes $this->vatBreakdown with a breakdown of vat rates and amounts
   * occurring on the invoice (and not being strategy lines).
   */
  protected function initVatBreakdown() {
    $this->vatBreakdown = array();
    foreach ($this->invoice['customer']['invoice']['line'] as $line) {
      if (isset($line['vatrate'])) {
        $amount = $line['unitprice'] * $line['quantity'];
        $vatAmount = $line['vatrate'] / 100.0 * $amount;
        $vatRate = sprintf('%.3f', $line['vatrate']);
        // Add amount to existing vatrate line or
        if (isset($this->vatBreakdown[$vatRate])) {
          $breakdown = &$this->vatBreakdown[$vatRate];
          $breakdown['vatamount'] += $vatAmount;
          $breakdown['amount'] += $amount;
          $breakdown['count']++;
        }
        else {
          $this->vatBreakdown[$vatRate] = array(
            'vatrate' => $vatRate,
            'vatamount' => $vatAmount,
            'amount' => $amount,
            'count' => 1,
          );
        }
      }
    }
  }

  /**
   * Returns the minimum vat rate on the invoice.
   *
   * @return array
   *   A vat rate overview (array with vatrate, vatamount, amount, count).
   */
  protected function getVatBreakDownMinRate() {
    $result = array('vatrate' => PHP_INT_MAX);
    foreach ($this->vatBreakdown as $breakDown) {
      if ($breakDown['vatrate'] < $result['vatrate']) {
        $result = $breakDown;
      }
    }
    return $result;
  }

  /**
   * Returns the maximum vat rate on the invoice.
   *
   * @return array
   *   A vat rate overview (array with vatrate, vatamount, amount, count).
   */
  protected function getVatBreakDownMaxRate() {
    $result = array('vatrate' => -PHP_INT_MAX);
    foreach ($this->vatBreakdown as $breakDown) {
      if ($breakDown['vatrate'] > $result['vatrate']) {
        $result = $breakDown;
      }
    }
    return $result;
  }

  /**
   * Returns the key component vat rate on the invoice (NL: hoofdbestanddeel).
   *
   * @return array
   *   A vat rate overview (array with vatrate, vatamount, amount, count).
   */
  protected function getVatBreakDownMaxAmount() {
    $result = array('amount' => -PHP_INT_MAX);
    foreach ($this->vatBreakdown as $breakDown) {
      if ($breakDown['amount'] > $result['amount']) {
        $result = $breakDown;
      }
    }
    return $result;
  }

  /**
   * Applies the strategy to see if it results in a valid solution.
   *
   * @return bool
   *   Success.
   */
  public function apply() {
    $this->completedLines = array();
    $this->init();
    return $this->checkPreconditions() && $this->execute();
  }

  /**
   * Strategy dependent initialization.
   */
  protected function init() {
  }

  /**
   * Some strategies can only be tried when some conditions are met, e.g. if
   * there's only 1 line to complete.
   *
   * These checks are extracted from execute() and should be placed in this
   * method instead.
   *
   * @return bool
   *   True if this strategy might be used, false otherwise.
   */
  protected function checkPreconditions() {
    return true;
  }

  /**
   * Tries to apply the strategy.
   *
   * A strategy might be parameterised, most likely by 1 or more vat rates, and
   * thus involve multiple tries. The implementations of execute() should run
   * all possible permutations and return true as soon as 1 permutation is
   * successful.
   *
   * If the result is true, $this->$completedLines must contain the completed
   * lines that will replace the lines to complete.
   *
   * @return bool
   *   true the strategy has been applied successfully, false otherwise.
   */
  abstract protected function execute();

  /**
   * Completes a line by filling in the given vat rate and calculating other
   * possibly missing fields (vatamount, unitprice).
   *
   * @param $line2Complete
   * @param $vatRate
   *
   * @return float
   *   The vat amount for the completed line.
   */
  protected function completeLine(&$line2Complete, $vatRate) {
    if (!isset($line2Complete['quantity'])) {
      $line2Complete['quantity'] = 1;
    }
    $line2Complete['vatrate'] = $vatRate;
    if (isset($line2Complete['unitprice'])) {
      $line2Complete['vatamount'] = ($line2Complete['vatrate'] / 100.0) * $line2Complete['unitprice'];
    }
    else { // isset($line2Complete['unitpriceinc'])
      $line2Complete['vatamount'] = ($line2Complete['vatrate'] / (100.0 + $line2Complete['vatrate'])) * $line2Complete['unitpriceinc'];
      $line2Complete['unitprice'] = $line2Complete['unitpriceinc'] - $line2Complete['vatamount'];
    }
    $this->completedLines[] = $line2Complete;
    return $line2Complete['vatamount'] * $line2Complete['quantity'];
  }

  /**
   * @param float $f1
   * @param float $f2
   * @param float $maxDiff
   *
   * @return bool
   */
  protected function floatsAreEqual($f1, $f2, $maxDiff = 0.02) {
    return abs($f2 - $f1) < $maxDiff;
  }
}
