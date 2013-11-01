<?php
/**
 * @file Definition of Siel\Acumulus\WebAPI.
 */

namespace Siel\Acumulus;

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
  /** @var \Siel\Acumulus\ConfigInterface */
  protected $config;

  /** @var \Siel\Acumulus\WebAPICommunication */
  protected $webAPICommunicator;

  /**
   * Constructor.
   *
   * @param ConfigInterface $config
   */
  public function __construct(ConfigInterface $config) {
    $this->config = $config;
    if (!$this->config->getLocal()) {
      $this->webAPICommunicator = new WebAPICommunication($config);
    }
    else {
      require_once(dirname(__FILE__) . '/../AcumulusTest/WebAPICommunicationTest.php');
      $this->webAPICommunicator = new \Siel\AcumulusTest\WebAPICommunicationTest($config);
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
    if(!extension_loaded('curl')) {
      $result['errors'][] = array(
        'code' => 'curl',
        'codetag' => '',
        'message' => 'Voor het gebruik van deze extensie dient de CURL extensie actief te zijn op uw server.'
      );
    }
    if($this->config->getOutputFormat() === 'xml' && !extension_loaded('simplexml')) {
      $result['errors'][] = array(
        'code' => 'SimpleXML',
        'codetag' => '',
        'message' => 'Voor het gebruik van deze extensie en het output format XML, dient de SimpleXML extensie actief te zijn op uw server.'
      );
    }
    if(!extension_loaded('dom')) {
      $result['errors'][] = array(
        'code' => 'DOM',
        'codetag' => '',
        'message' => 'Voor het gebruik van deze extensie dient de DOM extensie actief te zijn op uw server.'
      );
    }

    return $result;
  }

  /**
   * @param int $status
   *
   * @return string
   */
  public function getStatusText($status) {
    switch ($status) {
      case 0:
        return 'Success. Without warnings';
      case 1:
        return 'Failed. Errors found';
      case 2:
        return 'Success. With any warnings';
      case 3:
        return 'Exception. Please contact Acumulus technical support';
      default:
        return "Onbekende status code $status";
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
    // EFTA countries are not part of this list because as regarding invoicing
    // it is considered outside of the EU.
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
      //'NL', // Outside the Netherlands.
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
      $result = 1;
    }
    elseif ($this->isEu($countryCode)) {
      $result = 2;
    }
    else {
      $result = 3;
    }
    return $result;
  }


  /**
   * Extracts the vat type based on customer and invoice information.
   *
   * NOTES:
   * For vat type 3:
   * - We should check the delivery address as well.
   * - Actually, the invoice should represent what the customer paid, so if that
   *   included TAX, the invoice should say so, unless payments and payment
   *   checking is done in Acumulus and not in the web shop (not likely).
   *
   * @param array $customer
   * @param array $invoice
   *
   * @return int
   *   The vat type as defined on
   *   https://apidoc.sielsystems.nl/content/invoice-add.
   */
  public function getVatType(array $customer, array $invoice) {
    // Return 5 (margin scheme) if any line is a used product with cost price.
    foreach ($invoice['line'] as $line) {
      if (!empty($line['costprice'])) {
        return 5;
      }
    }

    // Return 4 (outside EU) if outside the EU.
    if (!$this->isNl($customer['countrycode']) && !$this->isEu($customer['countrycode'])) {
      return 4;
    }

    // Return 3 (Reverse-charging VAT) if customer is in EU and is a company.
    if ($this->isEu($customer['countrycode']) && !empty($customer['vatnumber'])) {
      return 3;
    }

    // Never return 2 (national reverse-charging VAT) via the API for web shops.

    // Return 1 (Normal invoice with VAT).
    return 1;
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
   *
   * @param array $invoice
   *   The invoice to add.
   * @param string $orderId
   *   The order id, only used for error reporting.
   * @param bool $addDefaults
   *   Whether to add default values from the configuration to empty or absent
   *   fields:
   *   - type (type of customer)
   *   - accountnumber (own account number to use)
   *   - costheading (cost heading to use)
   *   - template (invoice template to use)
   *
   * @return array
   */
  public function addInvoice(array $invoice, $orderId = '', $addDefaults = true) {
    if ($addDefaults) {
      $invoiceSettings = $this->config->getInvoiceSettings();
      $this->addDefault($invoice['customer'], 'type', $invoiceSettings['defaultCustomerType']);
      $this->addDefault($invoice['customer']['invoice'], 'accountnumber', $invoiceSettings['defaultAccountNumber']);
      $this->addDefault($invoice['customer']['invoice'], 'costheading', $invoiceSettings['defaultCostHeading']);
      $this->addDefault($invoice['customer']['invoice'], 'template', $invoiceSettings['defaultInvoiceTemplate']);
    }

    // Validate order (client side).
    $response = $this->validateInvoice($invoice, $orderId);
    if (empty($response['errors'])) {
      // Send order.
      $response = $this->webAPICommunicator->call("invoices/invoice_add", $invoice);
    }

    return $response;
  }

  /**
   * Helper method to add a default (without overwriting) value ot the message.
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
   * Validates the invoice.
   *
   * Checks that are performed:
   * - 19% and 21% VAT: those are not both allowed in 1 order.
   *
   * @param array $invoice
   * @param string $orderId
   *
   * @return array
   */
  protected function validateInvoice(array $invoice, $orderId) {
    $response = array(
      'errors' => array(),
      'warnings' => array(),
      'status' => 0,
    );

    // Check if both 19% and 21% vat rates occur.
    $has19 = false;
    $has21 = false;
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
        'message' => 'Deze order heeft zowel 19% als 21% BTW percentages. U dient deze factuur handmatig aan te maken in Acumulus.',
      );
      $result['status'] = 1;
    }

    return $response;
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
   * see https://apidoc.sielsystems.nl/content/picklist-accounts-bankrekeningen.
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
   * - vattypes: an array of available invoice templates, an array with keys:
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
    $response =  $this->webAPICommunicator->call("picklists/picklist_$plural", array());
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
}
