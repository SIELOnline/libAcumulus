<?php
/**
 * @file Definition of Siel\Acumulus\WebAPI.
 */

namespace Siel\Acumulus;

/**
 * WebAPI provides an easy interface towards the different API calls of the
 * Acumulus WebAPI.
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

  public function __construct(ConfigInterface $config) {
    $this->config = $config;
    $this->webAPICommunicator = new WebAPICommunication($config);
  }

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
   * Returns the Acumulus location code for a given country code.
   *
   * See https://apidoc.sielsystems.nl/content/invoice-add for more information
   * about the location code.
   *
   * @param string $countryCode
   *   ISO 3166-1 alpha-2 country code
   *
   * @return int
   *   Location code
   */
  public function getLocationCode($countryCode) {
    // http://epp.eurostat.ec.europa.eu/statistics_explained/index.php/Glossary:Country_codes
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
      'NL',
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
    $countryCode = strtoupper($countryCode);
    if ($countryCode === 'NL') {
      $result = 1;
    }
    elseif (in_array($countryCode, $euCountryCodes)) {
      $result = 2;
    }
    else {
      $result = 3;
    }
    return $result;
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
      if (empty($invoice['customer']['type'])) {
        $invoice['customer']['type'] = $invoiceSettings['defaultCustomerType'];
      }
      if (empty($invoice['customer']['invoice']['accountnumber'])) {
        $invoice['customer']['invoice']['accountnumber'] = $invoiceSettings['defaultAccountNumber'];
      }
      if (empty($invoice['customer']['invoice']['costheading'])) {
        $invoice['customer']['invoice']['costheading'] = $invoiceSettings['defaultCostHeading'];
      }
      if (empty($invoice['customer']['invoice']['template'])) {
        $invoice['customer']['invoice']['template'] = $invoiceSettings['defaultInvoiceTemplate'];
      }
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
