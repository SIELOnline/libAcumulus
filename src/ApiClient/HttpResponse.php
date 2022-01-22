<?php
namespace Siel\Acumulus\ApiClient;

/**
 * Class HttpResponse contains an HTTP response: http code, (response) headers,
 * body, metadata/info about the request,response, and the request that lead to
 * this response.
 */
class HttpResponse
{
    protected string $headers;
    protected string $body;
    protected array $info;
    protected HttpRequest $request;

    public function __construct(string $headers, string $body, array $info, HttpRequest $request)
    {
        $this->headers = $headers;
        $this->body = $body;
        $this->info = $info;
        $this->request = $request;
    }

    public function getHttpCode(): int
    {
        return (int) $this->info['http_code'] ?? 0;
    }

    public function getHeaders(): string
    {
        // @todo: return as string[]? (split on \r\n, double \r\n\r\n at end!)
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getInfo(): array
    {
        return $this->info;
    }

    public function getRequestHeaders(): string
    {
        // @todo: return as string[]? (split on \r\n, double \r\n\r\n at end!)
        return $this->info['request_header'];
    }

    public function getRequest(): HttpRequest
    {
        return $this->request;
    }
}
