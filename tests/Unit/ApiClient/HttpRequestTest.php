<?php
/** @noinspection PhpStaticAsDynamicMethodCallInspection */

namespace Siel\Acumulus\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Siel\Acumulus\ApiClient\HttpRequest;

class HttpRequestTest extends TestCase
{
    private $connection;

    protected function setUp(): void
    {
        $this->connection = curl_init();
    }

    public function testGet()
    {
        $httpRequest = new HttpRequest($this->connection);
        $uri = 'http://localhost/lib-acumulus/readme.md';
        $this->assertEquals('GET', $httpRequest->getMethod());
        $this->assertEquals($uri, $httpRequest->getUri());
        $this->assertNull($httpRequest->getPostFields());
    }

    public function testPost()
    {
        $httpRequest = new HttpRequest($this->connection);
        $post = ['my_post' => 'my_value'];
        $uri = 'http://localhost/lib-acumulus/readme.md';
        $httpRequest->post($uri, $post);
        $this->assertEquals('POST', $httpRequest->getMethod());
        $this->assertEquals($uri, $httpRequest->getUri());
        $this->assertEquals($post, $httpRequest->getPostFields());
    }

    public function testExecute()
    {
        $httpRequest = new HttpRequest($this->connection);
        $post = ['my_post' => 'my_value'];
        $uri = 'http://localhost/lib-acumulus/readme.md';
        $httpResponse = $httpRequest->post($uri, $post)->execute();
        $this->assertEquals($httpRequest, $httpResponse->getRequest());
    }

    public function testExecuteBeforeSettingMethod()
    {
        $httpRequest = new HttpRequest($this->connection);
        $this->expectException(RuntimeException::class);
        $httpRequest->execute();
    }

    public function testExecuteTwice()
    {
        $httpRequest = new HttpRequest($this->connection);
        $this->expectException(RuntimeException::class);
        $uri = 'http://localhost/lib-acumulus/readme.md';
        $httpRequest->get($uri)->execute();
        $httpRequest->execute();
    }

    public function dataPostFields()
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

    /**
     * @dataProvider dataPostFields
     *
     * @param array|string $postFields
     * @param string $msg
     */
    public function testGetPostFieldsAsMsg($postFields, string $msg)
    {
        $httpRequest = new HttpRequest($this->connection);
        $httpRequest->post('test', $postFields);
        $this->assertEquals($msg, $httpRequest->getPostFieldsAsMsg());
    }
}
