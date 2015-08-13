<?php
namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\Countries;
use Siel\Acumulus\Helpers\TranslatorInterface;
use Siel\Acumulus\Web\Service;

/**
 * The invoice completor class provides functionality to correct and complete
 * invoices before sending them to Acumulus.
 *
 * This class:
 * - Add defaults to shop independent invoice fields, based on the invoice
 *   configuration or other fields.
 * - Adds vat rates to 0 price lines (with a 0 price and thus 0 vat, not all
 *   web shops can fill in a vat rate).
 * - Adds the vat type based on inspection of the customer and invoice fields.
 * - Corrects the country code if it is a country code outside the EU. (This is
 *   a "glitch" in the API that expects the country code to be empty for non-EU
 *   countries.)
 * - Adds email as pdf fields based on the Invoice configuration values.
 * - Changes the customer into a fictitious client if set so in the config.
 * - Validates (and correct rounding errors of) vat rates using the VAT rate
 *   lookup webservice call.
 * - Validates the email address: the webservice does not allow an empty email
 *   address (but does allow a non provided email address).
 *
 * @package Siel\Acumulus
 */
class Completor {

  /** @var \Siel\Acumulus\Invoice\ConfigInterface */
  protected $config;

  /** @var \Siel\Acumulus\Helpers\TranslatorInterface */
  protected $translator;

  /** @var \Siel\Acumulus\Helpers\Countries */
  protected $countries;

  /** @var array */
  protected $messages;

  /** @var array */
  protected $invoice;

  /** @var Source */
  protected $source;

  /** @var \Siel\Acumulus\Invoice\CompletorInvoiceLines */
  protected $invoiceLineCompletor = null;

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

    $this->countries = new Countries();

    if ($this->invoiceLineCompletor === null) {
      $this->invoiceLineCompletor = new CompletorInvoiceLines($config, $translator, $service);
    }
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

    $this->completeInvoice();
    $this->invoice = $this->invoiceLineCompletor->complete($this->invoice, $this->source, $this->messages);
    $this->completeInvoiceAfterLineCompletion();

    return $this->invoice;
  }

  /**
   * Completes the invoice with default settings that do not depend on shop
   * specific data.
   */
  protected function completeInvoice() {
    $this->correctCityAndCountryCode();
    $this->fictitiousClient();
    $this->validateEmail();
  }

  /**
   * Completes the invoice with settings or behavior that might depend on
   * the fact that the invoice lines have been completed.
   */
  protected function completeInvoiceAfterLineCompletion() {
    $this->removeEmptyShipping();
  }

  /**
   * Corrects the city and country code if necessary:
   * - If outside the EU, empty the country code
   * - if outside the Netherlands, add the country name to the city field.
   *
   * This should be changed on the server, but for now we "correct" it here.
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
   */
  protected function correctCityAndCountryCode() {
    if (!$this->isNl()) {
      // Add country name to city.
      $country = $this->getCountryName();
      if (stripos($this->invoice['customer']['city'], $country) === false) {
        $this->invoice['customer']['city'] .= ' ' . strtoupper($country);
      }
    }
  }

  /**
   * Wrapper around Countries::isNl().
   *
   * @return bool
   */
  protected function isNl() {
    return empty($this->invoice['customer']['countrycode']) || $this->countries->isNl($this->invoice['customer']['countrycode']);
  }

  /**
   * Wrapper around Countries::getCountryName().
   *
   * @return string
   */
  protected function getCountryName() {
    return $this->countries->getCountryName(!empty($this->invoice['customer']['countrycode']) ? $this->invoice['customer']['countrycode'] : 'nl');
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
   * The email address may not be empty but may be left out though.
   */
  protected function validateEmail() {
    // Check email address.
    if (empty($this->invoice['customer']['email'])) {
      unset($this->invoice['customer']['email']);
    }
  }

  /**
   * Removes an empty shipping line (if so configured).
   */
  protected function removeEmptyShipping() {
    $invoiceSettings = $this->config->getInvoiceSettings();
    if ($invoiceSettings['removeEmptyShipping']) {
      $this->invoice['customer']['invoice']['line'] = array_filter($this->invoice['customer']['invoice']['line'],
        function ($line) {
          return $line['meta-line-type'] !== Creator::LineType_Shipping || $this->isAmount($line['unitpice']);
        });
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
