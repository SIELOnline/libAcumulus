<?php
namespace Siel\Acumulus\TestWebShop\ApiClient;

use Siel\Acumulus\ApiClient\AcumulusRequest as BaseAcumulusRequest;
use Siel\Acumulus\ApiClient\HttpRequest;
use Siel\Acumulus\ApiClient\HttpResponse;
use Siel\Acumulus\ApiClient\Result;
use Siel\Acumulus\Helpers\Severity;

/**
 * Communicator implements the communication with the Acumulus web API.
 *
 * It offers:
 * - Conversion between array and XML.
 * - Conversion from Json to array.
 * - Communicating with the Acumulus webservice using the
 *   {@se HttpCommunicator}.
 * - Good error handling, including detecting html responses from the proxy
 *   before the actual web service.
 */
class AcumulusRequest extends BaseAcumulusRequest
{
    protected function executeWithHttp(): HttpResponse
    {
        $request = new HttpRequest();
        $httpCode = 200;
        $requestHeaders = "request-headers1\r\nrequest-headers2\r\n\r\n";
        $responseHeaders = "response-headers1\r\nresponse-headers2\r\n\r\n";
        $responseBody = '{"vatinfo":{"vat":[{"vattype":"normal","vatrate":"21.0000"},{"vattype":"reduced","vatrate":"9.0000"},{"vattype":"reduced","vatrate":"0.0000"}]},"errors":{"count_errors":"0"},"warnings":{"count_warnings":"0"},"status":"0"}';
        $info = ['http_code' => $httpCode, 'request_header' => $requestHeaders, 'method_time' => 0.00123];
        return new HttpResponse($responseHeaders, $responseBody, $info, $request);
    }
}
