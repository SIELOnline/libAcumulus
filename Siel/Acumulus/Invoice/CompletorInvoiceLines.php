<?php
namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\Countries;
use Siel\Acumulus\Helpers\Number;
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
 *   - completor: zero price lines to be filled in by the completor with the
 *     most used VAT rate. these are like free shipping or discounts that are
 *     only there for info (discounts already processed in the product prices).
 *   - strategy: to be filled in by a tax divide strategy. This may lead to
 *     the line being split into multiple lines.
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
  const VatRateSource_Completor_Completed = 'completor-completed';
  const VatRateSource_Strategy_Completed = 'strategy-completed';

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

  /**
   * @var int[]
   *   The list of possible vat types, initially filled with possible vat types
   *   based on client country, invoiceHasLineWithVat(), is_company(), and the
   *   digital services setting. Bu then reduced by VAT rates we fnd on the
   *   order lines.
   */
  protected $possibleVatTypes;

  /**
   * @var int[]
   *   The original list of possible vat types, see above, but not reduced.
   *   Is used for logging when no vat type remains possible after checking all
   *   order lines.
   */
  protected $originalPossibleVatTypes;

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
    $this->correctCalculatedVatRates();
    $this->addVatRateTo0PriceLines();
    $this->completeStrategyLines();
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
    $invoiceSettings = $this->config->getInvoiceSettings();
    $digitalServices = $invoiceSettings['digitalServices'];

    if (!empty($this->invoice['customer']['invoice']['vattype'])) {
      // If shop specific code or an event handler has already set the vat type,
      // we obey so.
      $possibleVatTypes[] = $this->invoice['customer']['invoice']['vattype'];
    }
    else {
      if (!$this->invoiceHasLineWithVat()) {
        // National/EU reversed vat or no vat (rest of world).
        if ($this->isNl()) {
          // National: some products (e.g. education) are free of VAT, but
          // digital services are not part of these.
          if ($digitalServices !== ConfigInterface::DigitalServices_Only) {
            $possibleVatTypes[] = ConfigInterface::VatType_National;
          }
          if ($this->isCompany()) {
            $possibleVatTypes[] = ConfigInterface::VatType_NationalReversed;
          }
        }
        else if ($this->isEu()) {
          if ($this->isCompany()) {
            $possibleVatTypes[] = ConfigInterface::VatType_EuReversed;
          }
          if ($digitalServices !== ConfigInterface::DigitalServices_Only) {
            $possibleVatTypes[] = ConfigInterface::VatType_National;
          }
        }
        else if ($this->isOutsideEu()) {
          $possibleVatTypes[] = ConfigInterface::VatType_RestOfWorld;
        }

        if (empty($possibleVatTypes)) {
          // Warning + fall back.
          $this->messages['warnings'][] = array(
            'code' => '',
            'codetag' => '',
            'message' => $this->t('message_warning_no_vat'),
          );
          $possibleVatTypes[] = ConfigInterface::VatType_National;
          $possibleVatTypes[] = ConfigInterface::VatType_EuReversed;
          $possibleVatTypes[] = ConfigInterface::VatType_NationalReversed;
          $this->invoice['customer']['invoice']['concept'] = ConfigInterface::Concept_Yes;
        }
      }
      else {
        // NL or EU Foreign vat.
        if ($digitalServices === ConfigInterface::DigitalServices_No) {
          // No electronic services are sold: can only be dutch VAT.
          $possibleVatTypes[] = ConfigInterface::VatType_National;
        }
        else {
          if ($this->isEu() && $this->getInvoiceDate() >= '2015-01-01') {
            if ($digitalServices !== ConfigInterface::DigitalServices_Only) {
              // Also normal goods are sold, so dutch VAT still possible.
              $possibleVatTypes[] = ConfigInterface::VatType_National;
            }
            // As of 2015, electronic services should be taxed with the rates of
            // the clients' country. And they might be sold in the shop.
            $possibleVatTypes[] = ConfigInterface::VatType_ForeignVat;
          }
          else {
            // Not EU or before 2015-01-01: special regulations for electronic
            // services were not yet active: dutch VAT only.
            $possibleVatTypes[] = ConfigInterface::VatType_National;
          }
        }
      }
    }
    $this->possibleVatTypes = $possibleVatTypes;
    $this->originalPossibleVatTypes = $possibleVatTypes;
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
          $vatTypeVatRates = $this->getVatRates($this->invoice['customer']['countrycode']);
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
   * Checks whether a vat rate info array belongs to one of the still possible
   * vat types.
   *
   * As of PHP 5.4 this can be made an anonymous function (using: use ($this)).
   *
   * @param array $vatRateInfo
   *
   * @return bool
   */
  protected function filterVatRatesByPossibleVatTypes(array $vatRateInfo) {
    return in_array($vatRateInfo['vattype'], $this->possibleVatTypes);
  }

  protected function completeVatType() {
    // If shop specific code or an event handler has already set the vat type,
    // we don't change it.
    if (empty($this->invoice['customer']['invoice']['vattype'])) {

      $possibleVatTypes = $this->getPossibleVatTypesByCorrectVatRates();
      if (empty($possibleVatTypes)) {
        $this->messages['warnings'][] = array(
          'code' => '',
          'codetag' => '',
          'message' => $this->t('message_warning_no_vattype'),
        );
        $this->invoice['customer']['invoice']['vattype'] = reset($this->originalPossibleVatTypes);
        $this->invoice['customer']['invoice']['meta-vattypes-possible'] = implode(',', $this->originalPossibleVatTypes);
        $this->invoice['customer']['invoice']['concept'] = ConfigInterface::Concept_Yes;
      }
      else if (count($possibleVatTypes) === 1) {
        // Pick the first and only vat type.
        $this->invoice['customer']['invoice']['vattype'] = reset($possibleVatTypes);
      }
      else {
        // Get the intersection of possible vat types per line.
        $vatTypesOnAllLines = $this->getVatTypesAppearingOnAllLines();
        if (empty($vatTypesOnAllLines)) {
          // We must split.
          $message = 'message_warning_multiple_vattype_must_split';
          // Pick the first and hopefully correct vat type.
          $this->invoice['customer']['invoice']['vattype'] = reset($possibleVatTypes);
        }
        else {
          // We may have to split.
          $message = 'message_warning_multiple_vattype_may_split';
          // Pick the first vat type that appears on all lines.
          $this->invoice['customer']['invoice']['vattype'] = reset($vatTypesOnAllLines);
        }

        // But add meta info.
        $this->messages['warnings'][] = array(
          'code' => '',
          'codetag' => '',
          'message' => $this->t($message),
        );
        $this->invoice['customer']['invoice']['concept'] = ConfigInterface::Concept_Yes;
        $this->invoice['customer']['invoice']['meta-vattypes-possible'] = implode(',', $possibleVatTypes);
      }
    }
  }

  /**
   * Returns a list of possible vat types based on possible vat types for all
   * lines with a "correct" vat rate.
   *
   * If that results in 1 vat type, that will be the vat type for the invoice,
   * otherwise a warning will be issued.
   *
   * This method may return multiple vat types because:
   * - If vat types share equal vat rates we cannot make a choice (e.g. NL and
   *   BE high VAT rates are equal).
   * - If the invoice ought to be split into multiple invoices because multiple
   *   vat regimes apply (digital services and normal goods) (e.g. both the FR
   *   20% high rate and the NL 21% high rate appear on the invoice).
   *
   * @return int[]
   *   List of possible vat type for this invoice (keyed by the vat types).
   */
  protected function getPossibleVatTypesByCorrectVatRates() {
    // We only want to process correct vat rates.
    $correctVatRateSources = array(
      Creator::VatRateSource_Exact,
      Creator::VatRateSource_Exact0,
      static::VatRateSource_Calculated_Corrected,
      static::VatRateSource_Completor_Completed,
      static::VatRateSource_Strategy_Completed
    );
    // Define vat types that do know a zero rate.
    $zeroRateVatTypes = array(
      ConfigInterface::VatType_National,
      ConfigInterface::VatType_NationalReversed,
      ConfigInterface::VatType_EuReversed,
      ConfigInterface::VatType_RestOfWorld
    );

    // We keep track of vat types found per appearing vat rate.
    // The intersection of these sets should result in the new, hopefully
    // smaller list, of possible vat types.
    $invoiceVatTypes = array();
    foreach ($this->invoiceLines as &$line) {
      if (!empty($line['meta-vatrate-source']) && in_array($line['meta-vatrate-source'], $correctVatRateSources)) {
        // We ignore "0" vat rates (0 and -1).
        if ($line['vatrate'] > 0) {
          $lineVatTypes = array();
          foreach ($this->possibleVatRates as $vatRateInfo) {
            if ($vatRateInfo['vatrate'] == $line['vatrate']) {
              // Ensure that the values remain unique by keying them.
              $invoiceVatTypes[$vatRateInfo['vattype']] = $vatRateInfo['vattype'];
              $lineVatTypes[$vatRateInfo['vattype']] = $vatRateInfo['vattype'];
            }
          }
        }
        else {
          // Reduce the possible vat types to those that do know a zero rate.
          $lineVatTypes = array_intersect($this->possibleVatTypes, $zeroRateVatTypes);
          foreach ($lineVatTypes AS $lineVatType) {
            $invoiceVatTypes[$lineVatType] = $lineVatType;
          }
        }
        $line['meta-vattypes-possible'] = implode(',', $lineVatTypes);
      }
    }

    return $invoiceVatTypes;
  }


  /**
   * Returns a list of vat types that are possible for all lines of the invoice.
   *
   * @return int[]
   */
  protected function getVatTypesAppearingOnAllLines() {
    $result = NULL;
    foreach ($this->invoiceLines as $line) {
      $lineVatTypes = explode(',', $line['meta-vattypes-possible']);
      if ($result === NULL) {
        // 1st line.
        $result = $lineVatTypes;
      }
      else {
        $result = array_intersect($result, $lineVatTypes);
      }
    }
    return $result;
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

    $vatRate = $this->getUniqueVatRate($matchedVatRates);
    if ($vatRate === NULL || $vatRate === FALSE) {
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
      $line['meta-vatrate-source'] = static::VatRateSource_Calculated_Corrected;
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
        $line['meta-vatrate-source'] = static::VatRateSource_Completor_Completed;
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
        $strategy = new $strategyClass($this->translator, $this->invoice, $this->possibleVatTypes, $this->possibleVatRates);
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
        $completedLine['meta-vatrate-source'] = static::VatRateSource_Strategy_Completed;
      }
    }
    $this->invoice['customer']['invoice']['line'] = array_merge($lines, $completedLines);
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
      if (!empty($line['vatrate']) && !Number::isZero($line['vatrate']) && !Number::floatsAreEqual($line['vatrate'], -1.0)) {
        $isLineWithVat = true;
        break;
      }
      if (!empty($line['vatamount']) && !Number::isZero($line['vatamount'])) {
        $isLineWithVat = true;
        break;
      }
    }
    return $isLineWithVat;
  }

  /**
   * Helper method to get vat info for the current invoice from the Acumulus API.
   *
   * The vat rates as they were at the invoice date are retrieved.
   *
   * @param string $countryCode
   *   The country to fetch the vat rates for.
   *
   * @return float[]
   *   Actual type will be string[].
   *
   * @see \Siel\Acumulus\Web\Service::getVatInfo().
   */
  protected function getVatRates($countryCode) {
    $date = $this->getInvoiceDate();
    $vatInfo = $this->acumulusWebService->getVatInfo($countryCode, $date);
    // PHP5.5: array_column($vatInfo['vatinfo'], 'vatrate');
    $result = array_unique(array_map(function ($vatInfo1) {
      return $vatInfo1['vatrate'];
    }, $vatInfo['vatinfo']));
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

}
