<?php
/** @noinspection PhpStaticAsDynamicMethodCallInspection */

namespace Siel\Acumulus\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\ApiClient\HttpRequest;
use Siel\Acumulus\ApiClient\HttpResponse;

class HttpResponseTest extends TestCase
{
    public function testHttpResponse()
    {
        $request = new HttpRequest(curl_init());
        $httpCode = 200;
        $requestHeaders = "request-headers1\r\nrequest-headers2\r\n\r\n";
        $responseHeaders = "response-headers1\r\nresponse-headers2\r\n\r\n";
        $responseBody = 'my-response-body';
        $info = ['http_code' => $httpCode, 'request_header' => $requestHeaders];
        $response = new HttpResponse($responseHeaders, $responseBody, $info, $request);
        $this->assertEquals($httpCode, $response->getHttpCode());
        $this->assertEquals($responseHeaders, $response->getHeaders());
        $this->assertEquals($responseBody, $response->getBody());
        $this->assertEquals($info, $response->getInfo());
        $this->assertEquals($request, $response->getRequest());
        $this->assertEquals($requestHeaders, $response->getRequestHeaders());
    }
}
