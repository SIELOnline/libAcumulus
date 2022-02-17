<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

namespace Siel\Acumulus\Unit\ApiClient;

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
class AcumulusMaskPasswordsTest extends TestCase
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

    /**
     * @todo: move to anther test and/or delete if no longer needed.
     */
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
