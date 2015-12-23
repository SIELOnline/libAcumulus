<?php
namespace Siel\Acumulus\Invoice;

use Exception;
use Siel\Acumulus\Helpers\Countries;
use Siel\Acumulus\Helpers\Number;
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

  const LineType_Shipping = 'shipping';
  const LineType_PaymentFee = 'payment';
  const LineType_GiftWrapping = 'gift';
  const LineType_Manual = 'manual';
  const LineType_Order = 'product';
  const LineType_Discount = 'discount';
  const LineType_Other = 'other';

  /** @var \Siel\Acumulus\Shop\Config */
  protected $config;

  /** @var \Siel\Acumulus\Helpers\TranslatorInterface */
  protected $translator;

  /** @var \Siel\Acumulus\Helpers\Countries */
  protected $countries;

  /** @var array Resulting Acumulus invoice */
  protected $invoice = array();

  /** @var Source */
  protected $invoiceSource;

  /**
   * Constructor.
   *
   * @param \Siel\Acumulus\Shop\Config $config
   * @param \Siel\Acumulus\Helpers\TranslatorInterface $translator
   */
  public function __construct(Config $config, TranslatorInterface $translator) {
    $this->config = $config;

    $this->translator = $translator;
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
   * @param Source $invoiceSource
   */
  protected function setInvoiceSource($invoiceSource) {
    $this->invoiceSource = $invoiceSource;
    if (!in_array($invoiceSource->getType(), array(Source::Order, Source::CreditNote))) {
      $this->config->getLog()->error('Creator::setSource(): unknown source type %s', $this->invoiceSource->getType());
    };

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
    $this->setInvoiceSource($source);
    $this->invoice = array();
    $this->invoice['customer'] = $this->getCustomer();
    $this->addCustomerDefaults();
    $this->invoice['customer']['invoice'] = $this->getInvoice();
    $this->addInvoiceDefaults();
    $this->invoice['customer']['invoice']['line'] = $this->getInvoiceLines();
    $this->addEmailAsPdf();
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
    $customerSettings = $this->config->getCustomerSettings();
    $this->addDefault($this->invoice['customer'], 'overwriteifexists', $customerSettings['overwriteIfExists'] ? ConfigInterface::OverwriteIfExists_Yes : ConfigInterface::OverwriteIfExists_No);
    $this->addDefault($this->invoice['customer'], 'type', $customerSettings['defaultCustomerType']);
    if (!empty($customerSettings['salutation'])) {
      $this->invoice['customer']['salutation'] = $this->getSalutation($customerSettings['salutation']);
    }
    $this->addDefault($this->invoice['customer'], 'countrycode', 'nl');
    $this->convertEuCountryCode();
    $this->addDefault($this->invoice['customer'], 'country', $this->countries->getCountryName($this->invoice['customer']['countrycode']));
  }

  /**
   * Returns the salutation based on the (customer) setting for the salutation.
   *
   * This base implementation will do token expansion by searching for tokens
   * and calling the getProperty() method for each token found. So, normally it
   * won't be necessary to override this method, as overriding getProperty will
   * be more likely.
   *
   * @param string $salutation
   *
   * @return string
   *   The salutation for the customer of this order.
   */
  protected function getSalutation($salutation) {
    $salutation = preg_replace_callback('/\[#([^]]+)]/', array($this, 'salutationMatch'), $salutation);
    return $salutation;
  }

  /**
   * Callback for the preg_replace_callback call in Creator::getSalutation().
   *
   * This base implementation call the getProperty() method with the part of the
   * match between the #s, so, normally it won't be necessary to override this
   * method, as overriding getProperty will be more likely.
   *
   * @param array $matches
   *
   * @return string
   *   The salutation for the customer of this order.
   */
  protected function salutationMatch($matches) {
    return $this->searchProperty($matches[1]);
  }

  /**
   * Searches for a property in the various shop specific arrays and/or objects.
   *
   * This base implementation only looks up in the shop source object or array,
   * but that may be insufficient if the customer data is in a separate object
   * or array.
   *
   * @param string $property
   *
   * @return string
   *
   */
  protected function searchProperty($property) {
    return $this->getProperty($property, $this->invoiceSource->getSource());
  }

  /**
   * Looks up a property in the web shop specific order object/array.
   *
   * This default implementation looks for the property in the following ways:
   * If the passed parameter is an array:
   * - looking up the property as key.
   * If the passed parameter is an object:
   * - Looking up the property by name (as existing property or via __get).
   * - Calling the get{Property} getter.
   * - Calling the {property}() method (as existing method or via __call).
   *
   * Override if the property name or getter method is constructed differently.
   *
   * @param string $property
   * @param object|array $object
   *
   * @return string
   *   The value for the property of the given name or the empty string if not
   *   available.
   */
  protected function getProperty($property, $object) {
    $value = '';
    if (is_array($object)) {
      if (isset($object[$property])) {
        $value = $object[$property];
      }
    }
    else {
      if (isset($object->$property)) {
        $value = $object->$property;
      }
      else if (method_exists($object, $property)) {
        $value = $object->$property();
      }
      else {
        $method = 'get' . ucfirst($property);
        if (method_exists($object, $method)) {
          $value = $object->$method();
        }
        else if (method_exists($object, '__get')) {
          @$value = $object->$property;
        }
        else if (method_exists($object, '__call')) {
          try {
            $value = @$object->$property();
          }
          catch (Exception $e) {
          }
          if (empty($value)) {
            try {
              $value = $object->$method();
            }
            catch (Exception $e) {
            }
          }
        }
      }
    }
    return $value;
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
    $this->invoice['customer']['countrycode'] = $this->countries->convertEuCountryCode($this->invoice['customer']['countrycode']);
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

    $sourceToUse = $invoiceSettings['invoiceNrSource'];
    if ($sourceToUse != ShopConfigInterface::InvoiceNrSource_Acumulus) {
      $result['number'] = $this->getInvoiceNumber($sourceToUse);
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

    return $result;
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
   *
   * @return string
   *   Date to send to Acumulus as the invoice date: yyyy-mm-dd.
   */
  protected function getInvoiceDate($dateToUse) {
    return $this->callSourceTypeSpecificMethod(__FUNCTION__, func_get_args());
  }

  /**
   * Returns whether the order has been paid or not.
   *
   * @return int
   *   \Siel\Acumulus\Invoice\ConfigInterface::PaymentStatus_Paid or
   *   \Siel\Acumulus\Invoice\ConfigInterface::PaymentStatus_Due
   */
  protected function getPaymentState() {
    return $this->callSourceTypeSpecificMethod(__FUNCTION__, func_get_args());
  }

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
  protected function getPaymentDate() {
    return $this->callSourceTypeSpecificMethod(__FUNCTION__, func_get_args());
  }

  /**
   * Returns the description for this invoice.
   *
   * This default implementation returns something like "Order 123".
   *
   * @return string
   *   Description ofr this invoice
   */
  protected function getDescription() {
    return ucfirst($this->t($this->invoiceSource->getType())) . ' ' . $this->invoiceSource->getReference();
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
   * - meta-line-type: the type of line (order, shipping, discount, etc.)
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
   * @return array[]
   *   A non keyed array with all invoice lines.
   */
  protected function getInvoiceLines() {
    $itemLines = $this->getItemLines();
    $itemLines = $this->addLineType($itemLines, static::LineType_Order);

    $feeLines = $this->getFeeLines();

    $discountLines = $this->getDiscountLines();
    $discountLines = $this->addLineType($discountLines, static::LineType_Discount);

    $manualLines = $this->getManualLines();
    $manualLines = $this->addLineType($manualLines, static::LineType_Manual);

    $result = array_merge($itemLines, $feeLines, $discountLines, $manualLines);
    return $result;
  }

  /**
   * Returns the item/product lines of the order.
   *
   * @return array[]
   *  Array of item line arrays.
   */
  protected function getItemLines() {
    return $this->callSourceTypeSpecificMethod(__FUNCTION__, func_get_args());
  }

  /**
   * Returns all the fee lines for the order.
   *
   * @return array[]
   *  An array of fee line arrays
   */
  protected function getFeeLines() {
    $result = array();

    $line = $this->getGiftWrappingLine();
    if ($line) {
      $line['meta-line-type'] = static::LineType_GiftWrapping;
      $result[] = $line;
    }

    $line = $this->getShippingLine();
    if ($line) {
      $line['meta-line-type'] = 'shipping';
      $result[] = $line;
    }

    $line = $this->getPaymentFeeLine();
    if ($line) {
      $line['meta-line-type'] = static::LineType_PaymentFee;
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
  protected function getGiftWrappingLine() {
    return array();
  }

  /**
   * Returns the shipping costs line.
   *
   * To be able to produce a packing slip, a shipping line should normally be
   * added, even for free shipping.
   *
   * @return array
   *   A line array, empty if there is no shipping fee line.
   */
  protected function getShippingLine() {
    return $this->callSourceTypeSpecificMethod(__FUNCTION__, func_get_args());
  }

  /**
   * Returns the payment fee line.
   *
   * This base implementation returns an empty array: no payment fee line.
   *
   * @return array
   *   A line array, empty if there is no payment fee line.
   */
  protected function getPaymentFeeLine() {
    return array();
  }

  /**
   * Returns any applied discounts and partial payments.
   *
   * @return array[]
   *   An array of discount line arrays.
   */
  protected function getDiscountLines() {
    return $this->callSourceTypeSpecificMethod(__FUNCTION__, func_get_args());
  }

  /**
   * Returns any manual lines.
   *
   * Manual lines may appear on credit notes to overrule amounts as calculated
   * by the system. E.g. discounts applied on items should be taken into
   * account when refunding (while the system did not or does not know if the
   * discount also applied to that product), shipping costs may be returned
   * except for the handling costs, etc.
   *
   * @return array[]
   *  An array of manual line arrays, may be empty.
   */
  protected function getManualLines() {
    return array();
  }

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
        '[#b]' => $this->invoiceSource->getReference(),
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
        return TRUE;
      }
    }
    else {
      if (!empty($source->$sourceKey)) {
        $targetArray[$targetKey] = $source->$sourceKey;
        return TRUE;
      }
    }
    return FALSE;
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
      return TRUE;
    }
    return FALSE;
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
      return TRUE;
    }
    else {
      $array[$key] = $default;
      return FALSE;
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
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Adds a meta-line-type tag to the lines.
   *
   * @param array $itemLines
   * @param string $lineType
   *
   * @return array
   *
   */
  protected function addLineType(array $itemLines, $lineType) {
    foreach ($itemLines as &$itemLine) {
      $itemLine['meta-line-type'] = $lineType;
    }
    return $itemLines;
  }

  /**
   * Returns the range in which the vat rate will lie.
   *
   * If a webshop does not store the vat rates used in the order, we must
   * calculate them using a (product) price and the vat on it. But as webshops
   * often store these numbers rounded to cents, the vat rate calculation
   * becomes imprecise. Therefore we compute the range in which it will lie and
   * will let the Completor do a comparison with the actual vat rates that an
   * order can have (one of the Dutch or, for electronic services, other EU
   * country VAT rates).
   *
   * - If $denominator = 0 the vatrate will be set to NULL and the Completor may
   *   try to get this line listed under the correct vat rate.
   * - If $numerator = 0 the vatrate will be set to 0 and be treated as if it
   *   is an exact vat rate, not a vat range
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
    if (Number::isZero($denominator, 0.0001)) {
      return array(
        'vatrate' => NULL,
        'meta-vatrate-source' => static::VatRateSource_Completor,
      );
    }
    if (Number::isZero($numerator, 0.0001)) {
      return array(
        'vatrate' => 0,
        'vatamount' => $numerator,
        'meta-vatrate-source' => static::VatRateSource_Exact0,
      );
    }
    else {
      $range = Number::getDivisionRange($numerator, $denominator, $precisionNumerator, $precisionDenominator);
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
   * Returns the total amount for the given invoice lines.
   *
   * @param array[] $lines
   *   An array of invoice lines
   *
   * @return float
   */
  protected function getLinesTotal(array $lines) {
    $linesAmount = 0.0;
    foreach ($lines as $line) {
      if (isset($line['meta-linepriceinc'])) {
        $linesAmount += $line['meta-linepriceinc'];
      }
      else if (isset($line['unitpriceinc'])) {
        $linesAmount += $line['quantity'] * $line['unitpriceinc'];
      }
      else /* if (isset($line['unitprice']) && isset($line['vatrate'])) */ {
        $linesAmount += $line['quantity'] * $line['unitprice'] * ((100 + $line['vatrate']) / 100);
      }
    }
    return $linesAmount;
  }

  /**
   * Returns the sign to use for amounts that are always defined as a positive
   * number, also on credit notes.
   *
   * @return float
   *   1 for orders, -1 for credit notes.
   */
  protected function getSign() {
    return (float) ($this->invoiceSource->getType() === Source::CreditNote ? -1 : 1);
  }

  /**
   * Calls a method constructed of the method name and the source type.
   *
   * If the implementation/override of a method depends on the type of invoice
   * source it might be better to implement 1 method per source type. This
   * method calls such a method assuming it is named {method}{source-type}.
   * Example: getLineItem($line) would be very different for an order versus a
   * credit note: do not override the base method but implement 2 new methods
   * getLineItemOrder($line) and getLineItemCreditNote($line).
   *
   * @param string $method
   * @param array $args
   *
   * @return mixed|void
   */
  protected function callSourceTypeSpecificMethod($method, $args = array()) {
    $method .= $this->invoiceSource->getType();
    return call_user_func_array(array($this, $method), $args);
  }

}
