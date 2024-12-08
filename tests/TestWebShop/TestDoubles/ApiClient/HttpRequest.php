<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\TestDoubles\ApiClient;

use Siel\Acumulus\ApiClient\HttpRequest as BaseHttpRequest;
use Siel\Acumulus\ApiClient\HttpResponse;
use Siel\Acumulus\Tests\Unit\ApiClient\ApiRequestResponseExamples;

/**
 * {@inhertidoc}
 */
class HttpRequest extends BaseHttpRequest
{
    private ApiRequestResponseExamples $examples;

    public function __construct()
    {
        parent::__construct();
        $this->examples =  ApiRequestResponseExamples::getInstance();
    }

    protected function executeWithCurl(): HttpResponse
    {
        $httpCode = $this->examples->getHttpStatusCode($this->getUri());
        $requestHeaders = "request-headers1\r\nrequest-headers2\r\n\r\n";
        $responseHeaders = "response-headers1\r\nresponse-headers2\r\n\r\n";
        $responseBody = $this->examples->getResponseBody($this->getUri());
        $info = ['http_code' => $httpCode, 'request_header' => $requestHeaders, 'method_time' => 0.00123];
        return new HttpResponse($responseHeaders, $responseBody, $info, $this);
    }
}
