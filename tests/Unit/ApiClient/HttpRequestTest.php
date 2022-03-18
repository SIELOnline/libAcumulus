<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

namespace Siel\Acumulus\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\TestWebShop\TestDoubles\ApiClient\HttpRequest;

class HttpRequestTest extends TestCase
{
    protected function setUp(): void
    {
    }

    public function testBefore()
    {
        $httpRequest = new HttpRequest();
        $this->assertNull($httpRequest->getMethod());
        $this->assertNull($httpRequest->getUri());
        $this->assertNull($httpRequest->getBody());
    }

    public function testGet()
    {
        $httpRequest = new HttpRequest();
        $uri = 'accounts';
        $httpResponse = $httpRequest->get($uri);
        $this->assertSame('GET', $httpRequest->getMethod());
        $this->assertSame($uri, $httpRequest->getUri());
        $this->assertNull($httpRequest->getBody());
        $this->assertSame($httpRequest, $httpResponse->getRequest());
    }

    public function testPost()
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
