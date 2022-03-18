<?php
namespace Siel\Acumulus\TestWebShop\TestDoubles\ApiClient;

use Siel\Acumulus\ApiClient\HttpRequest as BaseHttpRequest;
use Siel\Acumulus\ApiClient\HttpResponse;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Unit\ApiClient\ApiRequestResponseExamples;

/**
 * {@inhertidoc}
 */
class HttpRequest extends BaseHttpRequest
{
    /**
     * @var \Siel\Acumulus\Unit\ApiClient\ApiRequestResponseExamples
     */
    private $examples;

    public function __construct()
    {
        parent::__construct();
        $this->examples = new ApiRequestResponseExamples();
    }

    protected function executeWithCurl(): HttpResponse
    {
        $httpCode = 200;
        $requestHeaders = "request-headers1\r\nrequest-headers2\r\n\r\n";
        $responseHeaders = "response-headers1\r\nresponse-headers2\r\n\r\n";
        $responseBody = $this->examples->getResponseBody($this->getUri());
        $info = ['http_code' => $httpCode, 'request_header' => $requestHeaders, 'method_time' => 0.00123];
        return new HttpResponse($responseHeaders, $responseBody, $info, $this);
    }
}
