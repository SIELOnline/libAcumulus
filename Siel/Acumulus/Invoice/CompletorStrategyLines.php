<?php
namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\TranslatorInterface;

/**
 * The invoice lines completor class provides functionality to correct and
 * complete invoice lines before sending them to Acumulus.
 *
 * This class:
 * - Validates (and correct rounding errors of) vat rates using the VAT rate
 *   lookup webservice call.
 * - Adds vat rates to 0 price lines (with a 0 price and thus 0 vat, not all
 *   web shops can fill in a vat rate).
 * - Adds vat rates to lines that need a strategy to compute their vat rates
 * - Adds other missing but required fields on the invoice lines. For now,
 *   unitprice can be missing (the line will have the unitpriceinc value and
 *   with the vatrate computed, we can also calculate the unitprice).
 * - Adds the vat type based on inspection of the completed invoice.
 *
 * Each invoice lines has 1 or more of the following keys:
 * -itemnumber
 * -product
 * -unitprice
 * -vatrate
 * -quantity
 * -costprice: optional, only for margin products
 *
 * Additional keys, not recognised by the API, but used by this completor to
 * complete the invoice lines:
 * - unitpriceinc: the price of the item per unit including VAT.
 * - vatamount: the amount of vat per unit.
 * - meta-vatrate-source: the source for the vatrate value. Can be one of:
 *   - exact: should be an existing VAT rate.
 *   - calculated: should be close to an existing VAT rate, but may contain a
 *       rounding error.
 *   - completor: zero price lines to be filled in by the completor with the
 *     most used VAT rate. these are like free shipping or discounts that are
 *     only there for info (discounts already processed in the product prices).
 *   - strategy: to be filled in by a tax divide strategy. This may lead to
 *     the line being split into multiple lines.
 * - (*)meta-line-price: the total price for this line excluding VAT.
 * - (*)meta-line-priceinc: the total price for this line including VAT.
 * - meta-line-vatamount: the amount of VAT for the whole line.
 * - meta-line-type: the type of line (order, shipping, discount, etc.)
 * (*) = these are not yet used.
 *
 * @package Siel\Acumulus
 */
class CompletorStrategyLines {

  /** @var \Siel\Acumulus\Helpers\TranslatorInterface */
  protected $translator;

  /** @var array[] */
  protected $invoice;

  /** @var array[] */
  protected $invoiceLines;

  /** @var Source */
  protected $source;

  /**
   * @var int[]
   *   The list of possible vat types, initially filled with possible vat types
   *   based on client country, invoiceHasLineWithVat(), is_company(), and the
   *   digital services setting. But then reduced by VAT rates we find on the
   *   order lines.
   */
  protected $possibleVatTypes;

  /** @var array[] */
  protected $possibleVatRates;

  /**
   * Constructor.
   *
   * @param \Siel\Acumulus\Helpers\TranslatorInterface $translator
   */
  public function __construct(TranslatorInterface $translator) {
    $this->translator = $translator;
  }

  /**
   * Completes the invoice with default settings that do not depend on shop
   * specific data.
   *
   * @param array $invoice
   *   The invoice to complete.
   * @param Source $source
   *   The source object for which this invoice was created.
   * @param int[] $possibleVatTypes
   * @param array[] $possibleVatRates
   *
   * @return array
   *   The completed invoice.
   */
  public function complete(array $invoice, Source $source, array $possibleVatTypes, array $possibleVatRates) {
    $this->invoice = $invoice;
    $this->invoiceLines = &$this->invoice['customer']['invoice']['line'];
    $this->source = $source;
    $this->possibleVatTypes = $possibleVatTypes;
    $this->possibleVatRates = $possibleVatRates;

    $this->completeStrategyLines();
    return $this->invoice;
  }

  /**
   * Complete all lines that need a vat divide strategy to compute correct
   * values.
   */
  protected function completeStrategyLines() {
    if ($this->invoiceHasStrategyLine()) {
      $input = array_reduce($this->possibleVatRates, function($carry, $item) {
          return $carry . (empty($carry) ? '' : ',') . '[' . $item['vatrate'] . '%,' . $item['vattype'] .']';
        },
        '');
      $this->invoice['customer']['invoice']['meta-completor-strategy-input'] = "strategy input($input)";

      $strategies = $this->getStrategyClasses();
      foreach ($strategies as $strategyClass) {
        /** @var CompletorStrategyBase $strategy  */
        $strategy = new $strategyClass($this->translator, $this->invoice, $this->possibleVatTypes, $this->possibleVatRates, $this->source);
        if ($strategy->apply()) {
          $this->replaceLinesCompleted($strategy->getLinesCompleted() , $strategy->getCompletedLines());
          if (empty($this->invoice['customer']['invoice']['meta-completor-strategy-used'])) {
            $this->invoice['customer']['invoice']['meta-completor-strategy-used'] = $strategy->getDescription();
          }
          else {
            $this->invoice['customer']['invoice']['meta-completor-strategy-used'] .= '; ' . $strategy->getDescription();
          }
          // Allow for partial solutions: a strategy may correct only some of
          // the strategy lines and leave the rest up to other strategies.
          if (!$this->invoiceHasStrategyLine()) {
            break;
          }
        }
      }
    }
  }

  /**
   * Returns whether the invoice has lines that are to be completed using a tax
   * divide strategy.
   *
   * @return bool
   */
  protected function invoiceHasStrategyLine() {
    $result = false;
    foreach ($this->invoiceLines as $line) {
      if ($line['meta-vatrate-source'] === Creator::VatRateSource_Strategy
        || (!empty($line['meta-strategy-split']) && isset($line['meta-vatrate-matches']))) {
        $result = true;
        break;
      }
    }
    return $result;
  }

  /**
   * Returns a list of strategy class names.
   *
   * @return string[]
   */
  protected function getStrategyClasses() {
    $result = array();

    // For now hardcoded, but this can be turned into a discovery.
    $namespace = '\Siel\Acumulus\Invoice\CompletorStrategy';
    $result[] = "$namespace\\SplitNonMatchingLine";
    $result[] = "$namespace\\SplitKnownDiscountLine";
    $result[] = "$namespace\\ApplySameVatRate";
    $result[] = "$namespace\\SplitLine";
    $result[] = "$namespace\\TryAllVatRatePermutations";
    $result[] = "$namespace\\Fail";

    return $result;
  }

  /**
   * Replaces all completed strategy lines with the given completed lines.
   *
   * @param int[] $linesCompleted
   * @param array[] $completedLines
   *   An array of completed invoice lines to replace the strategy lines with.
   */
  protected function replaceLinesCompleted(array $linesCompleted, array $completedLines) {
    // Remove old strategy lines that are now completed.
    $lines = array();
    foreach ($this->invoice['customer']['invoice']['line'] as $key => $line) {
      if (!in_array($key, $linesCompleted)) {
        $lines[] = $line;
      }
    }

    // And merge in the new completed ones.
    foreach ($completedLines as &$completedLine) {
      if ($completedLine['meta-vatrate-source'] === Creator::VatRateSource_Strategy) {
        $completedLine['meta-vatrate-source'] = Completor::VatRateSource_Strategy_Completed;
      }
    }
    $this->invoice['customer']['invoice']['line'] = array_merge($lines, $completedLines);
  }

}
