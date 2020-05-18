<?php
namespace Siel\Acumulus\TestWebShop\ApiClient;

use Siel\Acumulus\ApiClient\HttpCommunicator as BaseHttpCommunicator;

/**
 * {@inhertidoc}
 */
class HttpCommunicator extends BaseHttpCommunicator
{
    /**
     * Executes an http post request.
     *
     * @param string $uri
     *   The uri to send the HTTP request to.
     * @param array|string $post
     *   An array of values to be placed in the POST body or an url-encoded
     *   string that contains all the POST values
     *
     * @return string
     *  The response body from the HTTP response.
     *
     * @throws \RuntimeException
     */
    public function post($uri, $post)
    {
        $response = '';
        // @todo: more intelligent response handling using defines responses for
        //   given requests, so we can test handling the responses as well.
        return $uri;
    }
}
