<?php
/**
 * @file Definition of Siel\Acumulus\Common\WebAPI.
 */

namespace Siel\Acumulus\Common;

/**
 * Class WebAPI provides an easy interface towards the different API calls of
 * the Acumulus WebAPI.
 *
 * This class simplifies the communication so that the different web shop
 * specific interfaces can be more rapidly developed.
 *
 * More info:
 * - https://apidoc.sielsystems.nl/
 * - http://www.siel.nl/webkoppelingen/
 *
 * The WebAPI call wrappers return their information as a keyed array, which is
 * a simplified version of the call specific response structure and the exit
 * structure as described on
 * https://apidoc.sielsystems.nl/content/warning-error-and-status-response-section-most-api-calls.
 *
 * The general part is represented by the following keys in the result:
 * - status: int; 0 = success; 1 = Failed, Errors found; 2 = Success with
 *   warnings; 3 = Exception, Please contact Acumulus technical support.
 * - errors: an array of errors, an error is an array with the following keys:
 *   - code: int, see https://apidoc.sielsystems.nl/content/exit-and-warning-codes
 *   - codetag: string, a special code tag. Use this as a reference when
 *        communicating with Acumulus technical support.
 *   - message: string, a message describing the warning or error.
 * - warnings: an array of warning arrays, these have the same keys as an error.
 *
 * @package Siel\Acumulus
 */
class WebAPI {
  // Constants for some fields in the API.
  const Status_Success = WebAPICommunication::Status_Success;
  const Status_Errors = WebAPICommunication::Status_Errors;
  const Status_Warnings = WebAPICommunication::Status_Warnings;
  const Status_Exception = WebAPICommunication::Status_Exception;

  const PaymentStatus_Due = 1;
  const PaymentStatus_Paid = 2;

  const OverwriteIfExists_No = 0;
  const OverwriteIfExists_Yes = 1;

  const Concept_No = 0;
  const Concept_Yes = 1;

  const LocationCode_None = 0;
  const LocationCode_NL = 1;
  const LocationCode_EU = 2;
  const LocationCode_RestOfWorld = 3;

  const VatType_National = 1;
  const VatType_NationalReversed = 2;
  const VatType_EuReversed = 3;
  const VatType_RestOfWorld = 4;
  const VatType_MarginScheme = 5;
  const VatType_ForeignVat = 6;

  const ConfirmReading_No = 0;
  const ConfirmReading_Yes = 1;

  /** @var \Siel\Acumulus\Common\ConfigInterface */
  protected $config;

  /** @var \Siel\Acumulus\Common\WebAPICommunication */
  protected $webAPICommunicator;

  /**
   * Constructor.
   *
   * @param ConfigInterface $config
   */
  public function __construct(ConfigInterface $config) {
    $this->config = $config;
    if ($this->config->getDebug() == ConfigInterface::Debug_StayLocal) {
      require_once(dirname(__FILE__) . '/WebAPICommunicationLocal.php');
      $this->webAPICommunicator = new WebAPICommunicationLocal($config);
    }
    else {
      $this->webAPICommunicator = new WebAPICommunication($config);
    }
  }

  /**
   * Checks if the requirements for the environment are met.
   *
   * @return array
   *   A possibly empty array with messages regarding missing requirements.
   */
  public function checkRequirements() {
    $result = array();

    // PHP 5.3 is a requirement as well because we use namespaces. But as the
    // parser will already have failed fatally before we get here, it makes no
    // sense to check here.
    if (!extension_loaded('curl')) {
      $result['errors'][] = array(
        'code' => 'curl',
        'codetag' => '',
        'message' => $this->config->t('message_error_req_curl'),
      );
    }
    if ($this->config->getOutputFormat() === 'xml' && !extension_loaded('simplexml')) {
      $result['errors'][] = array(
        'code' => 'SimpleXML',
        'codetag' => '',
        'message' => $this->config->t('message_error_req_xml'),
      );
    }
    if (!extension_loaded('dom')) {
      $result['errors'][] = array(
        'code' => 'DOM',
        'codetag' => '',
        'message' => $this->config->t('message_error_req_dom'),
      );
    }

    return $result;
  }

  /**
   * If the result contains any errors or warnings, a list of verbose messages
   * is returned.
   *
   * @param array $result
   * @param bool $addTraceMessages
   *
   * @return array
   *   An array with textual messages tha can be used to inform the user.
   */
  public function resultToMessages(array $result, $addTraceMessages = true) {
    $messages = array();
    foreach ($result['errors'] as $error) {
      $message = "{$error['code']}: ";
      $message .= $this->config->t($error['message']);
      if ($error['codetag']) {
        $message .= " ({$error['codetag']})";
      }
      $messages[] = $this->config->t('message_error') . ' ' . $message;
    }
    foreach ($result['warnings'] as $warning) {
      $message = "{$warning['code']}: ";
      $message .= $this->config->t($warning['message']);
      if ($warning['codetag']) {
        $message .= " ({$warning['codetag']})";
      }
      $messages[] = $this->config->t('message_warning') . ' ' . $message;
    }

    if ($addTraceMessages && (!empty($messages) || $this->config->getDebug() != ConfigInterface::Debug_None)) {
      if (isset($result['trace'])) {
        $messages[] = $this->config->t('message_info_for_user');
        if (isset($result['trace']['request'])) {
          $messages[] = $this->config->t('message_sent') . ":\n" . $result['trace']['request'];
        }
        if (isset($result['trace']['response'])) {
          $messages[] = $this->config->t('message_received') . ":\n" . $result['trace']['response'];
        }
      }
    }

    return $messages;
  }

  /**
   * Converts an array of messages to a string that can be used in a text mail.
   *
   * @param array $messages
   *
   * @return string
   */
  public function messagesToText(array $messages) {
    return '* ' . join("\n\n* ", $messages) . "\n\n";
  }

  /**
   * Converts an array of messages to a string that can be used in an html mail.
   *
   * @param array $messages
   *
   * @return string
   */
  public function messagesToHtml(array $messages) {
    $messages_html = array();
    foreach ($messages as $message) {
      $messages_html[] = nl2br(htmlspecialchars($message, ENT_NOQUOTES));
    }
    return '<ul><li>' . join("</li><li>", $messages_html) . '</li></ul>';
  }

  /**
   * @param int $status
   *
   * @return string
   */
  public function getStatusText($status) {
    switch ($status) {
      case static::Status_Success:
        return $this->config->t('message_response_0');
      case static::Status_Errors:
        return $this->config->t('message_response_1');
      case static::Status_Warnings:
        return $this->config->t('message_response_2');
      case static::Status_Exception:
        return $this->config->t('message_response_3');
      default:
        return $this->config->t('message_response_x') . $status;
    }
  }

  /**
   * Returns whether the country is the Netherlands.
   *
   * For now only the alpha-2 codes are allowed. Other notations might be added
   * as soon we support a web shop with a different way of storing countries.
   *
   * @param string $countryCode
   *   Case insensitive ISO 3166-1 alpha-2 country code.
   *
   * @return bool
   */
  public function isNl($countryCode) {
    return strtoupper($countryCode) === 'NL';
  }

  /**
   * Returns whether the country is a EU country outside the Netherlands.
   *
   * For now only the alpha-2 codes are allowed. Other notations might be added
   * as soon we support a web shop with a different way of storing countries.
   *
   * @param string $countryCode
   *   Case insensitive ISO 3166-1 alpha-2 country code.
   *
   * @return bool
   */
  public function isEu($countryCode) {
    // Sources:
    // - http://publications.europa.eu/code/pdf/370000en.htm
    // - http://en.wikipedia.org/wiki/ISO_3166-1_alpha-2
    // EFTA countries are not part of this list because regarding invoicing they
    // are considered to be outside of the EU.
    $euCountryCodes = array(
      'BE',
      'BG',
      'CZ',
      'DK',
      'DE',
      'EE',
      'IE',
      'EL', // Greece according to the EU
      'ES',
      'FR',
      'GB', // Great Britain/United Kingdom according to ISO.
      'GR', // Greece according to the ISO
      'HR',
      'IT',
      'CY',
      'LV',
      'LT',
      'LU',
      'HU',
      'MT',
      //'NL', // In EU, but outside the Netherlands.
      'AT',
      'PL',
      'PT',
      'RO',
      'SI',
      'SK',
      'FI',
      'SE',
      'UK', // United kingdom, Great Britain according to the EU
    );
    return in_array(strtoupper($countryCode), $euCountryCodes);
  }

  /**
   * Retrieves a list of accounts.
   *
   * @return array
   *   Besides the general response structure, the actual result of this call is
   *   returned under the key 'accounts' and consists of an array of 'accounts',
   *   each 'account' being a keyed array with keys:
   *   - accountid
   *   - accountnumber
   *   - accountdescription
   *
   * See https://apidoc.sielsystems.nl/content/picklist-accounts-bankrekeningen.
   */
  public function getPicklistAccounts() {
    return $this->getPicklist('account');
  }

  /**
   * Retrieves a list of contact types.
   *
   * @return array
   *   Besides the general response structure, the actual result of this call is
   *   returned under the key 'contacttypes' and consists of an array of
   *   'contacttypes', each 'contacttype' being a keyed array with keys:
   *   - contacttypeid
   *   - contacttypename
   *
   * See https://apidoc.sielsystems.nl/content/picklist-contacttypes-contactsoorten.
   */
  public function getPicklistContactTypes() {
    return $this->getPicklist('contacttype');
  }

  /**
   * Retrieves a list of cost centers.
   *
   * @return array
   *   Besides the general response structure, the actual result of this call is
   *   returned under the key 'costcenters' and consists of an array of
   *   'costcenters', each 'costcenter' being a keyed array with keys:
   *   - costcenterid
   *   - costcentername
   *
   * See https://apidoc.sielsystems.nl/content/picklist-costcenters-kostenplaatsen.
   */
  public function getPicklistCostCenters() {
    return $this->getPicklist('costcenter');
  }

  /**
   * Retrieves a list of cost headings.
   *
   * @return array
   *   Besides the general response structure, the actual result of this call is
   *   returned under the key 'costheadings' and consists of an array of
   *   'costheadings', each 'costheading' being a keyed array with keys:
   *   - costheadingid
   *   - costheadingname
   *
   * See https://apidoc.sielsystems.nl/content/picklist-costheadings-kostensoorten.
   */
  public function getPicklistCostHeadings() {
    return $this->getPicklist('costheading');
  }

  /**
   * Retrieves a list of cost types.
   *
   * @return array
   *   Besides the general response structure, the actual result of this call is
   *   returned under the key 'invoicetemplates' and consists of an array of
   *   'invoicetemplates', each 'invoicetemplate' being a keyed array with keys:
   *   - invoicetemplateid
   *   - invoicetemplatename
   *
   * See https://apidoc.sielsystems.nl/content/picklist-invoice-templates-factuursjablonen.
   */
  public function getPicklistInvoiceTemplates() {
    return $this->getPicklist('invoicetemplate');
  }

  /**
   * Retrieves a list of VAT types.
   *
   * @return array
   *   Besides the general response structure, the actual result of this call is
   *   returned under the key 'vattypes' and consists of an array of 'vattypes',
   *   each 'vattype' being a keyed array with keys:
   *   - 'vattypeid'
   *   - 'vattypename'
   *
   * See https://apidoc.sielsystems.nl/content/picklist-vattypes-btw-groepen.
   */
  public function getPicklistVatTypes() {
    return $this->getPicklist('vattype');
  }

  /**
   * A helper method to retrieve a given picklist.
   *
   * The Acumulus API for picklists is so well standardized, that it is possible
   * to use 1 general picklist retrieval function that can process all picklist
   * types.
   *
   * @param string $picklist
   *   The picklist to retrieve, specify in singular form: account, contacttype,
   *   costcenter, etc.
   *
   * @return array
   *   Besides the general response structure, the actual result of this call is
   *   returned under the key $picklist in plural format (with an 's' attached)
   *   and consists of an array of keyed arrays, each keyed array being 1 result
   *   of the requested picklist.
   */
  protected function getPicklist($picklist) {
    $plural = $picklist . 's';
    $response = $this->webAPICommunicator->callApiFunction("picklists/picklist_$plural", array());
    // Simplify result: remove indirection.
    if (!empty($response[$plural][$picklist])) {
      $response[$plural] = $response[$plural][$picklist];
      // If there was only 1 result, it wasn't put in an array.
      if (!is_array(reset($response[$plural]))) {
        $response[$plural] = array($response[$plural]);
      }
    }
    else {
      $response[$plural] = array();
    }
    return $response;
  }

  /**
   * Retrieves a list of VAT rates for the given country at the given date.
   *
   * @param string $countryCode
   *   Country code of the country to retrieve the VAT info for.
   * @param string $date
   *   ISO date string (yyyy-mm-dd) for the date to retrieve the VAT info for.
   *
   * @return array
   *   Besides the general response structure, the actual result of this call is
   *   returned under the key 'vatinfo' and consists of an array of "vatinfo's",
   *   each 'vatinfo' being a keyed array with keys:
   *   - vattype
   *   - vatrate
   *
   * See https://apidoc.sielsystems.nl/content/lookup-vatinfo-btw-informatie.
   */
  public function getVatInfo($countryCode, $date = '') {
    if (empty($date)) {
      $date = date('Y-m-d');
    }
    $message = array(
      'vatdate' => $date,
      'vatcountry' => $countryCode,
    );
    $response = $this->webAPICommunicator->callApiFunction("lookups/lookup_vatinfo", $message);
    // Simplify result: remove indirection.
    if (!empty($response['vatinfo']['vat'])) {
      $response['vatinfo'] = $response['vatinfo']['vat'];
      // If there was only 1 result, it wasn't put in an array.
      if (!is_array(reset($response['vatinfo']))) {
        $response['vatinfo'] = array($response['vatinfo']);
      }
    }
    else {
      $response['vatinfo'] = array();
    }
    return $response;
  }

  /**
   * Adds an invoice to Acumulus.
   *
   * Before sending the invoice to Acumulus, a number of checks and completions
   * are performed on the invoice.
   *
   * @param array $invoice
   *   The invoice to add.
   * @param string $orderId
   *   The order id, only used for error reporting.
   *
   * @return array
   *   Besides the general response structure, the actual result of this call is
   *   returned under the following key:
   *   - invoice: an array of information about the created invoice, being an
   *     array with keys:
   *     - invoicenumber
   *     - token
   *     - entryid
   *   If the key invoice is present, it indicates success.
   *
   * See https://apidoc.sielsystems.nl/content/invoice-add.
   * See https://apidoc.sielsystems.nl/content/warning-error-and-status-response-section-most-api-calls
   * for more information on the contents of the returned array.
   */
  public function invoiceAdd(array $invoice, $orderId = '') {
    $response = array(
      'errors' => array(),
      'warnings' => array(),
      'status' => static::Status_Success,
    );

    // Complete the invoice with configured default values.
    $invoice = $this->completeInvoice($invoice);

    // Check and correct the invoice where necessary and possible.
    $invoice = $this->correctCountryCode($invoice);
    $invoice = $this->addEmailAsPdf($invoice, $orderId);
    $invoice = $this->fictitiousClient($invoice);
    $invoice = $this->validateVatRates($invoice, $orderId, $response);
    $invoice = $this->validateEmail($invoice);

    // Send order.
    if (empty($response['errors'])) {
      // Keep warnings.
      $warnings = $response['warnings'];
      $response = $this->webAPICommunicator->callApiFunction("invoices/invoice_add", $invoice);
      if (!empty($warnings)) {
        // Add local warnings and set status if it not worse then warnings.
        $response['warnings'] += $warnings;
        if (!isset($response['status']) || ($response['status'] !== static::Status_Errors && $response['status'] !== static::Status_Exception)) {
          $response['status'] = static::Status_Warnings;
        }
      }
    }

    return $response;
  }

  /**
   * Completes the invoice with default settings that do not depend on shop
   * specific data.
   *
   * @param array $invoice
   *
   * @return array
   *   The completed invoice.
   */
  protected function completeInvoice(array $invoice) {
    $invoiceSettings = $this->config->getInvoiceSettings();
    $this->addDefault($invoice['customer'], 'locationcode', $this->getLocationCode($invoice['customer']['countrycode']));
    $this->addDefault($invoice['customer'], 'overwriteifexists', $invoiceSettings['overwriteIfExists'] ? static::OverwriteIfExists_Yes : static::OverwriteIfExists_No);
    $this->addDefault($invoice['customer'], 'type', $invoiceSettings['defaultCustomerType']);
    $this->addDefault($invoice['customer']['invoice'], 'concept', static::Concept_No);
    $this->addDefault($invoice['customer']['invoice'], 'accountnumber', $invoiceSettings['defaultAccountNumber']);
    $this->addDefault($invoice['customer']['invoice'], 'costcenter', $invoiceSettings['defaultCostCenter']);
    if (isset($invoice['customer']['invoice']['paymentstatus'])
        && $invoice['customer']['invoice']['paymentstatus'] == static::PaymentStatus_Paid
        && $invoiceSettings['defaultInvoicePaidTemplate'] != 0) { // 0: use defaultInvoiceTemplate.
      $this->addDefault($invoice['customer']['invoice'], 'template', $invoiceSettings['defaultInvoicePaidTemplate']);
    }
    else {
      $this->addDefault($invoice['customer']['invoice'], 'template', $invoiceSettings['defaultInvoiceTemplate']);
    }

    // Add vat rate to 0-price products.
    $invoice = $this->addVatRateTo0PriceLines($invoice);

    // Add vattype.
    if (!isset($invoice['customer']['invoice']['vattype'])) {
      $invoice = $this->addVatType($invoice);
    }

    return $invoice;
  }

  /**
   * Helper method to add a default (without overwriting) value to the message.
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
   * Returns the Acumulus location code for a given country code.
   *
   * See https://apidoc.sielsystems.nl/content/invoice-add for more information
   * about the location code.
   *
   * This function will be deprecated once the locationcode has been removed
   * from the API.
   *
   * @param string $countryCode
   *   ISO 3166-1 alpha-2 country code
   *
   * @return int
   *   Location code
   */
  public function getLocationCode($countryCode) {
    if (empty($countryCode)) {
      $result = static::LocationCode_None;
    }
    else if ($this->isNl($countryCode)) {
      $result = static::LocationCode_NL;
    }
    elseif ($this->isEu($countryCode)) {
      $result = static::LocationCode_EU;
    }
    else {
      $result = static::LocationCode_RestOfWorld;
    }
    return $result;
  }

  /**
   * Completes lines with free items (price = 0) by giving them the tax rate
   * that appears the most in the other lines.
   *
   * @param array $invoice
   *
   * @return array
   *   Adapted invoice.
   */
  protected function addVatRateTo0PriceLines(array $invoice) {
    $lines = $invoice['customer']['invoice']['line'];
    $vatRates = array();
    foreach ($lines as $line) {
      if ($line['vatrate'] !== null) {
        if (isset($vatRates[$line['vatrate']])) {
          $vatRates[$line['vatrate']]++;
        }
        else {
          $vatRates[$line['vatrate']] = 1;
        }
      }
    }

    // Determine which vat rate occurs most.
    if (count($vatRates) > 0) {
      arsort($vatRates);
      reset($vatRates);
      list($vatRate) = each($vatRates);
    }
    else {
      $vatRate = -1;
    }

    foreach ($lines as &$line) {
      if ($line['vatrate'] === null) {
        $line['vatrate'] = $vatRate;
      }
    }

    $invoice['customer']['invoice']['line'] = $lines;
    return $invoice;
  }

  /**
   * Adds the vat type based on customer and invoice information.
   *
   * For more information see:
   * - https://apidoc.sielsystems.nl/content/invoice-add
   * - https://wiki.acumulus.nl/index.php?page=facturen-naar-het-buitenland
   *
   * @param array $invoice
   *
   * @return array
   */
  protected function addVatType(array $invoice) {
    $customer = $invoice['customer'];
    $invoicePart = $invoice['customer']['invoice'];
    // Set to static::VatType_MarginScheme if any line is:
    // - A used product with cost price.
    // - VAT rate is according to the margin, not the unitprice.
    // As we cannot check the latter with the available info here, we rely on
    // the setting useMargin.
    $invoiceSettings = $this->config->getInvoiceSettings();
    if ($invoiceSettings['useMargin']) {
      foreach ($invoicePart['line'] as $line) {
        if (!empty($line['costprice'])) {
          $invoice['customer']['invoice']['vattype'] = static::VatType_MarginScheme;
          return $invoice;
        }
      }
    }

    // Set to static::VatType_RestOfWorld if:
    // - Customer is outside the EU.
    // - VAT rate = 0 for all lines.
    if (!$this->isNl($customer['countrycode']) && !$this->isEu($customer['countrycode'])) {
      $vatIs0 = TRUE;
      foreach ($invoicePart['line'] as $line) {
        $vatIs0 = $vatIs0 && ($line['vatrate'] == 0 || $line['vatrate'] == -1);
      }
      if ($vatIs0) {
        $invoice['customer']['invoice']['vattype'] = static::VatType_RestOfWorld;
        return $invoice;
      }
    }

    // Set to static::VatType_EuReversed if:
    // - Customer is in EU.
    // - Customer is a company (VAT number provided).
    // - VAT rate = 0 for all lines.
    // - We should check the delivery address as well, but we don't as we can't.
    if ($this->isEu($customer['countrycode']) && !empty($customer['vatnumber'])) {
      $vatIs0 = TRUE;
      foreach ($invoicePart['line'] as $line) {
        $vatIs0 = $vatIs0 && ($line['vatrate'] == 0 || $line['vatrate'] == -1);
      }
      if ($vatIs0) {
        $invoice['customer']['invoice']['vattype'] = static::VatType_EuReversed;
        return $invoice;
      }
    }

    // Never set to static::VatType_NationalReversed via the API for web shops.

    // Set to static::VatType_National.
    $invoice['customer']['invoice']['vattype'] = static::VatType_National;
    return $invoice;
  }

  /**
   * Corrects the country code if necessary:
   * - If outside the EU, empty and move to the city field. This should be
   *   changed on the server, but for now we "correct" it here.
   * - Change UK to GB
   * - Change GR to EL. For now. In the future, when changed/corrected on the
   *   server, this should be reversed.
   *
   * For countries outside the EU, the countrycode field should be empty and the
   * country name should be added (in captitals) to the city instead.
   *
   * See https://apidoc.sielsystems.nl/content/invoice-add, tags city and
   * countrycode:
   *   city (non mandatory)
   *     City and optional country in capitals (Amsterdam NETHERLANDS).
   *   countrycode (non mandatory)
   *     Use international standard country code (ISO 3166-1) for countries in
   *     EU only (NL, DE etc). Defaults to NL when an empty or incorrect country
   *     code is supplied. Leave blank for countries outside EU-zone.
   *
   * This should be server side, but for now it is done client side.
   *
   * @param array $invoice
   *
   * @return array
   */
  protected function correctCountryCode(array $invoice) {
    if (!empty($invoice['customer']['countrycode'])) {
      if ($invoice['customer']['countrycode'] === 'GR') {
        $invoice['customer']['countrycode'] = 'EL';
      }
      if ($invoice['customer']['countrycode'] === 'UK') {
        $invoice['customer']['countrycode'] = 'GB';
      }
      if (isset($invoice['customer']['locationcode']) && $invoice['customer']['locationcode'] === static::LocationCode_RestOfWorld) {
        // Move countrycode to city.
        $country = $this->getCountryName($invoice['customer']['countrycode']);
        if (stripos($invoice['customer']['city'], $country) === false) {
          $invoice['customer']['city'] .= ' ' . strtoupper($country);
        }
        unset($invoice['customer']['countrycode']);
      }
    }
    return $invoice;
  }

  protected function getCountryName($code) {
    $countryNames = array(
      'AF' => 'Afghanistan',
      'AX' => 'Åland',
      'AL' => 'Albanië',
      'DZ' => 'Algerije',
      'VI' => 'Amerikaanse Maagdeneilanden',
      'AS' => 'Amerikaans-Samoa',
      'AD' => 'Andorra',
      'AO' => 'Angola',
      'AI' => 'Anguilla',
      'AQ' => 'Antarctica',
      'AG' => 'Antigua en Barbuda',
      'AR' => 'Argentinië',
      'AM' => 'Armenië',
      'AW' => 'Aruba',
      'AU' => 'Australië',
      'AZ' => 'Azerbeidzjan',
      'BS' => 'Bahama\'s',
      'BH' => 'Bahrein',
      'BD' => 'Bangladesh',
      'BB' => 'Barbados',
      'BE' => 'België',
      'BZ' => 'Belize',
      'BJ' => 'Benin',
      'BM' => 'Bermuda',
      'BT' => 'Bhutan',
      'BO' => 'Bolivia',
      'BQ' => 'Bonaire, Sint Eustatius en Saba',
      'BA' => 'Bosnië en Herzegovina',
      'BW' => 'Botswana',
      'BV' => 'Bouveteiland',
      'BR' => 'Brazilië',
      'VG' => 'Britse Maagdeneilanden',
      'IO' => 'Brits Indische Oceaanterritorium',
      'BN' => 'Brunei',
      'BG' => 'Bulgarije',
      'BF' => 'Burkina Faso',
      'BI' => 'Burundi',
      'KH' => 'Cambodja',
      'CA' => 'Canada',
      'CF' => 'Centraal-Afrikaanse Republiek',
      'CL' => 'Chili',
      'CN' => 'China',
      'CX' => 'Christmaseiland',
      'CC' => 'Cocoseilanden',
      'CO' => 'Colombia',
      'KM' => 'Comoren',
      'CG' => 'Congo-Brazzaville',
      'CD' => 'Congo-Kinshasa',
      'CK' => 'Cookeilanden',
      'CR' => 'Costa Rica',
      'CU' => 'Cuba',
      'CW' => 'Curaçao',
      'CY' => 'Cyprus',
      'DK' => 'Denemarken',
      'DJ' => 'Djibouti',
      'DM' => 'Dominica',
      'DO' => 'Dominicaanse Republiek',
      'DE' => 'Duitsland',
      'EC' => 'Ecuador',
      'EG' => 'Egypte',
      'SV' => 'El Salvador',
      'GQ' => 'Equatoriaal-Guinea',
      'ER' => 'Eritrea',
      'EE' => 'Estland',
      'ET' => 'Ethiopië',
      'FO' => 'Faeröer',
      'FK' => 'Falklandeilanden',
      'FJ' => 'Fiji',
      'PH' => 'Filipijnen',
      'FI' => 'Finland',
      'FR' => 'Frankrijk',
      'TF' => 'Franse Zuidelijke en Antarctische Gebieden',
      'GF' => 'Frans-Guyana',
      'PF' => 'Frans-Polynesië',
      'GA' => 'Gabon',
      'GM' => 'Gambia',
      'GE' => 'Georgië',
      'GH' => 'Ghana',
      'GI' => 'Gibraltar',
      'GD' => 'Grenada',
      'EL' => 'Griekenland',
      'GR' => 'Griekenland',
      'GL' => 'Groenland',
      'GP' => 'Guadeloupe',
      'GU' => 'Guam',
      'GT' => 'Guatemala',
      'GG' => 'Guernsey',
      'GN' => 'Guinee',
      'GW' => 'Guinee-Bissau',
      'GY' => 'Guyana',
      'HT' => 'Haïti',
      'HM' => 'Heard en McDonaldeilanden',
      'HN' => 'Honduras',
      'HU' => 'Hongarije',
      'HK' => 'Hongkong',
      'IE' => 'Ierland',
      'IS' => 'IJsland',
      'IN' => 'India',
      'ID' => 'Indonesië',
      'IQ' => 'Irak',
      'IR' => 'Iran',
      'IL' => 'Israël',
      'IT' => 'Italië',
      'CI' => 'Ivoorkust',
      'JM' => 'Jamaica',
      'JP' => 'Japan',
      'YE' => 'Jemen',
      'JE' => 'Jersey',
      'JO' => 'Jordanië',
      'KY' => 'Kaaimaneilanden',
      'CV' => 'Kaapverdië',
      'CM' => 'Kameroen',
      'KZ' => 'Kazachstan',
      'KE' => 'Kenia',
      'KG' => 'Kirgizië',
      'KI' => 'Kiribati',
      'UM' => 'Kleine Pacifische eilanden van de Verenigde Staten',
      'KW' => 'Koeweit',
      'HR' => 'Kroatië',
      'LA' => 'Laos',
      'LS' => 'Lesotho',
      'LV' => 'Letland',
      'LB' => 'Libanon',
      'LR' => 'Liberia',
      'LY' => 'Libië',
      'LI' => 'Liechtenstein',
      'LT' => 'Litouwen',
      'LU' => 'Luxemburg',
      'MO' => 'Macau',
      'MK' => 'Macedonië',
      'MG' => 'Madagaskar',
      'MW' => 'Malawi',
      'MV' => 'Maldiven',
      'MY' => 'Maleisië',
      'ML' => 'Mali',
      'MT' => 'Malta',
      'IM' => 'Man',
      'MA' => 'Marokko',
      'MH' => 'Marshalleilanden',
      'MQ' => 'Martinique',
      'MR' => 'Mauritanië',
      'MU' => 'Mauritius',
      'YT' => 'Mayotte',
      'MX' => 'Mexico',
      'FM' => 'Micronesia',
      'MD' => 'Moldavië',
      'MC' => 'Monaco',
      'MN' => 'Mongolië',
      'ME' => 'Montenegro',
      'MS' => 'Montserrat',
      'MZ' => 'Mozambique',
      'MM' => 'Myanmar',
      'NA' => 'Namibië',
      'NR' => 'Nauru',
      'NL' => 'Nederland',
      'NP' => 'Nepal',
      'NI' => 'Nicaragua',
      'NC' => 'Nieuw-Caledonië',
      'NZ' => 'Nieuw-Zeeland',
      'NE' => 'Niger',
      'NG' => 'Nigeria',
      'NU' => 'Niue',
      'MP' => 'Noordelijke Marianen',
      'KP' => 'Noord-Korea',
      "NO" => 'Noorwegen',
      'NF' => 'Norfolk',
      'UG' => 'Oeganda',
      'UA' => 'Oekraïne',
      'UZ' => 'Oezbekistan',
      'OM' => 'Oman',
      'AT' => 'Oostenrijk',
      'TL' => 'Oost-Timor',
      'PK' => 'Pakistan',
      'PW' => 'Palau',
      'PS' => 'Palestina',
      'PA' => 'Panama',
      'PG' => 'Papoea-Nieuw-Guinea',
      'PY' => 'Paraguay',
      'PE' => 'Peru',
      'PN' => 'Pitcairneilanden',
      'PL' => 'Polen',
      'PT' => 'Portugal',
      'PR' => 'Puerto Rico',
      'QA' => 'Qatar',
      'RE' => 'Réunion',
      'RO' => 'Roemenië',
      'RU' => 'Rusland',
      'RW' => 'Rwanda',
      'BL' => 'Saint-Barthélemy',
      'KN' => 'Saint Kitts en Nevis',
      'LC' => 'Saint Lucia',
      'PM' => 'Saint-Pierre en Miquelon',
      'VC' => 'Saint Vincent en de Grenadines',
      'SB' => 'Salomonseilanden',
      'WS' => 'Samoa',
      'SM' => 'San Marino',
      'SA' => 'Saoedi-Arabië',
      'ST' => 'Sao Tomé en Principe',
      'SN' => 'Senegal',
      'RS' => 'Servië',
      'SC' => 'Seychellen',
      'SL' => 'Sierra Leone',
      'SG' => 'Singapore',
      'SH' => 'Sint-Helena, Ascension en Tristan da Cunha',
      'MF' => 'Sint-Maarten',
      'SX' => 'Sint Maarten',
      'SI' => 'Slovenië',
      'SK' => 'Slowakije',
      'SD' => 'Soedan',
      'SO' => 'Somalië',
      'ES' => 'Spanje',
      'SJ' => 'Spitsbergen en Jan Mayen',
      'LK' => 'Sri Lanka',
      'SR' => 'Suriname',
      'SZ' => 'Swaziland',
      'SY' => 'Syrië',
      'TJ' => 'Tadzjikistan',
      'TW' => 'Taiwan',
      'TZ' => 'Tanzania',
      'TH' => 'Thailand',
      'TG' => 'Togo',
      'TK' => 'Tokelau',
      'TO' => 'Tonga',
      'TT' => 'Trinidad en Tobago',
      'TD' => 'Tsjaad',
      'CZ' => 'Tsjechië',
      'TN' => 'Tunesië',
      'TR' => 'Turkije',
      'TM' => 'Turkmenistan',
      'TC' => 'Turks- en Caicoseilanden',
      'TV' => 'Tuvalu',
      'UY' => 'Uruguay',
      'VU' => 'Vanuatu',
      'VA' => 'Vaticaanstad',
      'VE' => 'Venezuela',
      'AE' => 'Verenigde Arabische Emiraten',
      'US' => 'Verenigde Staten',
      'GB' => 'Verenigd Koninkrijk',
      'UK' => 'Verenigd Koninkrijk',
      'VN' => 'Vietnam',
      'WF' => 'Wallis en Futuna',
      'EH' => 'Westelijke Sahara',
      'BY' => 'Wit-Rusland',
      'ZM' => 'Zambia',
      'ZW' => 'Zimbabwe',
      'ZA' => 'Zuid-Afrika',
      'GS' => 'Zuid-Georgia en de Zuidelijke Sandwicheilanden',
      'KR' => 'Zuid-Korea',
      'SS' => 'Zuid-Soedan',
      'SE' => 'Zweden',
      'CH' => 'Zwitserland',
    );
    return isset($countryNames[$code]) ? $countryNames[$code] : $code;
  }

  /**
   * Adds an emailaspdf section if enabled.
   *
   * @param array $invoice
   * @param string $orderId
   *
   * @return array
   */
  protected function addEmailAsPdf(array $invoice, $orderId) {
    $emailAsPdfSettings = $this->config->getEmailAsPdfSettings();
    if ($emailAsPdfSettings['emailAsPdf'] && !empty($invoice['customer']['email'])) {
      $invoice['customer']['invoice']['emailaspdf'] = array(
        'emailto' => $invoice['customer']['email'],
        'emailbcc' => $emailAsPdfSettings['emailBcc'],
        'emailfrom' => $emailAsPdfSettings['emailFrom'],
        'subject' => strtr($emailAsPdfSettings['subject'], array(
          '[#b]' => $orderId,
          '[#f]' => isset($invoice['customer']['invoice']['number']) ? $invoice['customer']['invoice']['number'] : '',
        )),
        'confirmreading' => $emailAsPdfSettings['confirmReading'] ? static::ConfirmReading_Yes : static::ConfirmReading_No,
      );
    }
    return $invoice;
  }

  /**
   * Anonymize customer if set so. We don't do this for business clients, only
   * consumers.
   *
   * @param array $invoice
   *
   * @return array
   */
  protected function fictitiousClient(array $invoice) {
    $invoiceSettings = $this->config->getInvoiceSettings();
    if (!$invoiceSettings['sendCustomer'] && empty($invoice['customer']['companyname1']) && empty($invoice['customer']['vatnumber'])) {
      unset($invoice['customer']['type']);
      unset($invoice['customer']['companyname1']);
      unset($invoice['customer']['companyname2']);
      unset($invoice['customer']['fullname']);
      unset($invoice['customer']['salutation']);
      unset($invoice['customer']['address1']);
      unset($invoice['customer']['address2']);
      unset($invoice['customer']['postalcode']);
      unset($invoice['customer']['city']);
      unset($invoice['customer']['locationcode']);
      unset($invoice['customer']['countrycode']);
      unset($invoice['customer']['vatnumber']);
      unset($invoice['customer']['telephone']);
      unset($invoice['customer']['fax']);
      unset($invoice['customer']['bankaccountnumber']);
      unset($invoice['customer']['mark']);
      $invoice['customer']['email'] = $invoiceSettings['genericCustomerEmail'];
      $invoice['customer']['overwriteifexists'] = 0;
    }
    return $invoice;
  }

  /**
   * Validates the email address of the invoice.
   *
   * The email address may not be empty but may be left out though.
   *
   * @param array $invoice
   *   The invoice to validate.
   *
   * @return array
   *   The invoice, possibly modified.
   */
  protected function validateEmail(array $invoice) {
    // Check email address.
    if (empty($invoice['customer']['email'])) {
      unset($invoice['customer']['email']);
    }

    return $invoice;
  }

  /**
   * Validates and corrects the VAT rates in the invoice.
   *
   * Validation is performed using the vat info lookup API call:
   * - Dutch vat rates on the given order date.
   * - Foreign vat rates for invoices of vattype = 6.
   *
   * Correction is done when there are vat rates that are near to the incorrect
   * vat rate, this should correct rounding errors.
   *
   * @param array $invoice
   *   The invoice to validate.
   * @param string $orderId
   *   The order id of the invoice to use in error messages.
   * @param array $response
   *   The response structure where errors and warnings can be added.
   *
   * @return array
   *   The invoice, possibly modified.
   */
  protected function validateVatRates(array $invoice, $orderId, array &$response) {
    // Determine which vat rates to get.
    $date = !empty($invoice['customer']['invoice']['issuedate']) ? $invoice['customer']['invoice']['issuedate'] : date('Y-m-d');
    $countryCode = '';
    $vatType = $invoice['customer']['invoice']['vattype'];
    switch ($vatType) {
      case static::VatType_National;
      case static::VatType_MarginScheme:
        // We expect Dutch vat rates, even if the client is abroad.
        $countryCode = 'nl';
        break;
      case static::VatType_ForeignVat:
        // We expect foreign vat rates, but the country code should be set.
        $countryCode = isset($invoice['customer']['countrycode']) ? $invoice['customer']['countrycode'] : '';
        break;
      case static::VatType_NationalReversed:
      case static::VatType_EuReversed:
      case static::VatType_RestOfWorld:
        // We only expect 0 or -1 as vat rates.
      default:
        break;
    }

    // Get allowed vat rates for this invoice.
    // Always allow 0 and -1.
    $allowedVatRates = array(
      array('vattype' => 'vat free', 'vatrate' => -1),
      array('vattype' => 'no vat', 'vatrate' => 0),
    );
    if (!empty($countryCode)) {
      $vatInfo = $this->getVatInfo($countryCode, $date);
      if (isset($vatInfo['vatinfo'])) {
        $allowedVatRates = array_merge($allowedVatRates, $vatInfo['vatinfo']);
      }
    }

    // Check that all vat rates are correct or, if not, try to correct them.
    foreach ($invoice['customer']['invoice']['line'] as &$line) {
      if (isset($line['vatrate']) && !$this->isAllowedVatRate($line['vatrate'], $allowedVatRates)) {
        $correctedVatRate = $this->getNearVatRate($line['vatrate'], $allowedVatRates);
        if ($correctedVatRate !== null) {
          // Add message: incorrect vat rate, corrected to near vat rate.
          $response['warnings'][] = array(
            'code' => 'Order',
            'codetag' => !empty($invoice['customer']['invoice']['number']) ? $invoice['customer']['invoice']['number'] : $orderId,
            'message' => sprintf($this->config->t('message_warning_incorrect_vat_corrected'), $line['vatrate'], $correctedVatRate),
          );
          if (!isset($response['status']) || ($response['status'] !== static::Status_Errors && $response['status'] !== static::Status_Exception)) {
            $response['status'] = static::Status_Warnings;
          }
          $line['vatrate'] = $correctedVatRate;
        }
        else {
          // Add message: incorrect vat rate, could not correct.
          $response['warnings'][] = array(
            'code' => 'Order',
            'codetag' => !empty($invoice['customer']['invoice']['number']) ? $invoice['customer']['invoice']['number'] : $orderId,
            'message' => sprintf($this->config->t('message_warning_incorrect_vat_not_corrected'), $line['vatrate']),
          );
          if (!isset($response['status']) || ($response['status'] !== static::Status_Errors && $response['status'] !== static::Status_Exception)) {
            $response['status'] = static::Status_Warnings;
          }
        }
      }
    }
    return $invoice;
  }

  /**
   * Tries to return a vat rate form a list of allowed vat rates that is within
   * 1 percent point of a given vat rate.
   *
   * If there are 0 or more than 1 "near" vat rates, null is returned.
   *
   * @param float|string $vatRate
   * @param array $allowedVatRates
   *
   * @return float|string|null
   *   The (single) near vat rate or null if there is no or more than 1 near vat
   *   rate. The float value may actually be returned as a string.
   */
  protected function getNearVatRate($vatRate, array $allowedVatRates) {
    $nearestVatRate = null;
    foreach ($allowedVatRates as $allowedVatRate) {
      if ($this->floatsAreEqual($vatRate, $allowedVatRate['vatrate'], 1.0)) {
        if ($nearestVatRate === null) {
          // We found a near vat rate: use it.
          $nearestVatRate = $allowedVatRate['vatrate'];
        }
        else if ($this->floatsAreEqual($nearestVatRate, $allowedVatRate['vatrate'])) {
          // We found a 2nd near vat rate (that is different): do not correct.
          $nearestVatRate = null;
          break;
        }
      }
    }
    return $nearestVatRate;
  }

  /**
   * @param float|string $vatRate
   * @param array $allowedVatRates
   *
   * @return bool
   *
   */
  protected function isAllowedVatRate($vatRate, array $allowedVatRates) {
    foreach ($allowedVatRates as $allowedVatRate) {
      if ($this->floatsAreEqual($vatRate, $allowedVatRate['vatrate'])) {
        return true;
      }
    }
    return false;
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
