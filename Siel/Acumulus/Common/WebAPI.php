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

  const LocationCode_NL = 1;
  const LocationCode_EU = 2;
  const LocationCode_RestOfWorld = 3;

  const VatType_National = 1;
  const VatType_NationalReversed = 2;
  const VatType_EuReversed = 3;
  const VatType_RestOfWorld = 4;
  const VatType_MarginScheme = 5;

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
   *
   * @return array
   *   An array with textual messages tha can be used to inform the user.
   */
  public function resultToMessages(array $result) {
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

    if (!empty($messages) || $this->config->getDebug() != ConfigInterface::Debug_None) {
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
      case self::Status_Success:
        return $this->config->t('message_response_0');
      case self::Status_Errors:
        return $this->config->t('message_response_1');
      case self::Status_Warnings:
        return $this->config->t('message_response_2');
      case self::Status_Exception:
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
    // http://epp.eurostat.ec.europa.eu/statistics_explained/index.php/Glossary:Country_codes
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
      'EL',
      'ES',
      'FR',
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
      'UK',
    );
    return in_array(strtoupper($countryCode), $euCountryCodes);
  }

  /**
   * Retrieves a list of accounts.
   *
   * Besides the general response structure, the actual result of this call is
   * returned under the following key:
   * - accounts: an array of account information, being an array with keys:
   *   - accountid
   *   - accountnumber
   *   - accountdescription
   *
   * See https://apidoc.sielsystems.nl/content/picklist-accounts-bankrekeningen.
   *
   * @return array
   *   A keyed array.
   */
  public function getPicklistAccounts() {
    return $this->getPicklist('account');
  }

  /**
   * Retrieves a list of contact types.
   *
   * Besides the general response structure, the actual result of this call is
   * returned under the following key:
   * - contacttypes: an array of contact type information, an array with keys:
   *   - contacttypeid
   *   - contacttypename
   *
   * see https://apidoc.sielsystems.nl/content/picklist-contacttypes-contactsoorten.
   *
   * @return array
   *   A keyed array.
   */
  public function getPicklistContactTypes() {
    return $this->getPicklist('contacttype');
  }

  /**
   * Retrieves a list of cost centers.
   *
   * Besides the general response structure, the actual result of this call is
   * returned under the following key:
   * - costcenters: an array of cost center information, an array with keys:
   *   - costcenterid
   *   - costcentername
   *
   * see https://apidoc.sielsystems.nl/content/picklist-costcenters-kostenplaatsen.
   *
   * @return array
   *   A keyed array.
   */
  public function getPicklistCostCenters() {
    return $this->getPicklist('costcenter');
  }

  /**
   * Retrieves a list of cost types.
   *
   * Besides the general response structure, the actual result of this call is
   * returned under the following key:
   * - costtypes: an array of cost center information, an array with keys:
   *   - costtypeid
   *   - costtypename
   *
   * see https://apidoc.sielsystems.nl/content/picklist-costtypes-kostensoorten.
   *
   * @return array
   *   A keyed array.
   */
  public function getPicklistCostTypes() {
    return $this->getPicklist('costtype');
  }

  /**
   * Retrieves a list of cost types.
   *
   * Besides the general response structure, the actual result of this call is
   * returned under the following key:
   * - invoicetemplates: an array of available invoice templates, an array with keys:
   *   - invoicetemplateid
   *   - invoicetemplatename
   *
   * see https://apidoc.sielsystems.nl/content/picklist-invoice-templates-factuursjablonen.
   *
   * @return array
   *   A keyed array.
   */
  public function getPicklistInvoiceTemplates() {
    return $this->getPicklist('invoicetemplate');
  }

  /**
   * Retrieves a list of VAT types.
   *
   * Besides the general response structure, the actual result of this call is
   * returned under the following key:
   * - vattypes: an array of available vat types, an array with keys:
   *   - vattypeid
   *   - vattypename
   *
   * see https://apidoc.sielsystems.nl/content/picklist-vattypes-btw-groepen.
   *
   * @return array
   *   A keyed array.
   */
  public function getPicklistVATTypes() {
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
   *   The given picklist as a keyed array.
   */
  protected function getPicklist($picklist) {
    $plural = $picklist . 's';
    $response = $this->webAPICommunicator->callApiFunction("picklists/picklist_$plural", array());
    // Simplify result: remove indirection.
    $plural = $picklist . 's';
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
   * Adds an invoice to Acumulus.
   *
   * Besides the general response structure, the actual result of this call is
   * returned under the following key:
   * - invoice: an array of information about the created invoice, being an
   *   array with keys:
   *   - invoicenumber
   *   - token
   *   - entryid
   * See https://apidoc.sielsystems.nl/content/invoice-add.
   *
   * @param array $invoice
   *   The invoice to add.
   * @param string $orderId
   *   The order id, only used for error reporting.
   *
   * @return array
   *
   * @todo: remove setting locationcode and overwriteifexists from shop specific code (done: ps)
   */
  public function invoiceAdd(array $invoice, $orderId = '') {
    $invoice = $this->completeInvoice($invoice);

    // Correct countrycode (if in rest of world).
    $invoice = $this->correctCountryCode($invoice);

    // Change to fictitious client (if set so).
    $invoice = $this->fictitiousClient($invoice);

    // Validate order (client side).
    $response = $this->validateInvoice($invoice, $orderId);
    if (empty($response['errors'])) {
      // Send order.
      $response = $this->webAPICommunicator->callApiFunction("invoices/invoice_add", $invoice);
    }

    return $response;
  }

  /**
   * @param array $invoice
   *
   * @return array
   */
  protected function completeInvoice(array $invoice) {
    $invoiceSettings = $this->config->getInvoiceSettings();
    $this->addDefault($invoice['customer'], 'locationcode', $this->getLocationCode($invoice['customer']['countrycode']));
    $this->addDefault($invoice['customer'], 'overwriteifexists', $invoiceSettings['overwriteIfExists'] ? WebAPI::OverwriteIfExists_Yes : WebAPI::OverwriteIfExists_No);
    $this->addDefault($invoice['customer'], 'type', $invoiceSettings['defaultCustomerType']);
    $this->addDefault($invoice['customer']['invoice'], 'accountnumber', $invoiceSettings['defaultAccountNumber']);
    $this->addDefault($invoice['customer']['invoice'], 'costcenter', $invoiceSettings['defaultCostCenter']);
    $this->addDefault($invoice['customer']['invoice'], 'template', $invoiceSettings['defaultInvoiceTemplate']);

    // Add vattype.
    $invoice = $this->addVatType($invoice);

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
    if ($this->isNl($countryCode)) {
      $result = self::LocationCode_NL;
    }
    elseif ($this->isEu($countryCode)) {
      $result = self::LocationCode_EU;
    }
    else {
      $result = self::LocationCode_RestOfWorld;
    }
    return $result;
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
    // Set to self::VatType_MarginScheme if any line is:
    // - A used product with cost price.
    // - VAT rate is according to the margin, not the unitprice.
    // As we cannot check the latter with the available info here, we rely on
    // the setting useMargin.
    $invoiceSettings = $this->config->getInvoiceSettings();
    if ($invoiceSettings['useMargin']) {
      foreach ($invoicePart['line'] as $line) {
        if (!empty($line['costprice'])) {
          $invoice['customer']['invoice']['vattype'] = self::VatType_MarginScheme;
          return $invoice;
        }
      }
    }

    // Set to self::VatType_RestOfWorld if:
    // - Customer is outside the EU.
    // - VAT rate = 0 for all lines.
    if (!$this->isNl($customer['countrycode']) && !$this->isEu($customer['countrycode'])) {
      $vatIs0 = TRUE;
      foreach ($invoicePart['line'] as $line) {
        $vatIs0 = $vatIs0 && $line['vatrate'] == 0;
      }
      if ($vatIs0) {
        $invoice['customer']['invoice']['vattype'] = self::VatType_RestOfWorld;
        return $invoice;
      }
    }

    // Set to self::VatType_EuReversed if:
    // - Customer is in EU.
    // - Customer is a company (VAT number provided).
    // - VAT rate = 0 for all lines.
    // - We should check the delivery address as well, but we don't as we can't.
    if ($this->isEu($customer['countrycode']) && !empty($customer['vatnumber'])) {
      $vatIs0 = TRUE;
      foreach ($invoicePart['line'] as $line) {
        $vatIs0 = $vatIs0 && $line['vatrate'] == 0;
      }
      if ($vatIs0) {
        $invoice['customer']['invoice']['vattype'] = self::VatType_EuReversed;
        return $invoice;
      }
    }

    // Never set to self::VatType_NationalReversed via the API for web shops.

    // Set to self::VatType_National.
    $invoice['customer']['invoice']['vattype'] = self::VatType_National;
    return $invoice;
  }

  /**
   * Correct country codes if outside th EU.
   *
   * For countries outside the EU, the countycode should be empty and the
   * country name should be added to the city instead.
   *
   * See https://apidoc.sielsystems.nl/content/invoice-add, tags city and
   * countrycode:
   * city non mandatory
   *   City and optional country in capitals (Amsterdam NETHERLANDS).
   * countrycode non mandatory
   *   Use international standard country code (ISO 3166-1) for countries in EU
   *   only (NL, DE etc). Defaults to NL when an empty or incorrect country code
   *   is supplied. Leave blank for countries outside EU-zone.
   *
   * This should be server side, but for now it is done client side.
   *
   * @param array $invoice
   *
   * @return array
   */
  protected function correctCountryCode(array $invoice) {
    if (isset($invoice['customer']['locationcode']) && $invoice['customer']['locationcode'] === self::LocationCode_RestOfWorld) {
      if (!empty($invoice['customer']['countrycode'])) {
        // Move countrycode to city.
        $country = $this->getCountryName($invoice['customer']['countrycode']);
        if (stripos($invoice['customer']['city'], $country) === FALSE) {
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
      'US' => 'VerenigdeStaten',
      'GB' => 'VerenigdKoninkrijk',
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
   * Validates the invoice.
   *
   * Checks that are performed:
   * - email address may not be empty, may be left out though.
   * - 19% and 21% VAT: those are not both allowed in 1 order.
   *
   * @param array $invoice
   * @param string $orderId
   *
   * @return array
   */
  protected function validateInvoice(array &$invoice, $orderId) {
    $response = array(
      'errors' => array(),
      'warnings' => array(),
      'status' => self::Status_Success,
    );

    // Check email address.
    if (empty($invoice['customer']['email'])) {
      unset($invoice['customer']['email']);
    }

    // Check if both 19% and 21% vat rates occur.
    $has19 = FALSE;
    $has21 = FALSE;
    foreach ($invoice['customer']['invoice']['line'] as $line) {
      if (isset($line['vatrate'])) {
        $has19 = $has19 && $line['vatrate'] == 19;
        $has21 = $has21 && $line['vatrate'] == 21;
      }
    }
    if ($has19 && $has21) {
      $result['errors'][] = array(
        'code' => 'Order',
        'codetag' => !empty($invoice['customer']['invoice']['number']) ? $invoice['customer']['invoice']['number'] : $orderId,
        'message' => $this->config->t('message_error_vat19and21'),
      );
      $result['status'] = self::Status_Errors;
    }

    return $response;
  }

}
