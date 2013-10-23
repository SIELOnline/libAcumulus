<?php
/**
 * @file Contains the Confgiurtion Interface.
 */

namespace Siel\Acumulus;

/**
 * ConfigInterface defines an interface to store and retrieve configuration
 * values.
 *
 * Configuration is stored in the host environment (i.e. the web shop), this
 * interface abstracts from how a specific web shop does so.
 *
 * @package Siel\Acumulus
 */
interface ConfigInterface {
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
   * Indicates whether the web api communicator should log all messages that are
   * sent and received.
   *
   * @return bool
   */
  public function getDebug();

  /**
   * Indicates whether the web api communicator should be a test class that does
   * not actually send the message to Acumulus but logs the message that would
   * have been sent.
   *
   * @return bool
   */
  public function getLocal();

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
   *   A keyed array with the keys contractcode, username, password, and
   *   optionally emailonerror, and emailonwarning.
   */
  public function getCredentials();

  /**
   * Returns a set of default settings for an invoice when adding an invoice.
   *
   * @return array
   *   A keyed array with the keys
   *   - type
   *   - accountnumber
   *   - invoicenumber
   *   - issuedate
   *   - costheading
   *   - template
   *   - trigger-order-status
   *   - use-margin
   *   - use cost-price
   */
  public function getInvoiceSettings();

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
