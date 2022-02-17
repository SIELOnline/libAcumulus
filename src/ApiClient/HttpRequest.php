<?php
namespace Siel\Acumulus\ApiClient;

use LogicException;
use RuntimeException;

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
    protected /*bool*/ $hasExecuted = false;
    protected /*?string*/ $method = null;
    protected /*?string*/ $uri = null;
    /**
     * @var array|string|null
     *   See {@see HttpRequest::getBody()}.
     */
    protected $body = null;

    /**
     * @return string|null
     *   Returns the HTTP method to be used for this request: 'POST' or 'GET',
     *   or null if not yet set.
     */
    public function getMethod(): ?string
    {
        return $this->method;
    }

    /**
     * @return string|null
     *   Returns the uri for this request, or null if not yet set.
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
     * Sets up an HTTP get request.
     *
     * @param string $uri
     *   The uri to send the HTTP request to.
     *
     * @return \Siel\Acumulus\ApiClient\HttpResponse
     *
     * @throws \LogicException
     *   This request has already been executed.
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
     * @return \Siel\Acumulus\ApiClient\HttpResponse
     *
     * @throws \LogicException
     *   This request has already been executed.
     * @throws \RuntimeException
     *   An error occurred at:
     *   - The Curl internals level, e.g. an out of memory error.
     *   - The communication level, e.g. time-out or no response received.
     */
    public function post(string $uri, $postFields): HttpResponse
    {
        return $this->execute('POST', $uri, $postFields);
    }

    /**
     * Executes the HTTP request.
     *
     * @param string $method
     *   The HTTP method to use for this request.
     * @param string $uri
     *   The uri to send the HTTP request to.
     * @param array|string|null $body
     *   The (optional) contents to be placed in the body, either:
     *   - An array of key/value pairs to be placed in the body in the
     *     multipart/form-data format.
     *   - An url-encoded string that contains all the POST values.
     *   - Null when the body is to remain empty (mostly for GET requests).
     *
     * @return \Siel\Acumulus\ApiClient\HttpResponse
     *  The HTTP response.
     *
     * @throws \LogicException
     *   This request has already been executed.
     * @throws \RuntimeException
     *   An error occurred at:
     *   - The Curl internals level, e.g. an out of memory error.
     *   - The communication level, e.g. time-out or no response received.
     */
    protected function execute(string $method, string $uri, $body = null): HttpResponse
    {
        if ($this->hasExecuted) {
            throw new LogicException('HttpRequest::execute() may only be called once.');
        }
        $this->hasExecuted = true;

        $this->uri = $uri;
        $this->method = $method;
        $this->body = $body;
        return $this->executeWithCurl();
    }

    /**
     * Executes an HTTP request using Curl and returns the
     * {@see \Siel\Acumulus\ApiClient\HttpResponse}.
     *
     * All details regarding the fact we are using Curl are contained in this
     * single method (except perhaps, the info array passed to the HttpResponse
     * that gets created which is based on curl_get_info()). This is also done
     * to be able to unit test this class (by just mocking this 1 method) while
     * not going so far as to inject a "communication library".
     *
     * @return \Siel\Acumulus\ApiClient\HttpResponse
     *
     * @throws \RuntimeException
     *   An error occurred at the Curl - e.g. memory error - or communication
     *   level, e.g. time-out or no response received.
     */
    protected function executeWithCurl(): HttpResponse
    {
        $start = microtime(TRUE);

        // Get and configure the curl connection.
        // Since 2017-09-19 the Acumulus web service only accepts TLS 1.2.
        // - Apparently, some curl libraries do support this version but do not
        //   use it by default, so we force it.
        // - Apparently, some up-to-date curl libraries do not define this
        //   constant, so we define it, if not defined.
        $handle = $this->getHandle();
        if (!defined('CURL_SSLVERSION_TLSv1_2')) {
            define('CURL_SSLVERSION_TLSv1_2', 6);
        }
        $options = [
            CURLOPT_URL => $this->getUri(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLINFO_HEADER_OUT => true,
            // This is a requirement for the Acumulus web service but should be
            // good for all servers.
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 15,
            // Follow redirects (maximum of 5).
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
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
        if (!curl_setopt_array($handle, $options)) {
            $this->raiseCurlError($handle, 'curl_setopt_array(defaults)');
        }

        // Send and receive over the curl connection.
        $response = curl_exec($handle);
        $responseInfo = curl_getinfo($handle);
        // We only check for errors at the communication level, not for
        // responses that indicate an error.
        if (!is_string($response) || empty($response) || curl_errno($handle) !== 0 || !isset($responseInfo['header_size'])) {
            $this->raiseCurlError($handle, 'curl_exec()');
        }

        $header_size = (int) $responseInfo['header_size'];
        $headers = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        return new HttpResponse(
            $headers,
            $body,
            $responseInfo + ['method_time' => microtime(true) - $start],
            $this
        );
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
        $message = sprintf('%s (curl: %s): %d - %s', $function, $curlVersion['version'], $code, curl_error($handle));
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
