<?php

declare(strict_types=1);

namespace Siel\Acumulus\ApiClient;

use RuntimeException;

use function is_array;

/**
 * ConnectionHandler handles HTTP connections.
 *
 * This library uses Curl for the actual connections. Connections created by
 * Curl can be reused in subsequent requests (if {@see \curl_close()} was not
 * called). This can improve performance if multiple requests are made to the
 * same server. The performance gain will even be bigger for SSL connections!
 * Therefore, during 1 PHP request, this handler creates and keeps a curl handle
 * per destination server. If a subsequent request to the same server is
 * executed, that handle can be reused, thereby preventing a new connection set
 * up.
 */
class ConnectionHandler
{
    private static ?ConnectionHandler $instance = null;

    /**
     * Singleton pattern.
     *
     * (PHP8: define return type as static)
     *
     * @return static
     */
    public static function getInstance(): ConnectionHandler
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * @var resource[] (PHP8: CurlHandle[])
     */
    protected array $curlHandles = [];

    /**
     * Protected constructor: use {@see getInstance()}.
     */
    protected function __construct()
    {
    }

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
     * @throws \RuntimeException
     *   curl_init() failed.
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
        } else {
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
