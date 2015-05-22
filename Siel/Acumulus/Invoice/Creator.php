<?php
namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\Countries;
use Siel\Acumulus\Helpers\TranslatorInterface;
use Siel\Acumulus\Shop\Config;
use Siel\Acumulus\Shop\ConfigInterface as ShopConfigInterface;

/**
 * Allows to create arrays in the Acumulus invoice structure from a web shop
 * order or credit note. This array can than be sent to Acumulus using the
 * Invoice:Add Acumulus API call.
 *
 * See https://apidoc.sielsystems.nl/content/invoice-add for the structure. In
 * addition to the scheme as defined over there, additional keys or values are
 * accepted:
 * - all keys starting with meta- are used for debugging purposes.
 * - other keys are used to complete and correct the invoice in the completor
 *   stage.
 * - (PHP) null values indicates the absence of a value that should be there but
 *   could not be (easily) retrieved from the invoice source. These should be
 *   replaced with actual values in the completor stage as well.
 *
 * This base class:
 * - Implements the basic break down into smaller actions that web shops should
 *   subsequently implement.
 * - Provides helper methods for some recurring functionality.
 */
abstract class Creator {

  const VatRateSource_Exact = 'exact';
  const VatRateSource_Exact0 = 'exact-0';
  const VatRateSource_Calculated = 'calculated';
  const VatRateSource_Completor = 'completor';
  const VatRateSource_Strategy = 'strategy';

  /** @var \Siel\Acumulus\Shop\Config */
  protected $config;

  /** @var \Siel\Acumulus\Helpers\TranslatorInterface */
  protected $translator;

  /** @var \Siel\Acumulus\Helpers\Countries */
  protected $countries;

  /** @var array */
  protected $invoice = array();

  /** @var Source */
  protected $source;

  /**
   * Constructor.
   *
   * @param \Siel\Acumulus\Shop\Config $config
   * @param \Siel\Acumulus\Helpers\TranslatorInterface $translator
   */
  public function __construct(Config $config, TranslatorInterface $translator) {
    $this->config = $config;

    $this->translator = $translator;
    require_once(dirname(__FILE__) . '/Translations.php');
    $invoiceHelperTranslations = new Translations();
    $this->translator->add($invoiceHelperTranslations);

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
   * Sets the source to create the invoice for.
   *
   * @param Source $source
   */
  protected function setSource($source) {
    $this->source = $source;
  }

  /**
   * Creates an Acumulus invoice from an order or credit note.
   *
   * @param Source $source
   *  The web shop order.
   *
   * @return array
   *   The acumulus invoice for this order.
   */
  public function create($source) {
    $this->setSource($source);
    $this->invoice = array();
    $this->invoice['customer'] = $this->getCustomer();
    $this->addCustomerDefaults();
    $this->invoice['customer']['invoice'] = $this->getInvoice();
    $this->addInvoiceDefaults();
    $this->invoice['customer']['invoice']['line'] = $this->getInvoiceLines();
    return $this->invoice;
  }

  /**
   * Returns the 'customer' part of the invoice add structure.
   *
   * The following keys are allowed/expected by the API:
   * - type: not needed, will be filled by the Completor
   * - contactid: not expected: Acumulus id for this customer, in the absence of
   *     this value, the API uses
   * - contactyourid: shop customer id
   * - companyname1
   * - companyname2
   * - fullname
   * - salutation
   * - address1
   * - address2
   * - postalcode
   * - city
   * - locationcode: not needed, will be filled by the Completor
   * - countrycode
   * - vatnumber
   * - telephone
   * - fax
   * - email: used to identify clients.
   * - overwriteifexists: not needed, will be filled by the Completor
   * - bankaccountnumber
   * - mark
   *
   * @return array
   *   A keyed array with the customer data.
   */
  abstract protected function getCustomer();

  /**
   * Completes the customer part with default settings that do not depend on
   * shop specific data.
   */
  protected function addCustomerDefaults() {
    $invoiceSettings = $this->config->getInvoiceSettings();
    $this->addDefault($this->invoice['customer'], 'locationcode', $this->getLocationCode($this->invoice['customer']['countrycode']));
    $this->addDefault($this->invoice['customer'], 'overwriteifexists', $invoiceSettings['overwriteIfExists'] ? ConfigInterface::OverwriteIfExists_Yes : ConfigInterface::OverwriteIfExists_No);
    $this->addDefault($this->invoice['customer'], 'type', $invoiceSettings['defaultCustomerType']);
    $this->convertEuCountryCode();
  }

  /**
   * Wrapper around Countries::getLocationCode().
   *
   * @param string $countryCode
   *   ISO 3166-1 alpha-2 country code.
   *
   * @return int
   *   Location code.
   */
  protected function getLocationCode($countryCode) {
    return $this->countries->getLocationCode($countryCode);
  }

  /**
   * Wrapper around Countries::convertEuCountryCod().
   *
   * Converts EU country codes to their ISO equivalent:
   * - Change UK to GB
   * - Change EL to GR.
   *
   * This could/should be server side, but for now it is done client side.
   */
  protected function convertEuCountryCode() {
    if (!empty($this->invoice['customer']['countrycode'])) {
      $this->invoice['customer']['countrycode'] = $this->countries->convertEuCountryCode($this->invoice['customer']['countrycode']);
    }
  }

  /**
   * Returns the 'invoice' part of the invoice add structure.
   *
   * The following keys are allowed/expected by the API:
   * - concept: not needed, will be filled by the Completor
   * - number
   * - vattype: not needed, will be filled by the Completor
   * - issuedate
   * - costcenter: not needed, will be filled by the Completor
   * - accountnumber: not needed, will be filled by the Completor
   * - paymentstatus
   * - paymentdate
   * - description
   * - template: not needed, will be filled by the Completor
   *
   * Additional keys (not recognised by the API but used by the Completor or
   * for support and debugging purposes):
   * - meta-invoiceamount: the total invoice amount excluding VAT.
   * - meta-invoiceamountinc: the total invoice amount including VAT.
   * - meta-invoicevatamount: the total vat amount for the invoice.
   *
   * Extending classes should normally not have to override this method, but
   * should instead implement getInvoiceNumber(), getInvoiceDate(),
   * getPaymentState(), getPaymentDate(), and, optionally, getDescription().
   *
   * @return array
   *   A keyed array with the invoice data (without the invoice lines).
   */
  protected function getInvoice() {
    $result = array();

    $invoiceSettings = $this->config->getShopSettings();

    $invoiceNrSource = $invoiceSettings['invoiceNrSource'];
    if ($invoiceNrSource != ShopConfigInterface::InvoiceNrSource_Acumulus) {
      $result['number'] = $this->getInvoiceNumber($invoiceNrSource);
    }

    $dateToUse = $invoiceSettings['dateToUse'];
    if ($dateToUse != ShopConfigInterface::InvoiceDate_Transfer) {
      $result['issuedate'] = $this->getInvoiceDate($dateToUse);
    }

    // Payment status and date.
    $result['paymentstatus'] = $this->getPaymentState();
    if ($result['paymentstatus'] === ConfigInterface::PaymentStatus_Paid) {
      $result['paymentdate'] = $this->getPaymentDate();
    }

    $result['description'] = $this->getDescription();

    $result = array_merge($result, $this->getInvoiceTotals());

    return  $result;
  }

  /**
   * Completes the invoice part with default settings that do not depend on
   * shop specific data.
   */
  protected function addInvoiceDefaults() {
    $invoiceSettings = $this->config->getInvoiceSettings();
    $this->addDefault($this->invoice['customer']['invoice'], 'concept', ConfigInterface::Concept_No);
    $this->addDefault($this->invoice['customer']['invoice'], 'accountnumber', $invoiceSettings['defaultAccountNumber']);
    $this->addDefault($this->invoice['customer']['invoice'], 'costcenter', $invoiceSettings['defaultCostCenter']);
    if (isset($this->invoice['customer']['invoice']['paymentstatus'])
      && $this->invoice['customer']['invoice']['paymentstatus'] == ConfigInterface::PaymentStatus_Paid
      // 0 = empty = use same invoice template as for non paid invoices
      && $invoiceSettings['defaultInvoicePaidTemplate'] != 0
    ) {
      $this->addDefault($this->invoice['customer']['invoice'], 'template', $invoiceSettings['defaultInvoicePaidTemplate']);
    }
    else {
      $this->addDefault($this->invoice['customer']['invoice'], 'template', $invoiceSettings['defaultInvoiceTemplate']);
    }
    $this->addEmailAsPdf();
  }

  /**
   * Returns the number to use as invoice number.
   *
   * @param int $invoiceNumberSource
   *   \Siel\Acumulus\Shop\ConfigInterface\InvoiceNrSource_ShopInvoice or
   *   \Siel\Acumulus\Shop\ConfigInterface\InvoiceNrSource_ShopOrder.
   *
   * @return string
   *   The number to use as "invoice number" on the invoice, may contain a
   *   prefix.
   */
  abstract protected function getInvoiceNumber($invoiceNumberSource);

  /**
   * Returns the date to use as invoice date.
   *
   * @param int $dateToUse
   *   \Siel\Acumulus\Shop\ConfigInterface\InvoiceDate_InvoiceCreate or
   *   \Siel\Acumulus\Shop\ConfigInterface\InvoiceDate_OrderCreate

   * @return string
   *   Date to send to Acumulus as the invoice date: yyyy-mm-dd.
   */
  abstract protected function getInvoiceDate($dateToUse);

  /**
   * Returns whether the order has been paid or not.
   *
   * @return int
   *   \Siel\Acumulus\Invoice\ConfigInterface::PaymentStatus_Paid or
   *   \Siel\Acumulus\Invoice\ConfigInterface::PaymentStatus_Due
   */
  abstract protected function getPaymentState();

  /**
   * Returns the payment date.
   *
   * The payment date is defined as the date on which the status changed from a
   * non-paid state to a paid state. If there are multiple state changes, the
   * last one is taken.
   *
   * @return string|null
   *   The payment date (yyyy-mm-dd) or null if the order has not been paid yet.
   */
  abstract protected function getPaymentDate();

  /**
   * Returns the description for this invoice.
   *
   * This default implementation returns something like "Order 123".
   *
   * @return string
   *   Description ofr this invoice
   */
  protected function getDescription() {
    return ucfirst($this->t($this->source->getType())) . ' ' . $this->source->getReference();
  }

  /**
   * Returns an array with the totals fields.
   *
   * All total fields are optional but may be used or even expected by the
   * Completor or are used for support and debugging purposes.
   *
   * This default implementation returns an empty array. Override to provide the
   * values.
   *
   * @return array
   *   An array with the following possible keys:
   *   - meta-invoiceamount: the total invoice amount excluding VAT.
   *   - meta-invoiceamountinc: the total invoice amount including VAT.
   *   - meta-invoicevatamount: the total vat amount for the invoice.
   */
  protected function getInvoiceTotals() {
    return array();
  }

  /**
   * Returns the 'invoice''line' part of the invoice add structure.
   *
   * Each invoice line is a keyed array.
   * The following keys are allowed/expected by the API:
   * -itemnumber
   * -product
   * -unitprice
   * -vatrate
   * -quantity
   * -costprice: optional, only for margin products
   *
   * Additional keys (not recognised by the API but used by the Completor or
   * for support and debugging purposes):
   * - unitpriceinc: the price of the item per unit including VAT.
   * - vatamount: the amount of vat per unit.
   * - meta-vatrate-source: the source for the vatrate value. Can be one of:
   *   - exact: should be an existing VAT rate.
   *   - calculated: should be close to an existing VAT rate, but may contain a
   *       rounding error.
   *   - completor: to be filled in by the completor.
   *   - strategy: to be filled in by a tax divide strategy. This may lead to
   *       this line being split into multiple lines.
   * - meta-vatrate-min: the minimum value for the vat rate, based on the
   *     precision of the numbers used to calculate the vat rate.
   * - meta-vatrate-max: the maximum value for the vat rate, based on the
   *     precision of the numbers used to calculate the vat rate.
   * - meta-strategy-split: boolean that indicates if this line may be split
   *     into multiple lines to divide vat.
   * - (*)meta-lineprice: the total price for this line excluding VAT.
   * - (*)meta-linepriceinc: the total price for this line including VAT.
   * - meta-linevatamount: the amount of VAT for the whole line.
   * (*) = these are not yet used.
   *
   * These keys can be used to complete missing values or to assist in
   * correcting rounding errors in values that are present.
   *
   * The base CompletorInvoiceLines expects;
   * - meta-vatrate-source to be filled
   * - If meta-vatrate-source = exact: no other keys expected
   * - If meta-vatrate-source = calculated: meta-vatrate-min and
   *   meta-vatrate-max are expected to be filled. These values should come
   *   from calling the helper method getDivisionRange() with the values used to
   *   calculate the vat rate and their precision.
   * - If meta-vatrate-source = completor: vatrate should be null and unitprice
   *   should be 0. The completor will typically fill vatrate with the highest
   *   or most appearing vat rate, looking at the exact and calculated (after
   *   correcting them for rounding errors) vat rates.
   * - If meta-vatrate-source = strategy: vat rate should be null and either
   *   unitprice or unitpriceinc should be filled wit a non-0 amount (typically
   *   a negative amount as this is mostly used for spreading discounts over tax
   *   rates). Moreover, on the invoice level meta-invoiceamount and
   *   meta-invoicevatamount should be filled in. The completor will use a tax
   *   divide strategy to arrive at valid values for the missing fields.
   *
   * Extending classes should normally not have to override this method, but
   * should instead implement getItemLines(), getManualLines(),
   * getGiftWrappingLine(), getShippingLine(), getPaymentFeeLine(),
   * and getDiscountLines().
   *
   * @return array
   *   A non keyed array with all invoice lines.
   */
  protected function getInvoiceLines() {
    $itemLines = $this->getItemLines();
    $manualLines = $this->getManualLines();
    $feeLines = $this->getFeeLines();
    $discountLines = $this->getDiscountLines();

    $result = array_merge($itemLines, $manualLines, $feeLines, $discountLines);
    return $result;
  }

  /**
   * Returns the item/product lines of the order.
   *
   * @return array
   *  Array of item line arrays.
   */
  abstract protected function getItemLines();

  /**
   * Returns any manual lines.
   *
   * Normally there are no manual lines, but Magento does support it for credit
   * notes.
   *
   * @return array
   *  An array of manual line arrays.
   */
  abstract protected function getManualLines();

  /**
   * Returns all the fee lines for the order.
   *
   * @return array
   *  An array of fee line arrays
   */
  protected function getFeeLines() {
    $result = array();

    $line = $this->getGiftWrappingLine();
    if ($line) {
      $result[] = $line;
    }

    $line = $this->getShippingLine();
    if ($line) {
      $result[] = $line;
    }

    $line = $this->getPaymentFeeLine();
    if ($line) {
      $result[] = $line;
    }

    return $result;

  }

  /**
   * Returns the gift wrapping costs line.
   *
   * This base implementation return an empty array: no gift wrapping.
   *
   * @return array
   *   A line array, empty if there is no gift wrapping fee line.
   */
  abstract protected function getGiftWrappingLine();

  /**
   * Returns the shipping costs line.
   *
   * To be able to produce a packing slip, a shipping line should normally be
   * added, even for free shipping.
   *
   * @return array
   *   A line array, empty if there is no shipping fee line.
   */
  abstract protected function getShippingLine();

  /**
   * Returns the payment fee line.
   *
   * @return array
   *   A line array, empty if there is no payment fee line.
   */
  abstract protected function getPaymentFeeLine();

  /**
   * Returns any applied discounts and partial payments.
   *
   * @return array
   *   An array of discount line arrays.
   */
  abstract protected function getDiscountLines();

  /**
   * Adds an emailaspdf section if enabled.
   */
  protected function addEmailAsPdf() {
    $emailAsPdfSettings = $this->config->getEmailAsPdfSettings();
    if ($emailAsPdfSettings['emailAsPdf'] && !empty($this->invoice['customer']['email'])) {
      $emailasPdf = array();
      $emailasPdf['emailto'] = $this->invoice['customer']['email'];
      $this->addDefault($emailasPdf, 'emailbcc', $emailAsPdfSettings['emailBcc']);
      $this->addDefault($emailasPdf, 'emailfrom', $emailAsPdfSettings['emailFrom']);
      $this->addDefault($emailasPdf, 'subject', strtr($emailAsPdfSettings['subject'], array(
        '[#b]' => $this->source->getReference(),
        '[#f]' => isset($this->invoice['customer']['invoice']['number']) ? $this->invoice['customer']['invoice']['number'] : '',
      )));
      $invoice['customer']['invoice']['emailaspdf']['confirmreading'] = $emailAsPdfSettings['confirmReading'] ? ConfigInterface::ConfirmReading_Yes : ConfigInterface::ConfirmReading_No;
      $this->invoice['customer']['invoice']['emailaspdf'] = $emailasPdf;
    }
  }

  /**
   * Returns whether the margin scheme may be used.
   *
   * @return bool
   */
  protected function allowMarginScheme() {
    $invoiceSettings = $this->config->getInvoiceSettings();
    return $invoiceSettings['useMargin'];
  }

  /**
   * Helper method to add a value from an array or object only if it is set and
   * not empty.
   *
   * @param array $targetArray
   * @param string $targetKey
   * @param array|object $source
   * @param string $sourceKey
   *
   * @return bool
   *   Whether the array value or object property is set and not empty and thus
   *   has been added.
   */
  protected function addIfSetAndNotEmpty(array &$targetArray, $targetKey, $source, $sourceKey) {
    if (is_array($source)) {
      if (!empty($source[$sourceKey])) {
        $targetArray[$targetKey] = $source[$sourceKey];
        return true;
      }
    }
    else {
      if (!empty($source->$sourceKey)) {
        $targetArray[$targetKey] = $source->$sourceKey;
        return true;
      }
    }
    return false;
  }

  /**
   * Helper method to add a non-empty value to an array.
   *
   * @param array $array
   * @param string $key
   * @param mixed $value
   *
   * @return bool
   *   True if the value was not empty and thus has been added, false otherwise,
   */
  protected function addIfNotEmpty(array &$array, $key, $value) {
    if (!empty($value)) {
      $array[$key] = $value;
      return true;
    }
    return false;
  }

  /**
   * Helper method to add a value to an array even if it is not set.
   *
   * @param array $array
   * @param string $key
   * @param mixed $value
   * @param mixed $default
   *
   * @return bool
   *   True if the value was not empty and thus has been added, false if the
   *   default has been added.,
   */
  protected function addEmpty(array &$array, $key, $value, $default = '') {
    if (!empty($value)) {
      $array[$key] = $value;
      return true;
    }
    else {
      $array[$key] = $default;
      return false;
    }
  }

  /**
   * Helper method to add a default (without overwriting) value to an array.
   *
   * @param array $array
   * @param string $key
   * @param mixed $value
   *
   * @return bool
   *   Whether the default was added.
   */
  protected function addDefault(array &$array, $key, $value) {
    if (empty($array[$key]) && !empty($value)) {
      $array[$key] = $value;
      return true;
    }
    return false;
  }

  /**
   * Returns the range within which the result of the division should fall given
   * the precision range for the 2 numbers to divide.
   *
   * @param float $numerator
   * @param float $denominator
   * @param float $precisionNumerator
   * @param float $precisionDenominator
   *
   * @return array
   *   Array of floats with keys min, max and calculated.
   */
  protected function getDivisionRange($numerator, $denominator, $precisionNumerator = 0.01, $precisionDenominator = 0.01) {
    // The actual value can be half the precision lower or higher.
    $numeratorHalfRange = $precisionNumerator / 2.0;
    $denominatorHalfRange = $precisionDenominator / 2.0;

    // The min values should be closer to 0 then the value.
    // The max values should be further from 0 then the value.
    if ($numerator < 0.0) {
      $numeratorHalfRange = -$numeratorHalfRange;
    }
    $minNumerator = $numerator - $numeratorHalfRange;
    $maxNumerator = $numerator + $numeratorHalfRange;

    if ($denominator < 0.0) {
      $denominatorHalfRange = -$denominatorHalfRange;
    }
    $minDenominator = $denominator - $denominatorHalfRange;
    $maxDenominator = $denominator + $denominatorHalfRange;

    // We get the min value of the division by dividing the minimum numerator by
    // the maximum denominator and vice versa.
    $min = $minNumerator / $maxDenominator;
    $max = $maxNumerator / $minDenominator;
    $calculated = $numerator / $denominator;

    return array('min' => $min, 'calculated' => $calculated, 'max' => $max);
  }

  /**
   * Wrapper around getDivisionRange() that returns the values under the key
   * names as the Completor expects them.
   *
   * If $numerator = 0 the vatrate will be set to 0 and treat as being exact.
   *
   * @param float $numerator
   * @param float $denominator
   * @param float $precisionNumerator
   * @param float $precisionDenominator
   *
   * @return array
   *   Array with keys vatrate, meta-vatrate-min, meta-vatrate-max, and
   *   meta-vatrate-source.
   */
  protected function getVatRangeTags($numerator, $denominator, $precisionNumerator = 0.01, $precisionDenominator = 0.01) {
    if ($this->floatsAreEqual($numerator, 0.0, 0.0001)) {
      return array(
        'vatrate' => 0,
        'vatamount' => $numerator,
        'meta-vatrate-source' => static::VatRateSource_Exact0,
      );
    }
    else {
      $range = $this->getDivisionRange($numerator, $denominator, $precisionNumerator, $precisionDenominator);
      return array(
        'vatrate' => 100.0 * $range['calculated'],
        'vatamount' => $numerator,
        'meta-vatrate-min' => 100.0 * $range['min'],
        'meta-vatrate-max' => 100.0 * $range['max'],
        'meta-vatrate-source' => static::VatRateSource_Calculated,
      );
    }
  }

  /**
   * Helper method to do a float comparison.
   *
   * @param float $f1
   * @param float $f2
   * @param float $maxDiff
   *
   * @return bool
   *   True if the the floats are "equal", i.e. do not differ more than the
   *   specified maximum difference.
   */
  protected function floatsAreEqual($f1, $f2, $maxDiff = 0.005) {
    return abs($f2 - $f1) < $maxDiff;
  }

  /**
   * indicates if a float is to be considered a non-zero amount.
   *
   * This is a wrapper around floatsAreEqual() for the most used situation where
   * an amount is checked for not being 0.0.
   *
   * @param $f1
   * @param float $maxDiff
   *
   * @return bool
   */
  protected function isAmount($f1, $maxDiff = 0.001) {
    return !$this->floatsAreEqual($f1, 0.0, $maxDiff);
  }

  /**
   * indicates if a float is to be considered zero.
   *
   * This is a wrapper around floatsAreEqual() for the often used case where
   * an amount is checked for being 0.0.
   *
   * @param $f1
   * @param float $maxDiff
   *
   * @return bool
   */
  protected function isZero($f1, $maxDiff = 0.001) {
    return $this->floatsAreEqual($f1, 0.0, $maxDiff);
  }

}
