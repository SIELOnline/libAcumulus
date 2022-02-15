<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

namespace Siel\Acumulus\Unit\ApiClient;

use Siel\Acumulus\ApiClient\AcumulusRequest;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\ApiClient\HttpRequest;

/**
 * Features to test with the ApiCommunicator:
 * - convertXmlToArray
 * - convertArrayToXml (convertToDom)
 * - isHtmlResponse
 * - callApiFunction
 * - getUri
 */
class AcumulusRequestTest extends TestCase
{
//    public function testGetUri()
//    {
//
//    }
//
//    public function testCallApiFunction()
//    {
//
//    }

    public function testMaskPasswords()
    {
        $httpRequest = new HttpRequest(curl_init());
        $post = ['accounts' =>
            '<?xml version="1.0" encoding="utf-8" standalone="yes"?>
<myxml>
    <username>APIGebruiker12345</username>
    <password>mysecret</password><password>myse&lt;cret"</password>
    <my1-password>mysecret"</my1-password>
    <my2-password>myse"\&lt;&lt;cret"</my2-password>
    <emailonerror>erwin@burorader.com</emailonerror>
</myxml>'];
        $uri = 'test';
        $httpRequest->post($uri, $post);
        $msg = $httpRequest->getPostFieldsAsMsg();
        $this->assertStringNotContainsString('<password>mysecret</password>', $msg);
        $this->assertStringNotContainsString('<mypassword>myse&lt;cret</mypassword>', $msg);
        $this->assertStringNotContainsString('<my1-password>mysecret"</my1-password>', $msg);
        $this->assertStringNotContainsString('<my2-password>myse"\&lt;cret"</my2-password>', $msg);
        $this->assertStringContainsString('<password>REMOVED FOR SECURITY</password>', $msg);
        $this->assertStringContainsString('<password>REMOVED FOR SECURITY</password><password>REMOVED FOR SECURITY</password>', $msg);
        $this->assertStringContainsString('<my1-password>REMOVED FOR SECURITY</my1-password>', $msg);
        $this->assertStringContainsString('<my2-password>REMOVED FOR SECURITY</my2-password>', $msg);

        $post = '
{
"username": "APIGebruiker12345",
"password": "mysecret",
"my1-password": "myse\"/<cret",
"my2-password": "my\"secret",
"my3-password": "myse\\\\\\"/<cret",
"emailonerror": "erwin@burorader.com"
}';
        $httpRequest->post($uri, $post);
        $msg = $httpRequest->getPostFieldsAsMsg();
        $this->assertStringNotContainsString('"password": "mysecret"', $msg);
        $this->assertStringNotContainsString('"my1-password": "myse\"/<cret"', $msg);
        $this->assertStringNotContainsString('"my2-password": "my\"secret"', $msg);
        $this->assertStringNotContainsString('"my3-password": "myse\\\\\\"/<cret"', $msg);
        $this->assertStringContainsString('"password": "REMOVED FOR SECURITY",', $msg);
        $this->assertStringContainsString('"my1-password": "REMOVED FOR SECURITY",', $msg);
        $this->assertStringContainsString('"my2-password": "REMOVED FOR SECURITY",', $msg);
        $this->assertStringContainsString('"my3-password": "REMOVED FOR SECURITY",', $msg);
    }
}
