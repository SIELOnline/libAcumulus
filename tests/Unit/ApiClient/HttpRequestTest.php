<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\TestWebShop\TestDoubles\ApiClient\HttpRequest;

/**
 * Tests for the {@see \Siel\Acumulus\ApiClient\HttpRequest} class.
 *
 * A test double prevents real curl communication.
 */
class HttpRequestTest extends TestCase
{
    public function testBefore(): void
    {
        $httpRequest = new HttpRequest();
        static::assertNull($httpRequest->getMethod());
        static::assertNull($httpRequest->getUri());
        static::assertNull($httpRequest->getBody());
    }

    public function testGet(): void
    {
        $httpRequest = new HttpRequest();
        $uri = 'accounts';
        $httpResponse = $httpRequest->get($uri);
        static::assertSame('GET', $httpRequest->getMethod());
        static::assertSame($uri, $httpRequest->getUri());
        static::assertNull($httpRequest->getBody());
        static::assertSame($httpRequest, $httpResponse->getRequest());
    }

    public function testPost(): void
    {
        $httpRequest = new HttpRequest();
        $uri = 'accounts';
        $post = ['my_post' => 'my_value'];
        $httpResponse = $httpRequest->post($uri, $post);
        static::assertSame('POST', $httpRequest->getMethod());
        static::assertSame($uri, $httpRequest->getUri());
        static::assertSame($post, $httpRequest->getBody());
        static::assertSame($httpRequest, $httpResponse->getRequest());
    }
}
