<?php
/**
 * @noinspection PhpUnused
 */

namespace Siel\Acumulus\ApiClient;

/**
 * Documentation for the ApiClient namespace
 *
 * The ApiClient namespace handles the communication with the
 * [Acumulus API](https://www.siel.nl/acumulus/API/) and contains the following
 * classes:
 * - {@see Acumulus}: Provides a facade to the Acumulus
 *   API methods. The supported API calls may be called via a PHP method with
 *   the arguments for that call passed as its parameters, mostly being keyed
 *   arrays or scalars that map more or less directly to the API call arguments.
 * - {@see AcumulusResult}: The result of an API call
 *   is returned as an {@see AcumulusResult}. It
 *   contains:
 *     - The actual response, if no error message was returned.
 *     - The parts of the
 *       {@link https://www.siel.nl/acumulus/API/Basic_Response/ Basis Response}:
 *       status, errors, and warnings.
 *     - A reference to the {@see AcumulusRequest}.
 *     - References to the {@see HttpResponse}
 *       and {@see HttpRequest} objects of this call.
 *
 *   And it captures knowledge about:
 *      - Transforming the response body into the actual simplified response.
 *      - Handling error responses from the Acumulus API.
 *
 * Higher layers in this library and the application will typically only use the
 * above 2 classes to make a service call and process the result. However,
 * behind this facade, other classes encapsulate the actual work and knowledge
 * of the separate layers involved:
 * - {@see AcumulusRequest}: Encapsulates knowledge
 *   about the Acumulus API. Think of things like:
 *     - Getting the uri for each service.
 *     - Adding the authentication fields, where necessary, and the other
 *       {@link https://www.siel.nl/acumulus/API/Basic_Submit/ Basic Submit}
 *       fields.
 *     - How to format the arguments in an HTTP post body (see "Method #2 on
 *       {@link https://www.siel.nl/acumulus/API/Basic_Usage/ Basic Usage}").
 *
 * An {@see AcumulusRequest} passes the request down to
 * a {@see HttpRequest}.
 * - {@see HttpRequest}: Handles sending the request at
 *   the HTTP level:
 *     - Setting up the connection.
 *     - Setting the method, the headers, and url-encoding the arguments.
 *     - Sending the http request.
 *     - The response is placed in a {@see HttpResponse}.
 *     - Error handling at the Curl level.
 *
 *   For logging/debugging purpose this object is kept after the request
 *   returns, so that it is possible to access information like
 *     - The uri.
 *     - The HTTP method.
 *     - The fields in the HTTP request body.
 * - {@see HttpResponse} An object used to capture the
 *   response at the HTTP level. It is typically kept for logging and debugging
 *   purposes, and contains information like:
 *     - The HTTP result code
 *     - The HTTP response headers
 *     - The HTTP response body
 *     - The HTTP request headers(!) (as they are only known after the request
 *       returns).
 *     - A reference to the originating
 *       {@see HttpRequest}.
 *
 * The latter 2 classes are typically only used by an
 * {@see AcumulusRequest} or
 * {@see AcumulusResult}, except perhaps for future
 * support of other web services, e.g. looking for newer versions at GitHub or
 * validating VAT numbers with the VIES service.
 *
 * Note to developers: when implementing a new extension, normally, you should
 * not have to override any of the classes in this namespace.
 */
interface _Documentation {}
