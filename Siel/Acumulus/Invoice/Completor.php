<?php
namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\Countries;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Helpers\TranslatorInterface;
use Siel\Acumulus\Web\Service;

/**
 * The invoice completor class provides functionality to correct and complete
 * invoices before sending them to Acumulus.
 *
 * This class:
 * - Changes the customer into a fictitious client if set so in the config.
 * - Validates the email address: the webservice does not allow an empty email
 *   address (but does allow a non provided email address).
 * - Adds line totals. These are compared with what the shop advertises as order
 *   totals to see if an amount might be missing.
 * - Adds the vat type based on inspection of the completed invoice.
 * - Removes an empty shipping line if set to do so in the config.
 *
 * - Calls the invoice line completor.
 * - Calls the strategy line completor.
 *
 * @package Siel\Acumulus
 */
class Completor {

  const VatRateSource_Calculated_Corrected = 'calculated-corrected';
  const VatRateSource_Completor_Completed = 'completor-completed';
  const VatRateSource_Strategy_Completed = 'strategy-completed';

  static $CorrectVatRateSources = array(
      Creator::VatRateSource_Exact,
      Creator::VatRateSource_Exact0,
      self::VatRateSource_Calculated_Corrected,
      self::VatRateSource_Completor_Completed,
    );

  /** @var \Siel\Acumulus\Invoice\ConfigInterface */
  protected $config;

  /** @var \Siel\Acumulus\Helpers\TranslatorInterface */
  protected $translator;

  /** @var \Siel\Acumulus\Web\Service */
  protected $service;

  /** @var \Siel\Acumulus\Helpers\Countries */
  protected $countries;

  /** @var array */
  protected $messages;

  /** @var array */
  protected $invoice;

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

  /** @var \Siel\Acumulus\Invoice\CompletorInvoiceLines */
  protected $invoiceLineCompletor = null;

  /** @var \Siel\Acumulus\Invoice\CompletorStrategyLines */
  protected $strategyLineCompletor = null;

  /** @var array */
  protected $incompleteValues;

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

    $this->service = $service;
    $this->countries = new Countries();

    $this->invoiceLineCompletor = new CompletorInvoiceLines();
    $this->strategyLineCompletor = new CompletorStrategyLines($translator);
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
    $this->source = $source;
    $this->messages = &$messages;

    $this->initPossibleVatTypes();
    $this->initPossibleVatRates();

    // Completes the invoice with default settings that do not depend on shop
    // specific data.
    $this->fictitiousClient();
    $this->validateEmail();

    // Complete lines as far as they can be completed om their own.
    $this->invoice = $this->invoiceLineCompletor->complete($this->invoice, $this->possibleVatTypes, $this->possibleVatRates);

    // Check if we are missing an amount and, if so, add a line for it.
    $this->completeLineTotals();
    $areTotalsEqual = $this->areTotalsEqual();
    if (!$areTotalsEqual) {
      $this->addMissingAmountLine($areTotalsEqual);
    }

    // Complete strategy lines: those lines that have to be completed based on
    // the whole invoice.
    $this->invoice = $this->strategyLineCompletor->complete($this->invoice, $this->source, $this->possibleVatTypes, $this->possibleVatRates);

    // Fill in the VAT type, adding a warning if possible vat types are possible.
    $this->completeVatType();

    // Completes the invoice with settings or behaviour that might depend on the
    // fact that the invoice lines have been completed.
    $this->removeEmptyShipping();

    return $this->invoice;
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
    $shopSettings = $this->config->getShopSettings();
    $digitalServices = $shopSettings['digitalServices'];
    $vatFreeProducts = $shopSettings['vatFreeProducts'];

    if (!empty($this->invoice['customer']['invoice']['vattype'])) {
      // If shop specific code or an event handler has already set the vat type,
      // we obey so.
      $possibleVatTypes[] = $this->invoice['customer']['invoice']['vattype'];
    }
    else {
      if (!$this->invoiceHasLineWithVat()) {
        // No VAT at all: National/EU reversed vat, only vat free products, or
        // no vat (rest of world).
        if ($this->isNl()) {
          // Can it be VAT free products (e.g. education)? Digital services are
          // never VAT free, nor are there any if set so.
          if ($digitalServices !== ConfigInterface::DigitalServices_Only && $vatFreeProducts !== ConfigInterface::VatFreeProducts_No) {
            $possibleVatTypes[] = ConfigInterface::VatType_National;
          }
          // National reversed VAT: not really supported, but it is possible.
          if ($this->isCompany()) {
            $possibleVatTypes[] = ConfigInterface::VatType_NationalReversed;
          }
        }
        else if ($this->isEu()) {
          // U reversed VAT.
          if ($this->isCompany()) {
            $possibleVatTypes[] = ConfigInterface::VatType_EuReversed;
          }
          // Can it be VAT free products (e.g. education)? Digital services are
          // never VAT free, nor are there any if set so.
          if ($digitalServices !== ConfigInterface::DigitalServices_Only && $vatFreeProducts !== ConfigInterface::VatFreeProducts_No) {
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
   * Anonymize customer if set so. We don't do this for business clients, only
   * consumers.
   */
  protected function fictitiousClient() {
    $customerSettings = $this->config->getCustomerSettings();
    if (!$customerSettings['sendCustomer'] && empty($this->invoice['customer']['companyname1']) && empty($this->invoice['customer']['vatnumber'])) {
      unset($this->invoice['customer']['type']);
      unset($this->invoice['customer']['companyname1']);
      unset($this->invoice['customer']['companyname2']);
      unset($this->invoice['customer']['fullname']);
      unset($this->invoice['customer']['salutation']);
      unset($this->invoice['customer']['address1']);
      unset($this->invoice['customer']['address2']);
      unset($this->invoice['customer']['postalcode']);
      unset($this->invoice['customer']['city']);
      unset($this->invoice['customer']['countrycode']);
      unset($this->invoice['customer']['vatnumber']);
      unset($this->invoice['customer']['telephone']);
      unset($this->invoice['customer']['fax']);
      unset($this->invoice['customer']['bankaccountnumber']);
      unset($this->invoice['customer']['mark']);
      $this->invoice['customer']['email'] = $customerSettings['genericCustomerEmail'];
      $this->invoice['customer']['overwriteifexists'] = 0;
    }
  }

  /**
   * Validates the email address of the invoice.
   *
   * - The email address may not be empty but may be left out though.
   * - Multiple, comma separated, email addresses are not allowed.
   * - Display names (My Name <my.name@example.com>) are not allowed.
   */
  protected function validateEmail() {
    // Check email address.
    if (empty($this->invoice['customer']['email'])) {
      unset($this->invoice['customer']['email']);
    }
    else {
      $email = $this->invoice['customer']['email'];
      $at = strpos($email, '@');
      // Comma (,) used as separator?
      $comma = strpos($email, ',', $at);
      if ($at < $comma) {
        $email = trim(substr($email, 0, $comma));
      }
      // Semicolon (;) used as separator?
      $semicolon = strpos($email, ';', $at);
      if ($at < $semicolon) {
        $email = trim(substr($email, 0, $semicolon));
      }

      // Display name used in single remaining address?
      if (preg_match('/^(.+?)<([^>]+)>$/', $email, $matches)) {
        $email = trim($matches[2]);
      }
      $this->invoice['customer']['email'] = $email;
    }
  }

  /**
   * Calculates the total amount and vat amount for the invoice lines and adds
   * these to the fields meta-lines-amount and meta-lines-vatamount.
   */
  protected function completeLineTotals() {
    $linesAmount = 0.0;
    $linesAmountInc = 0.0;
    $linesVatAmount = 0.0;
    $this->incompleteValues = array();

    $invoiceLines = $this->invoice['customer']['invoice']['line'];
    foreach($invoiceLines as $line) {
      if (isset($line['meta-line-price'])) {
        $linesAmount += $line['meta-line-price'];
      }
      else if (isset($line['unitprice'])) {
        $linesAmount += $line['quantity'] * $line['unitprice'];
      }
      else {
        $this->incompleteValues['meta-lines-amount'] = 'meta-lines-amount';
      }

      if (isset($line['meta-line-priceinc'])) {
        $linesAmountInc += $line['meta-line-priceinc'];
      }
      else if (isset($line['unitpriceinc'])) {
        $linesAmountInc += $line['quantity'] * $line['unitpriceinc'];
      }
      else {
        $this->incompleteValues['meta-lines-amountinc'] = 'meta-lines-amountinc';
      }

      if (isset($line['meta-line-vatamount'])) {
        $linesVatAmount += $line['meta-line-vatamount'];
      }
      else if (isset($line['vatamount'])) {
        $linesVatAmount += $line['quantity'] * $line['vatamount'];
      }
      else {
        $this->incompleteValues['meta-lines-vatamount'] = 'meta-lines-vatamount';
      }
    }

    $this->invoice['customer']['invoice']['meta-lines-amount'] = $linesAmount;
    $this->invoice['customer']['invoice']['meta-lines-amountinc'] = $linesAmountInc;
    $this->invoice['customer']['invoice']['meta-lines-vatamount'] = $linesVatAmount;
    if (!empty($this->incompleteValues)) {
      sort($this->incompleteValues);
      $this->invoice['customer']['invoice']['meta-lines-incomplete'] = implode(',', $this->incompleteValues);
    }
  }

  /**
   * Compares the invoice totals metadata with the line totals metadata.
   *
   * If any of the 3 values are equal we do consider the totals to be equal
   * (except for a 0 VAT amount (for reversed VAT invoices)). This because in
   * many cases 1 or 2 of the 3 values are either incomplete or incorrect.
   *
   * @todo: if 1 is correct but other not, that would be an indication of an error: warn?
   * @return bool|null
   *   True if the totals are equal, false if not equal, null if undecided (all
   *   3 values are incomplete).
   */
  protected function areTotalsEqual() {
    $invoice = $this->invoice['customer']['invoice'];
    if (!in_array('meta-lines-amount', $this->incompleteValues) && Number::floatsAreEqual($invoice['meta-invoice-amount'], $invoice['meta-lines-amount'], 0.05)) {
      return TRUE;
    }
    if (!in_array('meta-lines-amountinc', $this->incompleteValues) && Number::floatsAreEqual($invoice['meta-invoice-amountinc'], $invoice['meta-lines-amountinc'], 0.05)) {
      return TRUE;
    }
    if (!in_array('meta-lines-vatamount', $this->incompleteValues)
      && Number::floatsAreEqual($invoice['meta-invoice-vatamount'], $invoice['meta-lines-vatamount'], 0.05)
      && !Number::isZero($invoice['meta-invoice-vatamount'])) {
      return TRUE;
    }
    return count($this->incompleteValues) === 3 ? NULL : FALSE;
  }

  /**
   * Adds an invoice line if the total amount (meta-invoice-amount) is not
   * matching the total amount of the lines.
   *
   * This can happen if:
   * - we missed a fee that is stored in custom fields
   * - a manual adjustment
   * - an error in the logic or data as provided by the webshop.
   *
   * However, we can only add this line if we have at least 2 complete values,
   * that is, there are no strategy lines,
   *
   * @param bool|null $areTotalsEqualResult
   *   Result of areTotalsEqual() (false or null)
   */
  protected function addMissingAmountLine($areTotalsEqualResult) {
    $invoice = &$this->invoice['customer']['invoice'];
    if (!in_array('meta-lines-amount', $this->incompleteValues)) {
      $missingAmount = $invoice['meta-invoice-amount'] - $invoice['meta-lines-amount'];
    }
    if (!in_array('meta-lines-amountinc', $this->incompleteValues)) {
      $missingAmountInc = $invoice['meta-invoice-amountinc'] - $invoice['meta-lines-amountinc'];
    }
    if (!in_array('meta-lines-vatamount', $this->incompleteValues)) {
      $missingVatAmount = $invoice['meta-invoice-vatamount'] - $invoice['meta-lines-vatamount'];
    }

    if (count($this->incompleteValues) <= 1) {
      if (!isset($missingAmount)) {
        /** @noinspection PhpUndefinedVariableInspection */
        $missingAmount = $missingAmountInc - $missingVatAmount;
      }
      if (!isset($missingVatAmount)) {
        /** @noinspection PhpUndefinedVariableInspection */
        $missingVatAmount = $missingAmountInc - $missingAmount;
      }

      $settings = $this->config->getInvoiceSettings();
      if ($settings['addMissingAmountLine']) {
        if ($this->source->getType() === Source::CreditNote) {
          $product = $this->t('refund_adjustment');
        }
        else if ($missingAmount < 0.0) {
          $product = $this->t('discount_adjustment');
        }
        else {
          $product = $this->t('fee_adjustment');
        }
        $countLines = count($invoice['line']);
        $line = array(
            'product' => $product,
            'quantity' => 1,
            'unitprice' => $missingAmount,
            'vatamount' => $missingVatAmount,
          ) + Creator::getVatRangeTags($missingVatAmount, $missingAmount, $countLines * 0.02, $countLines * 0.02)
          + array(
            'meta-line-type' => Creator::LineType_Corrector,
          );
        // Correct and add this line.
        if ($line['meta-vatrate-source'] === Creator::VatRateSource_Calculated) {
          $line = $this->invoiceLineCompletor->correctVatRateByRange($line);
        }
        $invoice['line'][] = $line;
      }
      else {
        // Add some diagnostic info to the message sent.
        // @todo: this could/should be turned into a warning (after some testing).
        /** @noinspection PhpUndefinedVariableInspection */
        $invoice['meta-missing-amount'] = "Ex: $missingAmount, Inc: $missingAmountInc, VAT: $missingVatAmount";
      }
    }
    else {
      if ($areTotalsEqualResult === FALSE) {
        // Due to lack of information, we cannot add a missing line, even though
        // we know we are missing something ($areTotalsEqualResult is false, not
        // null). Add some diagnostic info to the message sent.
        // @todo: this could/should be turned into a warning (after some testing).
        $invoice['meta-missing-amount'] = array();
        if (isset($missingAmount)) {
          $invoice['meta-missing-amount'][] = "Ex: $missingAmount";
        }
        if (isset($missingAmountInc)) {
          $invoice['meta-missing-amount'][] = "Inc: $missingAmountInc";
        }
        if (isset($missingVatAmount)) {
          $invoice['meta-missing-amount'][] = "VAT: $missingVatAmount";
        }
        $invoice['meta-missing-amount'] = implode(', ', $invoice['meta-missing-amount']);
      }
    }
  }

  /**
   * Fills the vattype field of the invoice.
   *
   * This method (and class) is aware of:
   * - The setting digitalServices.
   * - The country of the client.
   * - Whether the client is a company.
   * - The actual VAT rates on the day of the order.
   *
   * So to start with, any list of (possible) vat types is based on the above.
   * Furthermore this method is aware of:
   * - The fact that orders do not have to be split over different vat types,
   *   but that invoices should be split if both national and foreign VAT rates
   *   appear on the order.
   * - The fact that the vat type may be indeterminable if EU countries have VAT
   *   rates in common with NL and the settings indicate that this shop sells
   *   products in both vat type categories.
   *
   * If multiple vat types are possible, the invoice is sent as concept, so that
   * it may be edited in Acumulus.
   */
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
        $this->invoice['customer']['invoice']['vattype'] = reset($this->possibleVatTypes);
        $this->invoice['customer']['invoice']['meta-vattypes-possible'] = implode(',', $this->possibleVatTypes);
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
          // Pick the first and hopefully correct vat type, but ...
          $this->invoice['customer']['invoice']['vattype'] = reset($possibleVatTypes);
        }
        else {
          // We may have to split.
          $message = 'message_warning_multiple_vattype_may_split';
          // Pick the first vat type that appears on all lines, but ...
          $this->invoice['customer']['invoice']['vattype'] = reset($vatTypesOnAllLines);
        }

        // But add a message and meta info.
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
    foreach ($this->invoice['customer']['invoice']['line'] as &$line) {
      if (in_array($line['meta-vatrate-source'], static::$CorrectVatRateSources)) {
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
    foreach ($this->invoice['customer']['invoice']['line'] as $line) {
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
   * Removes an empty shipping line (if so configured).
   */
  protected function removeEmptyShipping() {
    $invoiceSettings = $this->config->getInvoiceSettings();
    if ($invoiceSettings['removeEmptyShipping']) {
      $this->invoice['customer']['invoice']['line'] = array_filter($this->invoice['customer']['invoice']['line'],
        function ($line) {
          return $line['meta-line-type'] !== Creator::LineType_Shipping || !Number::isZero($line['unitprice']);
        });
    }
  }

  /**
   * Returns whether the invoice has at least 1 line with a non-0 vat rate.
   *
   * 0 (VAT free/reversed VAT) and -1 (no VAT) are valid 0-vat rates.
   * As vatrate may be null, the vatamount value is also checked.
   *
   * @return bool
   */
  protected function invoiceHasLineWithVat() {
    $isLineWithVat = false;
    foreach ($this->invoice['customer']['invoice']['line'] as $line) {
      if (!empty($line['vatrate'])) {
        if (!Number::isZero($line['vatrate']) && !Number::floatsAreEqual($line['vatrate'], -1.0)) {
          $isLineWithVat = TRUE;
          break;
        }
      }
      else if (!empty($line['vatamount']) && !Number::isZero($line['vatamount'])) {
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
    $vatInfo = $this->service->getVatInfo($countryCode, $date);
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
