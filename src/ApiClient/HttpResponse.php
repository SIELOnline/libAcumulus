<?php
namespace Siel\Acumulus\ApiClient;

/**
 * Class HttpResponse contains an HTTP response: http code, (response) headers,
 * body, metadata/info about the request/response, and the request that lead to
 * this response.
 */
class HttpResponse
{
    protected /*string*/ $headers;
    protected /*string*/ $body;
    protected /*array*/ $info;
    protected /*HttpRequest*/ $request;

    public function __construct(string $headers, string $body, array $info, HttpRequest $request)
    {
        $this->headers = $headers;
        $this->body = $body;
        $this->info = $info;
        $this->request = $request;
    }

    /**
     * Returns the HTTP response status code.
     *
     * @return int
     *   The HTTP response status code, or 0 if unknown.
     */
    public function getHttpCode(): int
    {
        return (int) $this->info['http_code'] ?? 0;
    }

    /**
     * Returns the HTTP response headers.
     *
     * @return string
     *   The HTTP response headers as 1 string. Each header is separated by a
     *   new line (\r\n) and the headers end with a double new line.
     */
    public function getHeaders(): string
    {
        // @todo: return as string[]? (split on \r\n, double \r\n\r\n at end!)
        return $this->headers;
    }

    /**
     * Returns the body of the HTTP response.
     *
     * Note that this may contain unmasked sensitive data (e.g. a password) and
     * thus should not be logged unprocessed.
     *
     * @return string
     *   The body of the HTTP response.
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Returns a set of informative values about the HTTP request/response.
     *
     * It contains at least the keys:
     * - http_code: the HTTP status code.
     * - request_header: the request headers
     * - method_time: the time (float) passed in the HttpRequest::execute()
     *   method.
     * In reality, it will contain all info as returned by {@see curl_getinfo()}
     * (plus that extra key method_time).
     *
     * @return array
     *   A set of informative values about the HTTP request/response.
     */
    public function getInfo(): array
    {
        return $this->info;
    }

    /**
     * Returns the set of HTTP request headers.
     *
     * The headers sent with the request are only known after executing the
     * request and are, therefore, made part of this
     * {@see \Siel\Acumulus\ApiClient\HttpResponse} class, not of the
     * {@see \Siel\Acumulus\ApiClient\HttpRequest} class.
     *
     * @return string
     *   The HTTP request headers as 1 string. Each header is separated by a
     *   new line (\r\n) and the headers end with a double new line. If the
     *   request headers are not known, the empty string is returned.
     */
    public function getRequestHeaders(): string
    {
        // @todo: return as string[]? (split on \r\n, double \r\n\r\n at end!)
        return $this->info['request_header'] ?? '';
    }

    /**
     * Returns the {@see \Siel\Acumulus\ApiClient\HttpRequest} that was executed
     * to get this {@see \Siel\Acumulus\ApiClient\HttpResponse}.
     *
     * @return \Siel\Acumulus\ApiClient\HttpRequest
     *   The HttpRequest belonging that lead to this response.
     */
    public function getRequest(): HttpRequest
    {
        return $this->request;
    }
}
