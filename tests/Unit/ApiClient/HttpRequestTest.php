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
        $this->assertNull($httpRequest->getMethod());
        $this->assertNull($httpRequest->getUri());
        $this->assertNull($httpRequest->getBody());
    }

    public function testGet(): void
    {
        $httpRequest = new HttpRequest();
        $uri = 'accounts';
        $httpResponse = $httpRequest->get($uri);
        $this->assertSame('GET', $httpRequest->getMethod());
        $this->assertSame($uri, $httpRequest->getUri());
        $this->assertNull($httpRequest->getBody());
        $this->assertSame($httpRequest, $httpResponse->getRequest());
    }

    public function testPost(): void
    {
        $httpRequest = new HttpRequest();
        $uri = 'accounts';
        $post = ['my_post' => 'my_value'];
        $httpResponse = $httpRequest->post($uri, $post);
        $this->assertSame('POST', $httpRequest->getMethod());
        $this->assertSame($uri, $httpRequest->getUri());
        $this->assertSame($post, $httpRequest->getBody());
        $this->assertSame($httpRequest, $httpResponse->getRequest());
    }
}
