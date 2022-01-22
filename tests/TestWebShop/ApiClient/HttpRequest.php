<?php
namespace Siel\Acumulus\TestWebShop\ApiClient;

use Siel\Acumulus\ApiClient\HttpRequest as BaseHttpCommunicator;
use Siel\Acumulus\ApiClient\HttpResponse;

/**
 * {@inhertidoc}
 */
class HttpRequest extends BaseHttpCommunicator
{
    public function execute(): HttpResponse
    {
        $response = '';
        // @todo: more intelligent response handling using defines responses for
        //   given requests, so we can test handling the responses as well.
        return new HttpResponse('', '', ['http_code' => 200, 'request_header' => ''], $this);
    }
}
