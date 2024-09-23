<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Integration\ApiClient;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Siel\Acumulus\ApiClient\HttpRequest;

/**
 * We test the relation between HttpRequest and HttpResponse and that the
 * contents of especially the info array is correct.
 */
class HttpRequestResponseTest extends TestCase
{
    public function testGet(): void
    {
        $httpRequest = new HttpRequest([CURLOPT_HTTPHEADER => ['Cache-Control: no-cache']]);
        $uri = 'http://localhost/lib-acumulus/readme.md';
        $response = $httpRequest->get($uri);

        // Request and response are linked
        $this->assertSame($httpRequest, $response->getRequest());

        // Properties of request.
        $this->assertSame('GET', $httpRequest->getMethod());
        $this->assertSame($uri, $httpRequest->getUri());
        $this->assertNull($httpRequest->getBody());

        // Properties of response.
        $this->assertSame(200, $response->getHttpStatusCode());
        $this->assertIsString($response->getHeaders());
        $this->assertStringEqualsFile(__DIR__ . '/../../../readme.md', $response->getBody());
        /** @noinspection DuplicatedCode */
        $this->assertIsArray($response->getInfo());
        $this->assertIsString($response->getRequestHeaders());

        // Properties that (can) come from info.
        $info = $response->getInfo();
        $this->assertSame($info['http_code'], $response->getHttpStatusCode());
        $this->assertSame($info['request_header'], $response->getRequestHeaders());
        $this->assertArrayHasKey('method_time', $info);
        $this->assertSame($response->getRequest()->getMethod(), explode(' ', $info['request_header'], 2)[0]);
    }

    public function testPost(): void
    {
        $httpRequest = new HttpRequest();
        $uri = 'http://localhost/lib-acumulus/resources/siel-logo.png';
        $post = ['my_post' => 'my_value'];
        $response = $httpRequest->post($uri, $post);

        // Request and response are linked
        $this->assertSame($httpRequest, $response->getRequest());

        // Properties of request.
        $this->assertSame('POST', $httpRequest->getMethod());
        $this->assertSame($uri, $httpRequest->getUri());
        $this->assertSame($post, $httpRequest->getBody());

        // Properties of response.
        $this->assertSame(200, $response->getHttpStatusCode());
        $this->assertIsString($response->getHeaders());
        $this->assertStringEqualsFile(__DIR__ . '/../../../resources/siel-logo.png', $response->getBody());
        /** @noinspection DuplicatedCode */
        $this->assertIsArray($response->getInfo());
        $this->assertIsString($response->getRequestHeaders());

        // Properties that (can) come from info.
        $info = $response->getInfo();
        $this->assertSame($info['http_code'], $response->getHttpStatusCode());
        $this->assertSame($info['request_header'], $response->getRequestHeaders());
        $this->assertArrayHasKey('method_time', $info);
        $this->assertSame($response->getRequest()->getMethod(), explode(' ', $info['request_header'], 2)[0]);

        // Properties that (can) come from info.
        $info = $response->getInfo();
        $this->assertSame($info['http_code'], $response->getHttpStatusCode());
        $this->assertSame($info['request_header'], $response->getRequestHeaders());
        $this->assertSame('image/png', $info['content_type']);
        $this->assertArrayHasKey('method_time', $info);
        $this->assertSame($response->getRequest()->getMethod(), explode(' ', $info['request_header'], 2)[0]);
    }

    public function test404(): void
    {
        $httpRequest = new HttpRequest();
        $uri = 'http://localhost/lib-acumulus/mot-existing';
        $response = $httpRequest->get($uri);

        // Request and response are linked
        $this->assertSame($httpRequest, $response->getRequest());

        // Properties of request.
        $this->assertSame('GET', $httpRequest->getMethod());
        $this->assertSame($uri, $httpRequest->getUri());
        $this->assertNull($httpRequest->getBody());

        // Properties of response.
        $this->assertSame(404, $response->getHttpStatusCode());
        $this->assertIsString($response->getHeaders());
        $this->assertStringContainsString('<title>404 Not Found</title>', $response->getBody());
        /** @noinspection DuplicatedCode */
        $this->assertIsArray($response->getInfo());
        $this->assertIsString($response->getRequestHeaders());

        // Properties that (can) come from info.
        $info = $response->getInfo();
        $this->assertSame($info['http_code'], $response->getHttpStatusCode());
        $this->assertSame($info['request_header'], $response->getRequestHeaders());
        $this->assertArrayHasKey('method_time', $info);
        $this->assertSame($response->getRequest()->getMethod(), explode(' ', $info['request_header'], 2)[0]);
    }

    public function testInvalidDomain(): void
    {
        $this->expectException(RuntimeException::class);
        $httpRequest = new HttpRequest();
        $uri = 'https://example0987654321.com/';
        $httpRequest->get($uri);
    }

    public function testPassingOptions(): void
    {
        $ua = 'my_useragent/1.0';
        $httpRequest = new HttpRequest([CURLOPT_USERAGENT => $ua]);
        $uri = 'http://localhost/lib-acumulus/resources/siel-logo.png';
        $post = ['my_post' => 'my_value'];
        $response = $httpRequest->post($uri, $post);

        // Properties that (can) come from info.
        $this->assertStringContainsString("User-Agent: $ua", $response->getRequestHeaders());
    }

    public function testOverridingOptions(): void
    {
        $httpRequest = new HttpRequest([CURLOPT_HEADER => false, CURLINFO_HEADER_OUT => false]);
        $uri = 'http://localhost/lib-acumulus/resources/siel-logo.png';
        $post = ['my_post' => 'my_value'];
        $response = $httpRequest->post($uri, $post);

        // Properties that are overridden not to be present.
        $this->assertEmpty($response->getHeaders());
        $this->assertEmpty($response->getRequestHeaders());
    }
}
