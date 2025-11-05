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
        self::assertSame($httpRequest, $response->getRequest());

        // Properties of request.
        self::assertSame('GET', $httpRequest->getMethod());
        self::assertSame($uri, $httpRequest->getUri());
        self::assertNull($httpRequest->getBody());

        // Properties of response.
        self::assertSame(200, $response->getHttpStatusCode());
        self::assertIsString($response->getHeaders());
        self::assertStringEqualsFile(__DIR__ . '/../../../readme.md', $response->getBody());
        /** @noinspection DuplicatedCode */
        self::assertIsArray($response->getInfo());
        self::assertIsString($response->getRequestHeaders());

        // Properties that (can) come from info.
        $info = $response->getInfo();
        self::assertSame($info['http_code'], $response->getHttpStatusCode());
        self::assertSame($info['request_header'], $response->getRequestHeaders());
        self::assertArrayHasKey('method_time', $info);
        self::assertSame($response->getRequest()->getMethod(), explode(' ', $info['request_header'], 2)[0]);
    }

    public function testPost(): void
    {
        $httpRequest = new HttpRequest();
        $uri = 'http://localhost/lib-acumulus/resources/siel-logo.png';
        $post = ['my_post' => 'my_value'];
        $response = $httpRequest->post($uri, $post);

        // Request and response are linked
        self::assertSame($httpRequest, $response->getRequest());

        // Properties of request.
        self::assertSame('POST', $httpRequest->getMethod());
        self::assertSame($uri, $httpRequest->getUri());
        self::assertSame($post, $httpRequest->getBody());

        // Properties of response.
        self::assertSame(200, $response->getHttpStatusCode());
        self::assertIsString($response->getHeaders());
        self::assertStringEqualsFile(__DIR__ . '/../../../resources/siel-logo.png', $response->getBody());
        /** @noinspection DuplicatedCode */
        self::assertIsArray($response->getInfo());
        self::assertIsString($response->getRequestHeaders());

        // Properties that (can) come from info.
        $info = $response->getInfo();
        self::assertSame($info['http_code'], $response->getHttpStatusCode());
        self::assertSame($info['request_header'], $response->getRequestHeaders());
        self::assertArrayHasKey('method_time', $info);
        self::assertSame($response->getRequest()->getMethod(), explode(' ', $info['request_header'], 2)[0]);

        // Properties that (can) come from info.
        $info = $response->getInfo();
        self::assertSame($info['http_code'], $response->getHttpStatusCode());
        self::assertSame($info['request_header'], $response->getRequestHeaders());
        self::assertSame('image/png', $info['content_type']);
        self::assertArrayHasKey('method_time', $info);
        self::assertSame($response->getRequest()->getMethod(), explode(' ', $info['request_header'], 2)[0]);
    }

    public function test404(): void
    {
        $httpRequest = new HttpRequest();
        $uri = 'http://localhost/lib-acumulus/mot-existing';
        $response = $httpRequest->get($uri);

        // Request and response are linked
        self::assertSame($httpRequest, $response->getRequest());

        // Properties of request.
        self::assertSame('GET', $httpRequest->getMethod());
        self::assertSame($uri, $httpRequest->getUri());
        self::assertNull($httpRequest->getBody());

        // Properties of response.
        self::assertSame(404, $response->getHttpStatusCode());
        self::assertIsString($response->getHeaders());
        self::assertStringContainsString('<title>404 Not Found</title>', $response->getBody());
        /** @noinspection DuplicatedCode */
        self::assertIsArray($response->getInfo());
        self::assertIsString($response->getRequestHeaders());

        // Properties that (can) come from info.
        $info = $response->getInfo();
        self::assertSame($info['http_code'], $response->getHttpStatusCode());
        self::assertSame($info['request_header'], $response->getRequestHeaders());
        self::assertArrayHasKey('method_time', $info);
        self::assertSame($response->getRequest()->getMethod(), explode(' ', $info['request_header'], 2)[0]);
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
        self::assertStringContainsString("User-Agent: $ua", $response->getRequestHeaders());
    }

    public function testOverridingOptions(): void
    {
        $httpRequest = new HttpRequest([CURLOPT_HEADER => false, CURLINFO_HEADER_OUT => false]);
        $uri = 'http://localhost/lib-acumulus/resources/siel-logo.png';
        $post = ['my_post' => 'my_value'];
        $response = $httpRequest->post($uri, $post);

        // Properties that are overridden not to be present.
        self::assertEmpty($response->getHeaders());
        self::assertEmpty($response->getRequestHeaders());
    }
}
