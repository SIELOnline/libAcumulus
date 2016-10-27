<?php
namespace Siel\Acumulus\Web;

/**
 * CommunicationConfigInterface defines an interface to store and retrieve
 * communication specific configuration values.
 *
 * Configuration is stored in the host environment (normally a web shop), this
 * interface abstracts from how a specific web shop does so.
 */
interface ConfigInterface
{
    const libraryVersion = '4.6.0-alpha3';

    // Web service configuration related constants.
    // Send status: bits 1, 2 and 3. Can be combined with an Invoice_Sent_...
    // const. Not necessarily a single bit per value, but the order should be by
    // increasing worseness.
    const Status_Success = 0;
    const Status_Warnings = 1;
    const Status_Errors = 2;
    const Status_Exception = 4;
    const Status_Mask = 7;

    const Debug_None = 1;
    const Debug_SendAndLog = 2;
    const Debug_TestMode = 3;

    // Web service API constants.
    const TestMode_Normal = 0;
    const TestMode_Test = 1;

    // Web service related defaults.
    const baseUri = 'https://api.sielsystems.nl/acumulus';
    //const baseUri = 'https://ng1.sielsystems.nl';
    const apiVersion = 'stable';
    const outputFormat = 'json';

    /**
     * The hostname of the current server.
     *
     * Used for a default email address.
     *
     * @return string
     */
    public function getHostName();

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
     * Returns information about the environment of this library.
     *
     * @return array
     *   A keyed array with information about the environment of this library:
     *   - baseUri
     *   - apiVersion
     *   - libraryVersion
     *   - moduleVersion
     *   - shopName
     *   - shopVersion
     *   - phpVersion
     *   - os
     *   - curlVersion
     *   - jsonVersion
     */
    public function getEnvironment();

    /**
     * Returns the set of settings related to reacting to shop events.
     *
     * @return array
     *   A keyed array with the keys:
     *   - debug
     *   - logLevel
     *   - outputFormat
     */
    public function getPluginSettings();

    /**
     * @return \Siel\Acumulus\Helpers\Log
     */
    public function getLog();
}
