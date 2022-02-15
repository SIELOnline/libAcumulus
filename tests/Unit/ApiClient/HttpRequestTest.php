<?php
/** @noinspection PhpStaticAsDynamicMethodCallInspection */

namespace Siel\Acumulus\Unit\ApiClient;

use LogicException;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\ApiClient\HttpRequest;
use Siel\Acumulus\ApiClient\HttpResponse;

class HttpRequestTest extends TestCase
{
    protected $httpResponseStub;

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
        $this->assertEquals('GET', $httpRequest->getMethod());
        $this->assertEquals($uri, $httpRequest->getUri());
        $this->assertNull($httpRequest->getBody());
        $this->assertEquals($this->httpResponseStub, $httpResponse);
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
        $this->assertEquals('POST', $httpRequest->getMethod());
        $this->assertEquals($uri, $httpRequest->getUri());
        $this->assertEquals($post, $httpRequest->getBody());
        $this->assertEquals($this->httpResponseStub, $httpResponse);
    }

    public function dataPostFields(): array
    {
        $msg = '<?xml version="1.0" encoding="utf-8" standalone="yes"?>
<acumulus>
  <format>json</format>
  <testmode>0</testmode>
  <lang>nl</lang>
  <connector>
    <application>WooCommerce 4.0.1 (WordPress: 5.4)</application>
    <webkoppel>Acumulus 5.9.0</webkoppel>
    <development>SIEL - Buro RaDer</development>
    <remark>Library 5.10.0-alpha1 - PHP 7.1.33</remark>
    <sourceuri>https://www.siel.nl/</sourceuri>
  </connector>
</acumulus>';

        $postFields = [
            'option1' => 1,
            'option2' => 2,
            'text3' => 'Hello "World"',
        ];

        return [
            'array-1-entry' => [
                'postFields' => ['xmlstring' => $msg],
                'msg' => $msg,
            ],
            'array-multiple-entries' => [
                'postFields' => $postFields,
                'msg' => json_encode($postFields),
            ],
            'string' => [
                'postFields' => 'option1=1&option2=2&text3=Hello%20"World"',
                'msg' => 'option1=1&option2=2&text3=Hello%20"World"',
            ],
        ];
    }
}
