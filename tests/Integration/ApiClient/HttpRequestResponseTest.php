<?php

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
        static::assertSame($httpRequest, $response->getRequest());

        // Properties of request.
        static::assertSame('GET', $httpRequest->getMethod());
        static::assertSame($uri, $httpRequest->getUri());
        static::assertNull($httpRequest->getBody());

        // Properties of response.
        static::assertSame(200, $response->getHttpStatusCode());
        static::assertIsString($response->getHeaders());
        static::assertStringEqualsFile(__DIR__ . '/../../../readme.md', $response->getBody());
        /** @noinspection DuplicatedCode */
        static::assertIsArray($response->getInfo());
        static::assertIsString($response->getRequestHeaders());

        // Properties that (can) come from info.
        $info = $response->getInfo();
        static::assertSame($info['http_code'], $response->getHttpStatusCode());
        static::assertSame($info['request_header'], $response->getRequestHeaders());
        static::assertArrayHasKey('method_time', $info);
        static::assertSame($response->getRequest()->getMethod(), explode(' ', $info['request_header'], 2)[0]);
    }

    public function testPost(): void
    {
        $httpRequest = new HttpRequest();
        $uri = 'http://localhost/lib-acumulus/resources/Acumulus-Online-Boekhouden_icon_150.png';
        $post = ['my_post' => 'my_value'];
        $response = $httpRequest->post($uri, $post);

        // Request and response are linked
        static::assertSame($httpRequest, $response->getRequest());

        // Properties of request.
        static::assertSame('POST', $httpRequest->getMethod());
        static::assertSame($uri, $httpRequest->getUri());
        static::assertSame($post, $httpRequest->getBody());

        // Properties of response.
        static::assertSame(200, $response->getHttpStatusCode());
        static::assertIsString($response->getHeaders());
        static::assertStringEqualsFile(__DIR__ . '/../../../resources/Acumulus-Online-Boekhouden_icon_150.png', $response->getBody());
        /** @noinspection DuplicatedCode */
        static::assertIsArray($response->getInfo());
        static::assertIsString($response->getRequestHeaders());

        // Properties that (can) come from info.
        $info = $response->getInfo();
        static::assertSame($info['http_code'], $response->getHttpStatusCode());
        static::assertSame($info['request_header'], $response->getRequestHeaders());
        static::assertArrayHasKey('method_time', $info);
        static::assertSame($response->getRequest()->getMethod(), explode(' ', $info['request_header'], 2)[0]);

        // Properties that (can) come from info.
        $info = $response->getInfo();
        static::assertSame($info['http_code'], $response->getHttpStatusCode());
        static::assertSame($info['request_header'], $response->getRequestHeaders());
        static::assertSame('image/png', $info['content_type']);
        static::assertArrayHasKey('method_time', $info);
        static::assertSame($response->getRequest()->getMethod(), explode(' ', $info['request_header'], 2)[0]);
    }

    public function test404(): void
    {
        $httpRequest = new HttpRequest();
        $uri = 'http://localhost/lib-acumulus/mot-existing';
        $response = $httpRequest->get($uri);

        // Request and response are linked
        static::assertSame($httpRequest, $response->getRequest());

        // Properties of request.
        static::assertSame('GET', $httpRequest->getMethod());
        static::assertSame($uri, $httpRequest->getUri());
        static::assertNull($httpRequest->getBody());

        // Properties of response.
        static::assertSame(404, $response->getHttpStatusCode());
        static::assertIsString($response->getHeaders());
        static::assertStringContainsString('<title>404 Not Found</title>', $response->getBody());
        /** @noinspection DuplicatedCode */
        static::assertIsArray($response->getInfo());
        static::assertIsString($response->getRequestHeaders());

        // Properties that (can) come from info.
        $info = $response->getInfo();
        static::assertSame($info['http_code'], $response->getHttpStatusCode());
        static::assertSame($info['request_header'], $response->getRequestHeaders());
        static::assertArrayHasKey('method_time', $info);
        static::assertSame($response->getRequest()->getMethod(), explode(' ', $info['request_header'], 2)[0]);
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
        $uri = 'http://localhost/lib-acumulus/resources/Acumulus-Online-Boekhouden_icon_150.png';
        $post = ['my_post' => 'my_value'];
        $response = $httpRequest->post($uri, $post);

        // Properties that (can) come from info.
        static::assertStringContainsString("User-Agent: $ua", $response->getRequestHeaders());
    }

    public function testOverridingOptions(): void
    {
        $httpRequest = new HttpRequest([CURLOPT_HEADER => false, CURLINFO_HEADER_OUT => false]);
        $uri = 'http://localhost/lib-acumulus/resources/Acumulus-Online-Boekhouden_icon_150.png';
        $post = ['my_post' => 'my_value'];
        $response = $httpRequest->post($uri, $post);

        // Properties that are overridden not to be present.
        static::assertEmpty($response->getHeaders());
        static::assertEmpty($response->getRequestHeaders());
    }
}
