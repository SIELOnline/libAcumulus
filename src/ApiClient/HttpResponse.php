<?php
/**
 * @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection  SensitiveParameter.
 * @noinspection PhpLanguageLevelInspection  An attribute is a comment in 7.4.
 */
declare(strict_types=1);

namespace Siel\Acumulus\ApiClient;

use SensitiveParameter;

use function count;

/**
 * Class HttpResponse contains an HTTP response: http code, (response) headers,
 * body, metadata/info about the request/response, and the request that lead to
 * this response.
 */
class HttpResponse
{
    protected string $headers;
    protected array $parsedHeaders = [];
    protected string $body;
    protected array $info;
    protected HttpRequest $request;

    public function __construct(
        string $headers,
        string $body,
        #[SensitiveParameter]
        array $info,
        HttpRequest $request
    ) {
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
    public function getHttpStatusCode(): int
    {
        return (int) ($this->info['http_code'] ?? 0);
    }

    /**
     * Returns the HTTP response headers.
     *
     * As for now there's not a great use for inspecting the headers, we return
     * the header as a string and leave processing it up to the calling side.
     *
     * @return string
     *   The HTTP response headers as 1 string. Each header is separated by a
     *   new line (\r\n) and the headers end with a double new line. The return
     *   will be the empty string if the headers were not returned.
     */
    public function getHeaders(): string
    {
        return $this->headers;
    }

    /**
     * Returns the value of an HTTP response header.
     *
     * @param string $name
     *   The name of the header to return the value for.
     *
     * @return string
     *   The value for the requested header, or '' if not set.
     */
    public function getHeader(string $name): string
    {
        if (count($this->parsedHeaders) === 0 && !empty($this->headers)) {
            $headerLines = explode("\r\n", $this->headers);
            foreach ($headerLines as $headerLine) {
                $headerLine = trim($headerLine);
                if (!empty($headerLine)) {
                    $parts = explode(':', $headerLine, 2);
                    if (count($parts) === 2) {
                        $this->parsedHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                    } else {
                        $this->parsedHeaders['status-line'] = $headerLine;
                    }
                }
            }
        }
        return $this->parsedHeaders[strtolower($name)] ?? '';
    }

    /**
     * Returns the body of the HTTP response.
     *
     * Note that this may contain sensitive data (e.g. a password) and should
     * not be logged unprocessed.
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
     * Note that this may contain sensitive data (e.g. a password) and should
     * not be logged unprocessed.
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
     * request and are, therefore, made part of this {@see HttpResponse} class,
     * not of the {@see HttpRequest} class.
     *
     * As for now there's not a great use for inspecting the headers, we return
     * the header as a string and leave processing it up to the calling side.
     *
     * @return string
     *   The HTTP request headers as 1 string. Each header is separated by a
     *   new line (\r\n) and the headers end with a double new line. If the
     *   request headers are not known, an empty string is returned.
     */
    public function getRequestHeaders(): string
    {
        return $this->info['request_header'] ?? '';
    }

    /**
     * Returns the {@see HttpRequest} that was executed to get this
     * {@see HttpResponse}.
     *
     * @return HttpRequest
     *   The HttpRequest belonging that lead to this response.
     */
    public function getRequest(): HttpRequest
    {
        return $this->request;
    }
}
