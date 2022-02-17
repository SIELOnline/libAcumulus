<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

namespace Siel\Acumulus\Unit\ApiClient;

use LogicException;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\ApiClient\HttpRequest;
use Siel\Acumulus\ApiClient\HttpResponse;

class HttpRequestTest extends TestCase
{
    private $httpResponseStub;

    protected function setUp(): void
    {
    }

    /**
     * @return \Siel\Acumulus\ApiClient\HttpRequest
     */
    protected function getHttpRequestStub(): HttpRequest
    {
        $this->httpResponseStub = $this->createStub(HttpResponse::class);

        $stub = $this->getMockBuilder(HttpRequest::class)
            ->onlyMethods(['executeWithCurl'])
            ->getMock();
        $stub->method('executeWithCurl')
            ->willReturn($this->httpResponseStub);
        return $stub;
    }

    public function testBefore()
    {
        $httpRequest = $this->getHttpRequestStub();
        $this->assertNull($httpRequest->getMethod(), 'method not null');
        $this->assertNull($httpRequest->getUri(), 'uri not null');
        $this->assertNull($httpRequest->getBody(), 'body not null');
    }

    public function testGet()
    {
        $httpRequest = $this->getHttpRequestStub();
        $uri = 'http://localhost/lib-acumulus/readme.md';
        $httpResponse = $httpRequest->get($uri);
        $this->assertSame('GET', $httpRequest->getMethod());
        $this->assertSame($uri, $httpRequest->getUri());
        $this->assertNull($httpRequest->getBody());
        $this->assertSame($this->httpResponseStub, $httpResponse);
    }

    public function testExecuteTwiceNotAllowed()
    {
        $this->expectException(LogicException::class);
        $httpRequest = $this->getHttpRequestStub();
        $uri = 'http://localhost/lib-acumulus/readme.md';
        $httpRequest->get($uri);
        $httpRequest->get($uri);
    }

    public function testPost()
    {
        $httpRequest = $this->getHttpRequestStub();
        $uri = 'http://localhost/lib-acumulus/readme.md';
        $post = ['my_post' => 'my_value'];
        $httpResponse = $httpRequest->post($uri, $post);
        $this->assertSame('POST', $httpRequest->getMethod());
        $this->assertSame($uri, $httpRequest->getUri());
        $this->assertSame($post, $httpRequest->getBody());
        $this->assertSame($this->httpResponseStub, $httpResponse);
    }
}
