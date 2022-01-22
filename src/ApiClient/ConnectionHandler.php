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
class ConnectionHandler
{
    /**
     * @var resource[] (CurlHandle[] PHP8)
     */
    protected array $curlHandles = [];

    /**
     * Closes all open Curl handles.
     */
    public function __destruct()
    {
        foreach ($this->curlHandles as $curlHandle) {
            curl_close($curlHandle);
        }
    }

    /**
     * Opens and returns a Curl handle for the given host.
     *
     * Curl handles are stored for reuse, so do not curl_close() them after use.
     *
     * @param string $uri
     *   The uri that will be executed. This will be used to:
     *   - Determine the scheme, host and (optionally) port to see if there's
     *     already an open connection to that host.
     *   - set the CURLOPT_URL option.
     *
     * @return resource (CurlHandle PHP8)
     *   A, possibly already open, curl handle with the CURLOPT_URL option
     *   set to $uri.
     *
     */
    public function get(string $uri)
    {
        // Determine the scheme://host[:port] part to get a handle for the host.
        $key = $this->getKey($uri);

        if (empty($this->curlHandles[$key])) {
            $this->curlHandles[$key] = curl_init();
            if (empty($this->curlHandles[$key])) {
                unset($this->curlHandles[$key]);
                throw new RuntimeException(__METHOD__ . "($uri): curl_init() failed");
            }
        }
        else {
            // We can reuse the handle, but reset the options.
            curl_reset($this->curlHandles[$key]);
        }
        return $this->curlHandles[$key];
    }

    /**
     * Closes a Curl handle and removes it from the set of Curl handles.
     * Should only be used in case of errors or by the destructor.
     *
     * @param string $uri
     */
    public function close(string $uri): void
    {
        $key = $this->getKey($uri);
        if (isset($this->curlHandles[$key])) {
            curl_close($this->curlHandles[$key]);
            unset($this->curlHandles[$key]);
        }
    }

    /**
     * Returns a key that functions as key for the connection pool.
     *
     * @param string $uri
     *
     * @return string
     *   The scheme and host part of the uri that functions as key for the
     *   connection pool.
     */
    protected function getKey(string $uri): string
    {
        $parts = parse_url($uri);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new RuntimeException(__METHOD__ . "($uri): not a valid uri");
        }
        $key = $parts['scheme'] . '://' . $parts['host'];
        if ((!empty($parts['port']))) {
            $key .= ':' . $parts['port'];
        }

        return $key;
    }
}
