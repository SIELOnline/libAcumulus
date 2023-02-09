<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection DuplicatedCode
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\ApiClient\HttpRequest;
use Siel\Acumulus\ApiClient\HttpResponse;

/**
 * Tests for the {@see HttpResponse} class.
 */
class HttpResponseTest extends TestCase
{
    public function testHttpResponse(): void
    {
        $request = new HttpRequest();
        $httpCode = 200;
        $requestHeaders = "request-headers1\r\nrequest-headers2\r\n\r\n";
        $responseHeaders = "response-headers1\r\nresponse-headers2\r\n\r\n";
        $responseBody = '{"vatinfo":{"vat":[{"vattype":"normal","vatrate":"21.0000"},{"vattype":"reduced","vatrate":"9.0000"},{"vattype":"reduced","vatrate":"0.0000"}]},"errors":{"count_errors":"0"},"warnings":{"count_warnings":"0"},"status":"0"}';
        $info = ['http_code' => $httpCode, 'request_header' => $requestHeaders];
        $response = new HttpResponse($responseHeaders, $responseBody, $info, $request);

        $this->assertSame($httpCode, $response->getHttpStatusCode());
        $this->assertSame($responseHeaders, $response->getHeaders());
        $this->assertSame($responseBody, $response->getBody());
        $this->assertSame($info, $response->getInfo());
        $this->assertSame($request, $response->getRequest());
        $this->assertSame($requestHeaders, $response->getRequestHeaders());
    }
}
