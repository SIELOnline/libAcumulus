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
    const libraryVersion = '4.4.2';

    // Web service configuration related constants.
    const Status_NotSent = -1;
    const Status_Success = 0;
    const Status_Errors = 1;
    const Status_Warnings = 2;
    const Status_Exception = 3;
    const Status_SendingPrevented_InvoiceCreated = 4;
    const Status_SendingPrevented_InvoiceCompleted = 5;

    const Debug_None = 1;
    const Debug_SendAndLog = 2;
    const Debug_TestMode = 4;
    const Debug_StayLocal = 3;

    // Web service API constants.
    const TestMode_Normal = 0;
    const TestMode_Test = 1;

    // Web service related defaults.
    const baseUri = 'https://api.sielsystems.nl/acumulus';
    //const baseUri = 'https://ng1.sielsystems.nl';
    const apiVersion = 'stable';
    const outputFormat = 'json';

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
     * Indicates the debug mode of the web services communicator.
     *
     * @return int
     *   One of the ConfigInterface::Debug_... constants.
     */
    public function getDebug();

    /**
     * Returns the current log level for log messages from this module.
     *
     * @return int
     *   One of the Log::... constants.
     */
    public function getLogLevel();

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
     * Returns information about the environment of this library.
     *
     * @return array
     *   A keyed array with information about the environment of this library:
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
     * @return \Siel\Acumulus\Helpers\Log
     */
    public function getLog();
}
