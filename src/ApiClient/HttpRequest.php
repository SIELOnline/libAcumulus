<?php
/**
 * @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection  SensitiveParameter.
 * @noinspection PhpLanguageLevelInspection  An attribute is a comment in 7.4.
 */

declare(strict_types=1);

namespace Siel\Acumulus\ApiClient;

use RuntimeException;
use SensitiveParameter;

use function assert;
use function define;
use function defined;
use function in_array;
use function is_string;
use function strlen;

/**
 * HttpCommunicator implements the communication with the Acumulus web API at the
 * https level.
 *
 * It offers:
 * - Https communication with the Acumulus webservice using the curl library:
 *   setting up the connection, sending the request, receiving the response.
 * - Connections are kept open, 1 per destination, so they can be reused.
 * - Good error handling.
 */
class HttpRequest
{
    protected ?string $method = null;
    protected ?string $uri = null;
    protected array $options = [];
    /**
     * @var array|string|null
     *   See {@see getBody()}.
     */
    protected $body;
    protected ?HttpResponse $httpResponse = null;

    /**
     * Constructor.
     *
     * @param array $options
     *   An optional set of Curl option-value pairs.
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * @return string|null
     *   Returns the HTTP method to be used for this request: 'POST' or 'GET',
     *   or null if not yet executed.
     */
    public function getMethod(): ?string
    {
        return $this->method;
    }

    /**
     * @return string|null
     *   Returns the uri for this request, or null if not yet executed.
     */
    public function getUri(): ?string
    {
        return $this->uri;
    }

    /**
     * Returns the contents that will be placed in the body of the request.
     *
     * Either:
     * - An array of key/value pairs to be placed in the body in the
     *   multipart/form-data format.
     * - An url-encoded string that contains all the POST values.
     * - Null when the body is to remain empty (GET requests).
     *
     * Note that this may contain unmasked sensitive data (e.g. a password) and
     * thus should not be logged unprocessed.
     *
     * @return array|string|null
     *   The contents of the body, null if empty or not yet set.
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Returns the result of this request, or null if not yet executed.
     *
     * This "forward-linking" is ony used to be able to log what we have in case
     * of exceptions thrown during the execution of this request or the
     * processing of it.
     *
     * @return HttpResponse|null
     *   The result of this request, or null if not yet executed.
     *
     * @noinspection PhpUnused
     */
    public function getHttpResponse(): ?HttpResponse
    {
        return $this->httpResponse;
    }

    /**
     * Sets up an HTTP get request.
     *
     * @param string $uri
     *   The uri to send the HTTP request to.
     *
     * @return HttpResponse
     *
     * @throws \RuntimeException
     *   An error occurred at:
     *   - The Curl internals level, e.g. an out of memory error.
     *   - The communication level, e.g. time-out or no response received.
     */
    public function get(string $uri): HttpResponse
    {
        return $this->execute('GET', $uri);
    }

    /**
     * Sets up an HTTP post request.
     *
     * @param string $uri
     *   The uri to send the HTTP request to.
     * @param array|string $postFields
     *   The contents to be placed in the body, either:
     *   - An array of key/value pairs to be placed in the body in the
     *     multipart/form-data format.
     *   - An url-encoded string that contains all the POST values.
     *   - Null when the body is to remain empty (mostly for GET requests).
     *
     * @return HttpResponse
     *
     * @throws \RuntimeException
     *   An error occurred at:
     *   - The Curl internals level, e.g. an out of memory error.
     *   - The communication level, e.g. time-out or no response received.
     */
    public function post(
        string $uri,
        #[SensitiveParameter]
        $postFields
    ): HttpResponse {
        return $this->execute('POST', $uri, $postFields);
    }

    /**
     * Executes the HTTP request.
     *
     * @param string $method
     *   The HTTP method to use for this request. for now, we only support GET
     *   and POST
     * @param string $uri
     *   The uri to send the HTTP request to.
     * @param array|string|null $body
     *   The (optional) contents to be placed in the body, either:
     *   - An array of key/value pairs to be placed in the body in the
     *     multipart/form-data format.
     *   - An url-encoded string that contains all the POST values.
     *   - Null when the body is to remain empty (mostly for GET requests).
     *
     * @return HttpResponse
     *  The HTTP response.
     *
     * @throws \RuntimeException
     *   An error occurred at:
     *   - The Curl internals level, e.g. an out of memory error.
     *   - The communication level, e.g. time-out or no response received.
     */
    protected function execute(
        string $method,
        string $uri,
        #[SensitiveParameter]
        $body = null
    ): HttpResponse {
        $method = strtoupper($method);
        assert(in_array($method, ['GET', 'POST']), 'HttpRequest::execute(): non-supported method.');
        assert($this->uri === null, 'HttpRequest::execute(): may only be called once.');

        $this->uri = $uri;
        $this->method = $method;
        $this->body = $body;
        $this->httpResponse = $this->executeWithCurl();

        assert($this->httpResponse->getRequest() === $this);

        return $this->httpResponse;
    }

    /**
     * Executes an HTTP request using Curl and returns the {@see HttpResponse}.
     *
     * All details regarding the fact we are using Curl are contained in this
     * single method (except perhaps, the info array passed to the HttpResponse
     * that gets created which is based on curl_get_info()). This is also done
     * to be able to unit test this class (by just mocking this 1 method) while
     * not going so far as to inject a "communication library".
     *
     * @return HttpResponse
     *
     * @throws \RuntimeException
     *   An error occurred at the Curl - e.g. memory error - or communication
     *   level, e.g. time-out or no response received.
     */
    protected function executeWithCurl(): HttpResponse
    {
        $start = microtime(TRUE);

        // Get and configure the curl connection.
        $handle = $this->getHandle();
        $options = $this->getCurlOptions();
        if (!curl_setopt_array($handle, $options)) {
            $this->raiseCurlError($handle, 'curl_setopt_array()');
        }

        // Send and receive over the curl connection.
        $response = curl_exec($handle);
        // We only check for errors at the communication level, not for
        // responses that indicate an error.
        if (curl_errno($handle) !== 0) {
            $this->raiseCurlError($handle, 'curl_exec()');
        }
        if ($options[CURLOPT_RETURNTRANSFER] && !is_string($response)) {
            $this->raiseCurlError($handle, 'curl_exec() return');
        }

        $responseInfo = curl_getinfo($handle);
        if ($options[CURLOPT_HEADER]) {
            $header_size = (int) $responseInfo['header_size'];
            if (!is_string($response) || strlen($response) < $responseInfo['header_size']) {
                $this->raiseCurlError($handle, 'curl_exec() response');
            }
            $headers = substr($response, 0, $header_size);
            $body = substr($response, $header_size);
        } else {
            $headers = '';
            $body = is_string($response) ? $response : '';
        }

        $responseInfo += ['method_time' => microtime(true) - $start];
        return new HttpResponse($headers, $body, $responseInfo, $this);
    }

    /**
     * Collects and returns all Curl options.
     *
     * The Curl options consist of:
     * 1) options fixed by this class and that may not be overridden.
     * 2) Options passed in by the caller.
     * 3) Defaults, that may be overridden by the options passed in.
     *
     * @return array
     *  The assembled Curl options.
     */
    protected function getCurlOptions(): array
    {
        // 1) Fixed.
        $options = [
            CURLOPT_URL => $this->getUri(),
            // Return the response instead of a bool indicating success.
            CURLOPT_RETURNTRANSFER => true,
            //CURLOPT_PROXY => '127.0.0.1:8888', // Uncomment to debug with Fiddler.
            //CURLOPT_SSL_VERIFYPEER => false, // Uncomment to debug with Fiddler.
        ];
        switch ($this->getMethod()) {
            case 'GET':
                $options[CURLOPT_HTTPGET] = true;
                break;
            case 'POST':
                $options[CURLOPT_POST] = true;
                if ($this->getBody() !== null) {
                    $options[CURLOPT_POSTFIELDS] = $this->getBody();
                }
                break;
        }

        // 2) Options passed in by the caller, will overwrite our defaults.
        $options += $this->options;

        // 3) Defaults.
        // Since 2017-09-19 the Acumulus web service only accepts TLS 1.2.
        // - Apparently, some curl libraries do support this version but do not
        //   use it by default, so we force it.
        // - Apparently, some up-to-date curl libraries do (did?) not define
        //   this constant, so we define it, if not defined.
        if (!defined('CURL_SSLVERSION_TLSv1_2')) {
            define('CURL_SSLVERSION_TLSv1_2', 6);
        }
        $options += [
            // Return the response headers with the response.
            CURLOPT_HEADER => true,
            // Return request headers in the response of {@see \curl_getinfo()}.
            CURLINFO_HEADER_OUT => true,
            // This is a requirement for the Acumulus web service, but should be
            // good for all servers.
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_CONNECTTIMEOUT_MS => 15000,
            CURLOPT_TIMEOUT_MS => 15000,
            // Follow redirects (with a maximum of 5).
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
        ];
        return $options;
    }

    /**
     * Raises a runtime exception with the curl error message.
     *
     * @param resource $handle (PHP8: CurlHandle)
     * @param string $function
     *   The name of the Curl function that failed.
     *
     * @throws \RuntimeException
     *   Always.
     */
    protected function raiseCurlError($handle, string $function): void
    {
        $curlVersion = curl_version();
        $code = curl_errno($handle);
        /** @noinspection OffsetOperationsInspection */
        $message = sprintf('%s (curl: %s): %d - %s', $function, $curlVersion['version'] ?? 'unknown', $code, curl_error($handle));
        $this->closeHandle();
        throw new RuntimeException($message, $code);
    }

    /**
     * Gets a Curl handle.
     *
     * This method is a wrapper around access to the ConnectionHandler.
     *
     * @return resource
     */
    protected function getHandle()
    {
        return ConnectionHandler::getInstance()->get($this->getUri());
    }

    /**
     * Closes and deletes a failed Curl handle.
     *
     * This method is a wrapper around access to the ConnectionHandler.
     */
    protected function closeHandle(): void
    {
        ConnectionHandler::getInstance()->close($this->getUri());
    }
}
