<?php
namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\Number;

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
class CompletorInvoiceLines {

  /** @var array[] */
  protected $invoice;

  /** @var array[] */
  protected $invoiceLines;

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
   */
  public function __construct() {
  }

  /**
   * Completes the invoice with default settings that do not depend on shop
   * specific data.
   *
   * @param array $invoice
   *   The invoice to complete.
   * @param int[] $possibleVatTypes
   * @param array[] $possibleVatRates
   *   A response structure where errors and warnings can be added. Any local
   *   messages will be added to arrays under the keys 'errors' and 'warnings'.
   *
   * @return array The completed invoice.
   * The completed invoice.
   */
  public function complete(array $invoice, array $possibleVatTypes, array $possibleVatRates) {
    $this->invoice = $invoice;
    $this->invoiceLines = &$this->invoice['customer']['invoice']['line'];
    $this->possibleVatTypes = $possibleVatTypes;
    $this->possibleVatRates = $possibleVatRates;

    $this->completeInvoiceLines();

    return $this->invoice;
  }

  /**
   * Completes the invoice lines.
   */
  protected function completeInvoiceLines() {
    $this->correctCalculatedVatRates();
    $this->addVatRateTo0PriceLines();
  }

  /**
   * Try to correct 'calculated' vat rates for rounding errors by matching them
   * with possible vatRates
   */
  protected function correctCalculatedVatRates() {
    foreach ($this->invoiceLines as &$line) {
      if (!empty($line['meta-vatrate-source']) && $line['meta-vatrate-source'] === Creator::VatRateSource_Calculated) {
        $line = $this->correctVatRateByRange($line);
      }
    }
  }

  /**
   * Checks and corrects a 'calculated' vat rate to an allowed vat rate.
   *
   * The check is done on comparing allowed vat rates with the meta-vatrate-min
   * and meta-vatrate-max values. If only 1 match is found that will be used.
   *
   * If multiple matches are found with all equal rates - e.g. Dutch and Belgium
   * 21% - the vat rate will be corrected, but the VAT Type will remain
   * undecided.
   *
   * This method is public to allow a 2nd call to just this method for a single
   * line added after a 1st round of correcting. Do not use unless
   * $this->possibleVAtRates has been initialized
   *
   * @param array $line
   *   A line with a calculated vat rate.
   * @return array
   *   The line with a corrected vat rate.
   */
  public function correctVatRateByRange(array $line) {
    $matchedVatRates = array();
    foreach ($this->possibleVatRates as $vatRate) {
      if ($vatRate['vatrate'] >= $line['meta-vatrate-min'] && $vatRate['vatrate'] <= $line['meta-vatrate-max']) {
        $matchedVatRates[] = $vatRate;
      }
    }

    $vatRate = $this->getUniqueVatRate($matchedVatRates);
    if ($vatRate === NULL || $vatRate === FALSE) {
      // We remove the calculated vatrate
      // @todo: pick closest or pick just 1?
      unset($line['vatrate']);
      $line['meta-vatrate-matches'] = $vatRate === NULL
        ? 'none'
        : array_reduce($matchedVatRates, function ($carry, $item) {
            return $carry . ($carry === '' ? '' : ',') . $item['vatrate'] . '(' . $item['vattype'] . ')';
          }, '');
      if (!empty($line['meta-strategy-split'])) {
        // Give the strategy phase a chance to correct this line.
        $line['meta-vatrate-source'] = Creator::VatRateSource_Strategy;
      }
    }
    else {
      $line['vatrate'] = $vatRate;
      $line['meta-vatrate-source'] = Completor::VatRateSource_Calculated_Corrected;
    }
    return $line;
  }

  /**
   * Determines if all (matched) vat rates are equal.
   *
   * @param array $matchedVatRates
   *
   * @return float|FALSE|NULL
   *   If all vat rates are equal that vat rate, null if $matchedVatRates is
   *   empty, false otherwise (multiple but different vat rates).
   */
  protected function getUniqueVatRate(array $matchedVatRates) {
    $result = array_reduce($matchedVatRates, function ($carry, $matchedVatRate) {
      if ($carry === NULL) {
        // 1st item: return its vat rate.
        return $matchedVatRate['vatrate'];
      }
      else if ($carry == $matchedVatRate['vatrate']) {
        // Note that in PHP: '21' == '21.0000' returns true. So using == works.
        // Vat rate equals all previous vat rates: return that vat rate.
        return $carry;
      }
      else {
        // Vat rate does not match previous vat rates or carry is already false,
        // return false.
        return FALSE;
      }
    }, NULL);
    return $result;
  }

  /**
   * Completes lines with free items (price = 0) by giving them the maximum tax
   * rate that appears in the other lines.
   */
  protected function addVatRateTo0PriceLines() {
    // Get appearing vat rates and their frequency.
    $vatRates = $this->getAppearingVatRates();

    // Get the highest vat rate.
    $maxVatRate = -1.0;
    foreach ($vatRates as $vatRate => $frequency) {
      if ((float) $vatRate > $maxVatRate) {
        $maxVatRate = (float) $vatRate;
      }
    }

    foreach ($this->invoiceLines as &$line) {
      if ($line['meta-vatrate-source'] === Creator::VatRateSource_Completor && $line['vatrate'] === null && Number::isZero($line['unitprice'])) {
        $line['vatrate'] = $maxVatRate;
        $line['meta-vatrate-source'] = Completor::VatRateSource_Completor_Completed;
      }
    }
  }

  /**
   * Returns a list of vat rates that actually appear in the invoice.
   *
   * @return array
   *  An array with the vat rates as key and the number of times they appear in
   *  the invoice lines as value.
   */
  protected function getAppearingVatRates() {
    $vatRates = array();
    foreach ($this->invoiceLines as $line) {
      if ($line['vatrate'] !== NULL) {
        if (isset($vatRates[$line['vatrate']])) {
          $vatRates[$line['vatrate']]++;
        }
        else {
          $vatRates[$line['vatrate']] = 1;
        }
      }
    }
    return $vatRates;
  }

}
