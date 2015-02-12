<?php
/**
 * @file Contains the Configuration Interface.
 */

namespace Siel\Acumulus\Common;

/**
 * Defines an interface to store and retrieve configuration values.
 *
 * Configuration is stored in the host environment (i.e. the web shop), this
 * interface abstracts from how a specific web shop does so.
 */
interface ConfigInterface {
  // Constants for configuration fields.
  const TriggerOrderEvent_OrderStatus = 1;
  const TriggerOrderEvent_InvoiceCreate = 2;

  const InvoiceNrSource_ShopInvoice = 1;
  const InvoiceNrSource_ShopOrder = 2;
  const InvoiceNrSource_Acumulus = 3;

  const InvoiceDate_InvoiceCreate = 1;
  const InvoiceDate_OrderCreate = 2;
  const InvoiceDate_Transfer = 3;

  const Debug_None = 1;
  const Debug_SendAndLog = 2;
  const Debug_TestMode = 4;
  const Debug_StayLocal = 3;

  /**
   * Returns the URI of the Acumulus API to connect with.
   *
   * This method returns the base URI, without version indicator and API call.
   *
   * @return string
   *   The URI of the Acumulus API.
   */
  public function getBaseUri();

  /**
   * Returns the version of the Acumulus API to use.
   *
   * A version number may be part of the URI, so this value implicitly also
   *  defines the API version to communicate with.
   *
   * @return string
   *   The version of the Acumulus API to use.
   */
  public function getApiVersion();

  /**
   * Returns information about the environment of this library.
   *
   * @return array
   *   A keyed array with information about the environment of this library:
   *   - libraryVersion
   *   - moduleVersion
   *   - shopName
   *   - shopVersion
   */
  public function getEnvironment();

  /**
   * Indicates the debug mode of the web api communicator.
   *
   * @return int
   *   One of the ConfigInterface::Debug_... constants.
   */
  public function getDebug();

  /**
   * Returns the format the output from the Acumulus API should be in.
   *
   * @return string
   *   xml or json.
   */
  public function getOutputFormat();

  /**
   * Returns the contract credentials to authenticate with the Acumulus API.
   *
   * @return array
   *   A keyed array with the keys:
   *   - contractcode
   *   - username
   *   - password
   *   - emailonerror
   *   - emailonwarning
   */
  public function getCredentials();

  /**
   * Returns the set of settings related to adding an invoice.
   *
   * @return array
   *   A keyed array with the keys:
   *   - defaultCustomerType
   *   - sendCustomer
   *   - genericCustomerEmail
   *   - overwriteIfExists
   *   - defaultAccountNumber
   *   - invoiceNrSource
   *   - dateToUse
   *   - defaultCostCenter
   *   - defaultInvoiceTemplate
   *   - defaultInvoicePaidTemplate
   *   - triggerOrderEvent
   *   - triggerOrderStatus
   *   - useMargin
   */
  public function getInvoiceSettings();

  /**
   * Returns the set of settings related to sending an email.
   *
   * @return array
   *   A keyed array with the keys:
   *   - emailAsPdf
   *   - emailBcc
   *   - emailFrom
   *   - subject
   *   - confirmReading
   */
  public function getEmailAsPdfSettings();

  /**
   * Returns the current (2 character) language (code).
   *
   * @return string
   */
  public function getLanguage();

  /**
   * Get a translated string.
   *
   * Strictly speaking,this is no configuration thing, but as doing it this way,
   * allows to easily pass it along. Practically everywhere the config is
   * passed, the translation object has to be passed as well.
   *
   * @param string $key
   *
   * @return string
   *   The translated message for the given key, or the key itself if no
   *   translation could be found. Neither in the current language and nor in
   *   the fallback language dutch.
   */
  public function t($key);

  /**
   * Allows the host environment to supply a log sink.
   *
   * Strictly speaking,this is no configuration thing, but as doing it this way,
   * allows the host environment to define the log sink (file, db table, etc),
   * it gives some flexibility to the communication part.
   *
   * @param string $message
   */
  public function log($message);
}
