<?php
/** @noinspection PhpStaticAsDynamicMethodCallInspection */

namespace Siel\Acumulus\Integration\ApiClient;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\ApiClient\HttpRequest;
use Siel\Acumulus\ApiClient\HttpResponse;
use Siel\Acumulus\Unit\ApiClient\CurlHandles;

/**
 * We test the relation between HttpRequest and HttpResponse and that the
 * contents of especially the info array is correct.
 */
class HttpRequestTest extends TestCase
{
    private HttpRequest $httpRequest;

    protected function setUp(): void
    {
        $this->httpRequest = new HttpRequest(new CurlHandles());
    }

    public function testGet()
    {
        $uri = 'http://localhost/lib-acumulus/readme.md';
        $response = $this->httpRequest->get($uri);

        // Request and response are related.
        $this->assertInstanceOf(HttpResponse::class, $response);

        // Properties of request.
        $this->assertSame('GET', $response->getRequest()->getMethod());
        $this->assertSame($uri, $response->getRequest()->getUri());
        $this->assertNull($response->getRequest()->getPostValues());

        // Properties of response.
        $this->assertSame(200, $response->getHttpCode());
        $this->assertIsString($response->getHeaders());
        $this->assertSame(file_get_contents(__DIR__ . '/../../../readme.md'), $response->getBody());
        $this->assertIsArray($response->getInfo());
        $this->assertSame($this->httpRequest, $response->getRequest());
        $this->assertIsString($response->getRequestHeaders());

        // Properties that (can) come from info.
        $info = $response->getInfo();
        $this->assertSame($info['http_code'], $response->getHttpCode());
        $this->assertSame($info['request_header'], $response->getRequestHeaders());
        $this->assertArrayHasKey('method_time', $info);
        $this->assertSame($response->getRequest()->getMethod(), explode(' ', $info['request_header'], 2)[0]);
    }

    public function testPost()
    {
        $uri = 'http://localhost/lib-acumulus/siel-logo.png';
        $post = ['my_post' => 'my_value'];
        $response = $this->httpRequest->post($uri, $post);

        // Request and response are related.
        $this->assertInstanceOf(HttpResponse::class, $response);

        // Properties of request.
        $this->assertSame('POST', $response->getRequest()->getMethod());
        $this->assertSame($uri, $response->getRequest()->getUri());
        $this->assertSame($post, $response->getRequest()->getPostValues());

        // Properties of response.
        $this->assertSame(200, $response->getHttpCode());
        $this->assertSame(file_get_contents(__DIR__ . '/../../../siel-logo.png'), $response->getBody());

        // Properties that (can) come from info.
        $info = $response->getInfo();
        $this->assertSame($info['http_code'], $response->getHttpCode());
        $this->assertSame($info['request_header'], $response->getRequestHeaders());
        $this->assertSame('image/png', $info['content_type']);
        $this->assertArrayHasKey('method_time', $info);
        $this->assertSame($response->getRequest()->getMethod(), explode(' ', $info['request_header'], 2)[0]);
    }
}
