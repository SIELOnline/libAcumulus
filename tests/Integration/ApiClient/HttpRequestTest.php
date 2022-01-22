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
        $this->assertEquals('GET', $response->getRequest()->getMethod());
        $this->assertEquals($uri, $response->getRequest()->getUri());
        $this->assertNull($response->getRequest()->getPostValues());

        // Properties of response.
        $this->assertEquals(200, $response->getHttpCode());
        $this->assertIsString($response->getHeaders());
        $this->assertEquals(file_get_contents(__DIR__ . '/../../../readme.md'), $response->getBody());
        $this->assertIsArray($response->getInfo());
        $this->assertEquals($this->httpRequest, $response->getRequest());
        $this->assertIsString($response->getRequestHeaders());

        // Properties that (can) come from info.
        $info = $response->getInfo();
        $this->assertEquals($info['http_code'], $response->getHttpCode());
        $this->assertEquals($info['request_header'], $response->getRequestHeaders());
        $this->assertArrayHasKey('method_time', $info);
        $this->assertEquals($response->getRequest()->getMethod(), explode(' ', $info['request_header'], 2)[0]);
    }

    public function testPost()
    {
        $uri = 'http://localhost/lib-acumulus/siel-logo.png';
        $post = ['my_post' => 'my_value'];
        $response = $this->httpRequest->post($uri, $post);

        // Request and response are related.
        $this->assertInstanceOf(HttpResponse::class, $response);

        // Properties of request.
        $this->assertEquals('POST', $response->getRequest()->getMethod());
        $this->assertEquals($uri, $response->getRequest()->getUri());
        $this->assertEquals($post, $response->getRequest()->getPostValues());

        // Properties of response.
        $this->assertEquals(200, $response->getHttpCode());
        $this->assertEquals(file_get_contents(__DIR__ . '/../../../siel-logo.png'), $response->getBody());

        // Properties that (can) come from info.
        $info = $response->getInfo();
        $this->assertEquals($info['http_code'], $response->getHttpCode());
        $this->assertEquals($info['request_header'], $response->getRequestHeaders());
        $this->assertEquals('image/png', $info['content_type']);
        $this->assertArrayHasKey('method_time', $info);
        $this->assertEquals($response->getRequest()->getMethod(), explode(' ', $info['request_header'], 2)[0]);
    }
}
