<?php
/**
 * The ApiClient namespace handles the external communication with the Acumulus
 * API and contains the following classes:
 * - {@see Acumulus}: Provides abstracted access to the Acumulus API methods.
 * - {@see Result}: Contains the result of a service call: the actual response
 *   and any other information like the status, exceptions, error messages, and
 *   warnings.
 * - {@see ApiCommunicator}: Handles the API calls: API url construction,
 *   conversion between PHP structures and request/response message formats (xml
 *   and json) and error handling at the API level. This class should only be
 *   called by {@see Acumulus} and never be used directly higher up in the
 *   application.
 * - {@see HttpCommunicator}: Handles the actual http communication (connecting,
 *   sending requests, and receiving responses), and error handling. This class
 *   should only be called by {@see \Siel\Acumulus\ApiClient\ApiCommunicator}
 *   and never be used directly higher up in the application, except, perhaps,
 *   when other web services are used.
 *
 * When implementing a new extension, you should not have to override any of the
 * classes in this namespace.
 */
namespace Siel\Acumulus\ApiClient;
