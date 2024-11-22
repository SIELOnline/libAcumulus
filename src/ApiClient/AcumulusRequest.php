<?php

declare(strict_types=1);

namespace Siel\Acumulus\ApiClient;

use RuntimeException;
use Siel\Acumulus\Config\Environment;
use Siel\Acumulus\Data\BasicSubmit;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Util;

use function assert;
use function sprintf;

/**
 * AcumulusRequest turns a call to {@see Acumulus} into an {@see HttpRequest}.
 *
 * It offers:
 * - Adding the basic submit structure - contract, connector, testmode, ... - to
 *   create a complete request structure.
 * - Conversion from the request structure (array or AcumulusObject) to XML.
 * - Sending the request.
 * - Creating the {@see AcumulusResult} from the {@see HttpResponse}.
 * - Good error handling, including:
 *     - Detecting HTML responses from the proxy before the actual web service.
 *     - Detecting XML responses when an error occurred before the <format> was
 *       interpreted.
 *     - Interpreting the HTTP result status code.
 */
class AcumulusRequest
{
    protected Container $container;
    protected Environment $environment;
    protected Util $util;
    protected string $userLanguage;
    protected ?string $uri = null;
    protected ?array $submit = null;
    protected ?HttpRequest $httpRequest = null;

    public function __construct(Container $container, Environment $environment, Util $util, string $userLanguage)
    {
        $this->container = $container;
        $this->environment = $environment;
        $this->util = $util;
        $this->userLanguage = $userLanguage;
    }

    public function getUri(): ?string
    {
        return $this->uri;
    }

    public function getHttpRequest(): ?HttpRequest
    {
        return $this->httpRequest;
    }

    /**
     * Returns the full submit structure as has been sent to Acumulus.
     *
     * The full submit structure consists of the:
     * - basic submit: see {@link https://www.siel.nl/acumulus/API/Basic_Submit/}.
     * - submit: the API call specific part as passed to {@see execute()}.
     *
     * @return array|null
     *    The full submit structure as has been sent to Acumulus, or null if
     *    this Acumulus request has not yet been executed.
     */
    public function getSubmit(): ?array
    {
        return $this->submit;
    }

    /**
     * Returns the uri and submit structure as a loggable string.
     *
     * - We use json_encode() to turn submit into a string, so we may use it to create
     *   test input.
     * - We mask all values that have 'password' in their key, so we can safely
     *   log it.
     */
    public function getMaskedRequest(): string
    {
        $uri = $this->getUri();
        $submit = $this->getSubmit();
        if ($submit !== null) {
            $submit = $this->util->maskArray($this->getSubmit());
        }
        /** @noinspection JsonEncodingApiUsageInspection  False positive */
        return sprintf("Request: uri=%s\nsubmit=%s", $uri ?? 'null', json_encode($submit, Log::JsonFlags));
    }

    /**
     * Sends the message to the given API function and returns the results.
     * Any errors (or warnings) in the response structure of the web service are
     * returned via the result value and should be handled at a higher level.
     *
     * @param string $uri
     *   The uri of the API resource to invoke.
     * @param array $submit
     *   The main submit part to send to the Acumulus web API.
     * @param bool $needContract
     *   Indicates whether this api function needs the contract details. Most
     *   API functions do, but for some general listing functions, like vat
     *   info, it is optional, and for signUp it is even not allowed.
     *
     * @return AcumulusResult
     *   The result of the web service call. See
     *   {@link https://www.siel.nl/acumulus/API/Basic_Response/} for the
     *   structure of a response. In case of errors, an exception or error
     *   message will have been added to the Result and the main response may be
     *   empty.
     *
     * @throws AcumulusException|AcumulusResponseException
     *   An error occurred:
     *   - At the internal level, e.g. an out of memory error.
     *   - At the communication level, e.g. time-out or no response received.
     *   - While converting the {@see HttpResponse} into an
     *     {@see AcumulusResult}. This conversion is done during the
     *     construction of the {@see AcumulusResult}, so no result object will
     *     be created in the case of errors.
     *   Note that errors at the application level will not be thrown as an
     *   exception, but will have to be handled by the calling code.
     */
    public function execute(string $uri, array $submit, bool $needContract): AcumulusResult
    {
        assert($this->uri === null);

        $this->uri = $uri;
        $this->submit = $this->constructFullSubmit($submit, $needContract);
        $httpResponse = $this->executeWithPostXmlStringApproach();
        $acumulusResult = $this->container->createAcumulusResult($this, $httpResponse);

        assert($acumulusResult->getAcumulusRequest() === $this);
        assert($acumulusResult->getHttpResponse() === $httpResponse);

        return $acumulusResult;
    }

    /**
     * Actually executes an Acumulus request.
     *
     * [By wrapping the actual communication call in its own method we can
     * unit-test this class by just overriding this one method, while not going
     * so far as to inject the httpRequest.]
     *
     * We use the
     * {@link https://www.siel.nl/acumulus/API/Basic_Usage/#:~:text=The%20xmlstring%20approach XML string approach}
     * to send a request. That is we send the message as XML in the body of a
     * POST request in multipart/form-data format. Note: by passing an array to
     * Curl, we let Curl do the formatting and encoding.
     *
     * @return HttpResponse
     *
     * @throws AcumulusException
     *   An error occurred at:
     *   - The internal level, e.g. an out of memory error.
     *   - The communication level, e.g. time-out or no response received.
     *   Note that errors at the application level will be detected when the
     *   response is interpreted.
     *
     * @noinspection SpellCheckingInspection
     */
    protected function executeWithPostXmlStringApproach(): HttpResponse
    {
        // - Convert message to XML. XML requires 1 top level tag, so add one.
        //   This top tag name is ignored by the API, we use <acumulus>.
        // - 'xmlstring' is the post field that Acumulus expects.
        $options = $this->getCurlOptions();
        $body = ['xmlstring' => trim($this->util->convertArrayToXml(['acumulus' => $this->submit]))];
        $this->httpRequest = $this->container->createHttpRequest($options);
        try {
            $httpResponse = $this->httpRequest->post($this->uri, $body);
        } catch (RuntimeException $e) {
            // Rethrow as an AcumulusException for logging higher up.
            throw new AcumulusException($e->getMessage(), $e->getCode(), $e);
        }

        assert($httpResponse->getRequest() === $this->httpRequest);
        assert($this->httpRequest->getUri() === $this->uri);
        assert($this->httpRequest->getBody() === $body);

        return $httpResponse;
    }

    /**
     * Returns an array with Curl options we want for requests to the Acumulus
     * API server.
     */
    protected function getCurlOptions(): array
    {
        return [CURLOPT_USERAGENT => $this->getUserAgent()];
    }

    protected function getUserAgent(): string
    {
        $environment = $this->environment->toArray();
        $library = "libAcumulus/{$environment['libraryVersion']}";
        $shop = " {$environment['shopName']}/{$environment['shopVersion']}";
        $cms = !empty($environment['cmsName']) ? " {$environment['cmsName']}/{$environment['cmsVersion']}" : '';
        $php = " PHP/{$environment['phpVersion']}";
        return $library . $shop . $cms . $php;
    }

    /**
     * Constructs the full submit structure to be sent to the Acumulus API.
     * A submit-message is an XML message consisting of:
     * - A {@link https://www.siel.nl/acumulus/API/Basic_Submit/ [basic submit]}
     *   part containing a.o. tags like <contract>, <testmode>, and <connector>.
     * - An endpoint specific part, the actual data to be sent.
     *
     * @param array $submit
     *   The endpoint specific part to be sent.
     * @param bool $needContract
     *   Whether this endpoint needs the <contract> part to authorize and
     *   authenticate the call.
     *
     * @return array
     *   The post fields to send to Acumulus.
     *
     * @throws \RuntimeException
     *    Required property is not set.
     */
    protected function constructFullSubmit(array $submit, bool $needContract): array
    {
        $basicSubmit = $this->getBasicSubmit();
        $basicSubmit->needContract = $needContract;
        return array_merge($basicSubmit->toArray(), $submit);
    }

    /**
     * Returns the basic submit part of each API message.
     *
     * The basic submit part is defined at
     * {@link https://www.siel.nl/acumulus/API/Basic_Submit/}
     */
    protected function getBasicSubmit(): BasicSubmit
    {
        return $this->container->getCollectorManager()->collectBasicSubmit();
    }
}
