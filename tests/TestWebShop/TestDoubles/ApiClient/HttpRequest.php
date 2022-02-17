<?php
namespace Siel\Acumulus\TestWebShop\TestDoubles\ApiClient;

use Siel\Acumulus\ApiClient\HttpRequest as BaseHttpRequest;
use Siel\Acumulus\ApiClient\HttpResponse;

/**
 * {@inhertidoc}
 */
class HttpRequest extends BaseHttpRequest
{
    protected function executeWithCurl(): HttpResponse
    {
        $httpCode = 200;
        $requestHeaders = "request-headers1\r\nrequest-headers2\r\n\r\n";
        $responseHeaders = "response-headers1\r\nresponse-headers2\r\n\r\n";
        $responseBody = '{"vatinfo":{"vat":[{"vattype":"normal","vatrate":"21.0000"},{"vattype":"reduced","vatrate":"9.0000"},{"vattype":"reduced","vatrate":"0.0000"}]},"errors":{"count_errors":"0"},"warnings":{"count_warnings":"0"},"status":"0"}';
        $info = ['http_code' => $httpCode, 'request_header' => $requestHeaders, 'method_time' => 0.00123];
        return new HttpResponse($responseHeaders, $responseBody, $info, $this);
    }
}
