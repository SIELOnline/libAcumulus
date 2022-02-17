<?php
/** @noinspection PhpStaticAsDynamicMethodCallInspection */

namespace Siel\Acumulus\Integration\ApiClient;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\ApiClient\HttpRequest;

/**
 * We test the relation between HttpRequest and HttpResponse and that the
 * contents of especially the info array is correct.
 */
class HttpRequestResponseTest extends TestCase
{
    private /*HttpRequest*/ $httpRequest;

    protected function setUp(): void
    {
        $this->httpRequest = new HttpRequest();
    }

    public function testGet()
    {
        $uri = 'http://localhost/lib-acumulus/readme.md';
        $response = $this->httpRequest->get($uri);

        // Request and response are linked
        $this->assertSame($this->httpRequest, $response->getRequest());

        // Properties of request.
        $this->assertSame('GET', $this->httpRequest->getMethod());
        $this->assertSame($uri, $this->httpRequest->getUri());
        $this->assertNull($this->httpRequest->getBody());

        // Properties of response.
        $this->assertSame(200, $response->getHttpCode());
        $this->assertIsString($response->getHeaders());
        $this->assertSame(file_get_contents(__DIR__ . '/../../../readme.md'), $response->getBody());
        $this->assertIsArray($response->getInfo());
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

        // Request and response are linked
        $this->assertSame($this->httpRequest, $response->getRequest());

        // Properties of request.
        $this->assertSame('POST', $this->httpRequest->getMethod());
        $this->assertSame($uri, $this->httpRequest->getUri());
        $this->assertSame($post, $this->httpRequest->getBody());

        // Properties of response.
        $this->assertSame(200, $response->getHttpCode());
        $this->assertIsString($response->getHeaders());
        $this->assertSame(file_get_contents(__DIR__ . '/../../../siel-logo.png'), $response->getBody());
        $this->assertIsArray($response->getInfo());
        $this->assertIsString($response->getRequestHeaders());

        // Properties that (can) come from info.
        $info = $response->getInfo();
        $this->assertSame($info['http_code'], $response->getHttpCode());
        $this->assertSame($info['request_header'], $response->getRequestHeaders());
        $this->assertArrayHasKey('method_time', $info);
        $this->assertSame($response->getRequest()->getMethod(), explode(' ', $info['request_header'], 2)[0]);

        // Properties that (can) come from info.
        $info = $response->getInfo();
        $this->assertSame($info['http_code'], $response->getHttpCode());
        $this->assertSame($info['request_header'], $response->getRequestHeaders());
        $this->assertSame('image/png', $info['content_type']);
        $this->assertArrayHasKey('method_time', $info);
        $this->assertSame($response->getRequest()->getMethod(), explode(' ', $info['request_header'], 2)[0]);
    }

    public function test404()
    {
        $uri = 'http://localhost/lib-acumulus/mot-existing';
        $response = $this->httpRequest->get($uri);

        // Request and response are linked
        $this->assertSame($this->httpRequest, $response->getRequest());

        // Properties of request.
        $this->assertSame('GET', $this->httpRequest->getMethod());
        $this->assertSame($uri, $this->httpRequest->getUri());
        $this->assertNull($this->httpRequest->getBody());

        // Properties of response.
        $this->assertSame(404, $response->getHttpCode());
        $this->assertIsString($response->getHeaders());
        $this->assertStringContainsString('<title>404 Not Found</title>', $response->getBody());
        $this->assertIsArray($response->getInfo());
        $this->assertIsString($response->getRequestHeaders());

        // Properties that (can) come from info.
        $info = $response->getInfo();
        $this->assertSame($info['http_code'], $response->getHttpCode());
        $this->assertSame($info['request_header'], $response->getRequestHeaders());
        $this->assertArrayHasKey('method_time', $info);
        $this->assertSame($response->getRequest()->getMethod(), explode(' ', $info['request_header'], 2)[0]);
    }

    public function testInvalidDomain()
    {
        $this->expectException(\RuntimeException::class);
        $uri = 'https://example0987654321.com/';
        $this->httpRequest->get($uri);
    }
}
