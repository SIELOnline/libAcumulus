<?php
namespace Siel\Acumulus\ApiClient;

use RuntimeException;

/**
 * HttpCommunicator implements the communication with the Acumulus WebAPI at the
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
    protected bool $hasExecuted = false;
    /**
     * @var resource (PHP8: CurlHandle)
     */
    protected $handle;
    protected ?string $uri;
    protected ?string $method;
    /**
     * @var array|string|null
     *   See {@see HttpRequest::getPostFields()}
     */
    protected $postFields = null;

    /**
     * @param resource $curlHandle (PHP8: CurlHandle)
     */
    public function __construct($curlHandle)
    {
        $this->handle = $curlHandle;
    }

    public function getUri(): ?string
    {
        return $this->uri;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    /**
     * @return array|string|null
     *   The contents of the body, either:
     *   - An array of key/value pairs to be placed in the body in the
     *     multipart/form-data format.
     *   - An url-encoded string that contains all the POST values.
     *   - Null when the body is to remain empty (mostly for GET requests).

     */
    public function getPostFields()
    {
        return $this->postFields;
    }

    /**
     * Returns the post fields as a message that can be used for logging.
     *
     * @return string
     */
    public function getPostFieldsAsMsg(): string
    {
        $post = $this->postFields;
        if ($post !== null) {
            if (is_array($post)) {
                if (count($post) === 0) {
                    $body = '[]';
                } elseif (count($post) === 1 && key($post) === 'xmlstring') {
                    // Acumulus specialty: $post contains ['xmlstring' => $message]
                    $body = reset($post);
                } else {
                    $body = json_encode($post);
                }
            } else {
                // Urlencoded string.
                $body = $post;
            }
        } else {
            $body = '';
        }
        return $body;
    }

    /**
     * Sets up an HTTP get request.
     *
     * @param string $uri
     *   The uri to send the HTTP request to.
     *
     * @return $this
     */
    public function get(string $uri): self
    {
        $this->uri = $uri;
        $this->method = 'GET';
        $this->postFields = null;
        return $this;
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
     * @return $this
     */
    public function post(string $uri, $postFields): self
    {
        $this->uri = $uri;
        $this->method = 'POST';
        $this->postFields = $postFields;
        return $this;
    }

    /**
     * Executes the set HTTP request.
     *
     * PRE: Either the method get() or post() must have been called to set up
     * the request.
     *
     * @return \Siel\Acumulus\ApiClient\HttpResponse
     *  The HTTP response.
     *
     * @throws \RuntimeException
     *   In case of an internal curl error or an error at the communication
     *   level. (Thus not a response indicating error: that is up to the
     *   application level.)
     */
    public function execute(): HttpResponse
    {
        if ($this->uri === null || $this->method === null ) {
            throw new RuntimeException('HttpRequest::execute() may only be called after get() or post() has been called.');
        }
        if ($this->hasExecuted) {
            throw new RuntimeException('HttpRequest::execute() may only be called once.');
        }

        $start = microtime(TRUE);
        $this->hasExecuted = true;

        // Configure the curl connection.
        // Since 2017-09-19 the Acumulus web service only accepts TLS 1.2.
        // - Apparently, some curl libraries do support this version but do not
        //   use it by default, so we force it.
        // - Apparently, some up-to-date curl libraries do not define this
        //   constant, so we define it, if not defined.
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
                if ($this->getPostFields() !== null) {
                    $options[CURLOPT_POSTFIELDS] = $this->getPostFields();
                }
                break;
        }
        if (!curl_setopt_array($this->handle, $options)) {
            $this->raiseCurlError('curl_setopt_array(defaults)');
        }

        // Send and receive over the curl connection.
        $response = curl_exec($this->handle);
        $responseInfo = curl_getinfo($this->handle);
        // We only check for errors at the communication level, not for
        // responses that indicate an error.
        if (!is_string($response) || empty($response) || curl_errno($this->handle) !== 0 || !isset($responseInfo['header_size'])) {
            $this->raiseCurlError('curl_exec()');
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
     * @param string $function
     *   The name of the Curl function that failed.
     *
     * @throws \RuntimeException
     */
    protected function raiseCurlError(string $function): void
    {
        $curlVersion = curl_version();
        $code = curl_errno($this->handle);
        $message = sprintf('%s (curl: %s): %d - %s', $function, $curlVersion['version'], $code, curl_error($this->handle));
        throw new RuntimeException($message, $code);
    }
}
