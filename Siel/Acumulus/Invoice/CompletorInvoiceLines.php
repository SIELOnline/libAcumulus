<?php
namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\Countries;
use Siel\Acumulus\Helpers\TranslatorInterface;
use Siel\Acumulus\Web\Service;

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
 *   - completor: to be filled in by the completor.
 *   - strategy: to be filled in by a tax divide strategy. This may lead to
 *      this line being split into multiple lines.
 * - (*)meta-lineprice: the total price for this line excluding VAT.
 * - (*)meta-linepriceinc: the total price for this line including VAT.
 * - meta-linevatamount: the amount of VAT for the whole line.
 * - meta-line-type: the type of line (order, shipping, discount, etc.)
 * (*) = these are not yet used.
 *
 * @package Siel\Acumulus
 */
class CompletorInvoiceLines {

  const VatRateSource_Calculated_Corrected = 'calculated-corrected';

  /** @var \Siel\Acumulus\Invoice\ConfigInterface */
  protected $config;

  /** @var \Siel\Acumulus\Helpers\TranslatorInterface */
  protected $translator;

  /** @var \Siel\Acumulus\Web\Service */
  protected $acumulusWebService;

  /** @var \Siel\Acumulus\Helpers\Countries */
  protected $countries;

  /** @var array[] */
  protected $messages;

  /** @var array[] */
  protected $invoice;

  /** @var array[] */
  protected $invoiceLines;

  /** @var Source */
  protected $source;

  /** @var int[] */
  protected $possibleVatTypes;

  /** @var array[] */
  protected $possibleVatRates;

  /**
   * Constructor.
   *
   * @param \Siel\Acumulus\Invoice\ConfigInterface $config
   * @param \Siel\Acumulus\Helpers\TranslatorInterface $translator
   * @param \Siel\Acumulus\Web\Service $service
   */
  public function __construct(ConfigInterface $config, TranslatorInterface $translator, Service $service) {
    $this->config = $config;

    $this->translator = $translator;
    $invoiceHelperTranslations = new Translations();
    $this->translator->add($invoiceHelperTranslations);

    $this->acumulusWebService = $service;
    $this->countries = new Countries();
  }

  /**
   * Helper method to translate strings.
   *
   * @param string $key
   *  The key to get a translation for.
   *
   * @return string
   *   The translation for the given key or the key itself if no translation
   *   could be found.
   */
  protected function t($key) {
    return $this->translator->get($key);
  }

  /**
   * Completes the invoice with default settings that do not depend on shop
   * specific data.
   *
   * @param array $invoice
   *   The invoice to complete.
   * @param Source $source
   *   The source object for which this invoice was created.
   * @param array $messages
   *   A response structure where errors and warnings can be added. Any local
   *   messages will be added to arrays under the keys 'errors' and 'warnings'.
   *
   * @return array
   *   The completed invoice.
   */
  public function complete(array $invoice, Source $source, array &$messages) {
    $this->invoice = $invoice;
    $this->invoiceLines = &$this->invoice['customer']['invoice']['line'];
    $this->source = $source;
    $this->messages = &$messages;

    $this->completeInvoiceLines();
    $this->completeVatType();
    return $this->invoice;
  }

  /**
   * Completes the invoice lines.
   */
  protected function completeInvoiceLines() {
    $this->initPossibleVatTypes();
    $this->initPossibleVatRates();
    $this->reduceVatTypesByVatRatesBySource(array(Creator::VatRateSource_Exact, Creator::VatRateSource_Exact0));
    $this->correctCalculatedVatRates();
    $this->reduceVatTypesByVatRatesBySource(static::VatRateSource_Calculated_Corrected);
    $this->addVatRateTo0PriceLines();
    $this->completeStrategyLines();
    $this->reduceVatTypesByVatRatesBySource(Creator::VatRateSource_Strategy);
  }

  /**
   * Initializes the list of possible vat types for this invoice.
   *
   * The list of possible vat types depends on:
   * - whether there are lines with vat or if all lines appear vat free.
   * - the country of the client.
   * - optionally, the date of the invoice.
   */
  protected function initPossibleVatTypes() {
    $possibleVatTypes = array();
    if (!empty($this->invoice['customer']['invoice']['vattype'])) {
      // If shop specific code or an event handler has already set the vat type,
      // we obey so.
      $possibleVatTypes[] = $this->invoice['customer']['invoice']['vattype'];
    }
    else {
      if (!$this->invoiceHasLineWithVat()) {
        // National/EU reversed vat or no vat (rest of world).
        if ($this->isNl() && $this->isCompany()) {
          $possibleVatTypes[] = ConfigInterface::VatType_NationalReversed;
        }
        else if ($this->isEu() && $this->isCompany()) {
          $possibleVatTypes[] = ConfigInterface::VatType_EuReversed;
        }
        else if ($this->isOutsideEu()) {
          $possibleVatTypes[] = ConfigInterface::VatType_RestOfWorld;
        }
        else {
          // Warning + fall back.
          $this->messages['warnings'][] = array(
            'code' => 'Order',
            'codetag' => $this->getInvoiceReference(),
            'message' => $this->t('message_warning_no_vat'),
          );
          $possibleVatTypes[] = ConfigInterface::VatType_National;
          $possibleVatTypes[] = ConfigInterface::VatType_EuReversed;
          $possibleVatTypes[] = ConfigInterface::VatType_NationalReversed;
        }
      }
      else {
        // NL or EU Foreign vat.
        $possibleVatTypes[] = ConfigInterface::VatType_National;
        if ($this->isEu() && $this->getInvoiceDate() >= '2015-01-01') {
          // As of 2015, electronic services should be taxed with the rates of
          // the clients' country.
          $possibleVatTypes[] = ConfigInterface::VatType_ForeignVat;
        }
      }
    }
    $this->possibleVatTypes = $possibleVatTypes;
  }

  /**
   * Initializes the list of possible vat rates.
   *
   * The possible vat rats depend on:
   * - the possible vat types.
   * - optionally, the date of the invoice.
   * - optionally, the country of the client.
   *
   * @return array
   *   Array with possible vat rates. a vat rate being an array with keys
   *   vatrate and vattype. This to be able to retrieve to which vat type a vat
   *   rate belongs and to allow for the same vat rate to be valid for multiple
   *   vat types.
   */
  protected function initPossibleVatRates() {
    $possibleVatRates = array();
    foreach ($this->possibleVatTypes as $vatType) {
      switch ($vatType) {
        case ConfigInterface::VatType_National:
        case ConfigInterface::VatType_MarginScheme:
        default:
          $vatTypeVatRates = $this->getVatRates('nl');
          break;
        case ConfigInterface::VatType_NationalReversed:
        case ConfigInterface::VatType_EuReversed:
          $vatTypeVatRates = array(0);
          break;
        case ConfigInterface::VatType_RestOfWorld:
          $vatTypeVatRates = array(-1);
          break;
        case ConfigInterface::VatType_ForeignVat:
          $vatTypeVatRates = $this->getVatRates();
          break;
      }
      $vatTypeVatRates = array_map(function($vatRate) use ($vatType) {
        return array('vatrate' => $vatRate, 'vattype' => $vatType);
      }, $vatTypeVatRates);
      $possibleVatRates = array_merge($possibleVatRates, $vatTypeVatRates);
    }
    $this->possibleVatRates = $possibleVatRates;
  }

  /**
   * Tries to reduce the number of possible vat types, and thereby also the
   * number of possible vat rates, by comparing the 'exact' vat rates in the
   * invoice lines to the possible vat rates per vat type. If that results in 1
   * vat type, that will be the vat type for the invoice.
   *
   * It can still result in multiple vat types if these vat types share equal
   * vat rates. Therefore, if we find a vat rate that only exists for 1 vat type
   * we should arrive at that vat type.
   *
   * Example: NL: 6 and 21; FR 6 and 20: we find 6 and 20 => vat type = foreign.
   *
   * @param string|string[] $vatRateSource
   */
  protected function reduceVatTypesByVatRatesBySource($vatRateSource) {
    if (count($this->possibleVatTypes) > 1) {
      if (is_string($vatRateSource)) {
        $vatRateSource = array($vatRateSource);
      }

      // We keep track of vat types found per appearing vat rate.
      // The intersection of these sets should result in the new, hopefully
      // smaller list, of possible vat types.
      $foundVatTypes = array();
      foreach ($this->invoiceLines as &$line) {
        if (!empty($line['meta-vatrate-source']) && in_array($line['meta-vatrate-source'], $vatRateSource)
          // We ignore "0" vat rates (0 and -1).
          && isset($line['vatrate']) && $line['vatrate'] > 0) {
          // Check if we already processed this vat rate for another line.
          if (!isset($foundVatTypes[$line['vatrate']])) {
            $foundVatTypes[$line['vatrate']] = array();
            foreach ($this->possibleVatRates as $vatRateInfo) {
              if ($vatRateInfo['vatrate'] == $line['vatrate']) {
                $foundVatTypes[$line['vatrate']][$vatRateInfo['vattype']] = $vatRateInfo['vattype'];
              }
            }
          }
        }
      }

      // Now get the intersection of non-empty sub arrays (an empty sub array
      // denotes an invalid vat rate and should not prevent this method from
      // doing its work).
      // Remove empty sub arrays.
      array_filter($foundVatTypes);
      if (count($foundVatTypes) >= 1) {
        // Compute the intersection.
        $remainingVatTypes = reset($foundVatTypes);
        while ($foundVatType = (next($foundVatTypes)) !== false) {
          /** @var array $foundVatType */
          $remainingVatTypes = array_intersect($remainingVatTypes, $foundVatType);
        }

        if (count($remainingVatTypes) > 0 && count($remainingVatTypes) < count($this->possibleVatTypes)) {
          // We can reduce the number of possible vat types and thus also the
          // number of possible vat rates.
          $this->possibleVatTypes = array_values($remainingVatTypes);
          $this->possibleVatRates = array_filter($this->possibleVatRates, function ($vatRateInfo) {
            return in_array($vatRateInfo['vattype'], $this->possibleVatTypes);
          });
        }
      }
    }
  }

  protected function completeVatType() {
    if (empty($this->invoice['customer']['invoice']['vattype'])) {
      // Pick the first and hopefully only vat type.
      $this->invoice['customer']['invoice']['vattype'] = reset($this->possibleVatTypes);
      // But add meta info when there are still multiple possibilities.
      if (count($this->possibleVatTypes) > 1) {
        $this->invoice['customer']['invoice']['meta-vattypes-possible'] = implode(',', $this->possibleVatTypes);
      }
    }
  }

  /**
   * Trie to correct 'calculated' vat rates for rounding errors by matching them
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
   * @param array $line
   *   A line with a calculated vat rate.
   * @return array
   *   The line with a corrected vat rate.
   */
  protected function correctVatRateByRange(array $line) {
    $matchedVatRates = array();
    foreach ($this->possibleVatRates as $vatRate) {
      if ($vatRate['vatrate'] >= $line['meta-vatrate-min'] && $vatRate['vatrate'] <= $line['meta-vatrate-max']) {
        $matchedVatRates[] = $vatRate;
      }
    }

    if (count($matchedVatRates) === 1) {
      $line['vatrate'] = $matchedVatRates[0]['vatrate'];
      $line['meta-vatrate-source'] = static::VatRateSource_Calculated_Corrected;
    }
    else {
      $line['meta-vatrate-matches'] = count($matchedVatRates) === 0 ? 'none' : array_reduce($matchedVatRates, function($carry, $item) {
          return $carry . ($carry === '' ? '' : ',') . $item['vatrate'] . '(' . $item['vattype'] . ')';
        }, '');
    }
    return $line;
  }

  /**
   * Completes lines with free items (price = 0) by giving them the maximum tax
   * rate that appears in the other lines.
   */
  protected function addVatRateTo0PriceLines() {
    // Get appearing vat rates and their frequency.
    $vatRates = $this->getAppearingVatRates();

    // Get the highest vat rate.
    $maxVatRate = -1;
    foreach ($vatRates as $vatRate => $frequency) {
      if ((float) $vatRate > $maxVatRate) {
        $maxVatRate = (float) $vatRate;
      }
    }

    foreach ($this->invoiceLines as &$line) {
      if ($line['meta-vatrate-source'] === Creator::VatRateSource_Completor && $line['vatrate'] === null && $this->floatsAreEqual($line['vatamount'], 0.0)) {
        $line['vatrate'] = $maxVatRate;
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
        $strategy = new $strategyClass($this->invoice, $this->possibleVatTypes, $this->possibleVatRates);
        if ($strategy->apply()) {
          $this->replaceLines2Complete($strategy->getCompletedLines());
          $this->invoice['customer']['invoice']['meta-completor-strategy-used'] = $strategy->getDescription();
          break;
        }
      }
    }
  }

  /**
   * Returns whether the invoice has lines that are tobe completed using a tax
   * divide strategy.
   *
   * @return bool
   */
  protected function invoiceHasStrategyLine() {
    $result = false;
    foreach ($this->invoiceLines as $line) {
      if ($line['meta-vatrate-source'] === Creator::VatRateSource_Strategy) {
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
    $result[] = "$namespace\\SplitKnownDiscountLine";
    $result[] = "$namespace\\ApplySameVatRate";
    $result[] = "$namespace\\SplitLine";
    $result[] = "$namespace\\TryAllVatRatePermutations";
    $result[] = "$namespace\\Fail";

    return $result;
  }

  /**
   * Replaces all strategy lines with the given completed lines.
   *
   * @param array[] $completedLines
   *   An array of completed invoice lines to replace the strategy lines with.
   */
  protected function replaceLines2Complete(array $completedLines) {
    // Remove all old strategy lines.
    $this->invoice['customer']['invoice']['line'] = array_filter($this->invoice['customer']['invoice']['line'], function($line) {
      return $line['meta-vatrate-source'] !== Creator::VatRateSource_Strategy;
    });

    // And merge in the new completed ones.
    $this->invoice['customer']['invoice']['line'] = array_merge($this->invoice['customer']['invoice']['line'],
      $completedLines);
  }

  /**
   * Returns whether the invoice has at least 1 line with a non-0 vat rate.
   *
   * 0 (VAt free/reversed VAT) and -1 (no VAT) are valid 0-vat rates.
   * As vatrate may be null, the vatamount value is also checked.
   *
   * @return bool
   */
  protected function invoiceHasLineWithVat() {
    $isLineWithVat = false;
    foreach ($this->invoiceLines as $line) {
      if (!empty($line['vatrate']) && !$this->floatsAreEqual($line['vatrate'], 0.0) && !$this->floatsAreEqual($line['vatrate'], -1.0)) {
        $isLineWithVat = true;
        break;
      }
      if (!empty($line['vatamount']) && !$this->floatsAreEqual($line['vatamount'], 0.0)) {
        $isLineWithVat = true;
        break;
      }
    }
    return $isLineWithVat;
  }

  /**
   * Helper method to get vat info for the current invoice rom the Acumulus API.
   *
   * The vat rates at the invoice date are retrieved.
   *
   * @param string $countryCode
   *   The country to fetch the vat rates for. If empty, the vat rates for the
   *   clients country are taken.
   *
   * @return array
   *
   * @see \Siel\Acumulus\Web\Service::getVatInfo().
   */
  protected function getVatRates($countryCode = '') {
    $result = array();
    if (!empty($countryCode) || !empty($this->invoice['customer']['countrycode'])) {
      if (empty($countryCode)) {
        $countryCode = $this->invoice['customer']['countrycode'];
      }
      $date = $this->getInvoiceDate();
      $vatInfo = $this->acumulusWebService->getVatInfo($countryCode, $date);
      // PHP5.5: array_column($vatInfo['vatinfo'], 'vatrate');
      $result = array_map(function ($vatInfo1) {
        return $vatInfo1['vatrate'];
      }, $vatInfo['vatinfo']);
    }
    return $result;
  }

  /**
   * Returns the invoice date in the iso yyyy-mm-dd format.
   *
   * @return string
   */
  protected function getInvoiceDate() {
    $date = !empty($this->invoice['customer']['invoice']['issuedate']) ? $this->invoice['customer']['invoice']['issuedate'] : date('Y-m-d');
    return $date;
  }

  /**
   * Returns a reference string that can be used to identify the invoice in
   * human readable messages.
   *
   * @return int|string
   *
   */
  protected function getInvoiceReference() {
    return !empty($this->invoice['customer']['invoice']['number']) ? $this->invoice['customer']['invoice']['number'] : $this->source->getReference();
  }

  /**
   * Wrapper around Countries::isNl().
   *
   * @return bool
   */
  protected function isNl() {
    return $this->countries->isNl($this->invoice['customer']['countrycode']);
  }

  /**
   * Wrapper around Countries::isEu().
   *
   * @return bool
   */
  protected function isEu() {
    return $this->countries->isEu($this->invoice['customer']['countrycode']);
  }

  /**
   * Returns whether the client is located outside the EU.
   *
   * @return bool
   */
  protected function isOutsideEu() {
    return !$this->isNl() && !$this->isEu();
  }

  /**
   * Returns whether the client is a company with a vat number.
   *
   * @return bool
   */
  protected function isCompany() {
    return !empty($this->invoice['customer']['vatnumber']);
  }

  /**
   * @param float $f1
   * @param float $f2
   * @param float $maxDiff
   *
   * @return bool
   */
  protected function floatsAreEqual($f1, $f2, $maxDiff = 0.005) {
    return abs((float) $f2 - (float) $f1) <= $maxDiff;
  }

}
