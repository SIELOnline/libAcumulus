<?php
/**
 * The ApiClient namespace handles the communication with the
 * [Acumulus API](https://www.siel.nl/acumulus/API/) and contains the following
 * classes:
 * - {@see Acumulus}: Provides a facade to the Acumulus API methods. The
 *   supported API calls may be called via a PHP method with the arguments for
 *   that call passed as its parameters, mostly being keyed arrays or scalars
 *   that map more or less directly to the API call arguments.
 * - {@see AcumulusResult}: The result of an API call is returned as a {@see AcumulusResult}.
 *   It contains:
 *     - The actual response (if the call returned correctly).
 *     - The parts of the
 *       [Basis Response](https://www.siel.nl/acumulus/API/Basic_Response/):
 *       status, errors and warnings
 *     - Locally found error and warning messages, and thrown exceptions.
 *     - A reference to the {@see AcumulusRequest}.
 *     - References to the low level {@see HttpRequest} and {@see HttpResponse}
 *       objects of this call.
 *
 * Higher layers in this library and the application will typically only use the
 * above 2 classes to make a service call and process the result.
 *
 * However, behind this facade, other classes encapsulate the actual work and
 * knowledge of the separate layers involved:
 * - {@see AcumulusRequest}: Encapsulates knowledge about the Acumulus API.
 *   Think of things like:
 *     - Getting the uri for each function.
 *     - Adding the authentication fields, where necessary, and the other
 *       [Basic Submit](https://www.siel.nl/acumulus/API/Basic_Submit/)
 *       fields.
 *     - How to format the arguments in an HTTP post body (see "Method #2 on
 *       [Basic Usage](https://www.siel.nl/acumulus/API/Basic_Usage/)").
 *     - Error handling at the API level.
 *   An {see @ApiCommunicator} passes the request down to a {see HttpRequest}.
 * - {@see HttpRequest}: Handles sending the request at the HTTP level:
 *     - Setting up the connection
 *     - Setting the method, headers, url-encoding the arguments.
 *     - Sending the http request.
 *     - The response is placed in a {@see HttpResponse}.
 *     - Error handling at the (HTTP) communication level.
 *   For logging/debugging purpose this object is kept after the request
 *   returns, so that it is possible to access information like
 *     - The uri.
 *     - The method.
 *     - The fields in the request body.
 * - {@see HttpResponse} An object used to capture the response at the HTTP
 *   level. It is typically kept for logging and debugging purposes, therefore,
 *   it contains information like:
 *     - The HTTP result code
 *     - The response headers
 *     - The response body
 *     - The request headers(!) (as they are only known after the request
 *       returns).
 *     - A reference to the {@see HttpRequest}.
 *
 * The latter 2 classes are typically only used by an {@see AcumulusRequest}
 * and a {@see AcumulusResult}, except perhaps for future support of other web services,
 * e.g. looking for newer versions at GitHub or validating VAT numbers with the
 * VIES service.
 *
 * Note to developers: When implementing a new extension, you should not
 * override any of the classes in this namespace.
 */
namespace Siel\Acumulus\ApiClient;
