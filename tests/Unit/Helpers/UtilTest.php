<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Helpers\Util;
use Siel\Acumulus\Tests\Data\GetTestData;

/**
 * UtilTest tests the {@see Util} class.
 */
class UtilTest extends TestCase
{
    private Util $util;

    private function getUtil(): Util
    {
        if (!isset($this->util)) {
            $this->util = new Util();
        }
        return $this->util;
    }

    public function testConvertJsonToArray(): void
    {
        $json = (new GetTestData())->getJson(false);
        $array = $this->getUtil()->convertJsonToArray($json);
        self::assertIsArray($array['order']);
        self::assertIsArray($array['customer']);
        self::assertIsArray($array['customer']['invoice_address']);
        self::assertArrayNotHasKey('shipping_address', $array);
    }

//    public function testArrayToList(): void
//    {
//    }

    public function testMaskJson(): void
    {
        $object = (new GetTestData())->getJson();
        $json = $this->getUtil()->convertToJson($object);
        $maskedJson = $this->getUtil()->maskJson($json);
        $this->assertSame($json, $maskedJson);

        $array = $this->getUtil()->convertJsonToArray($json);
        $array['customer']['mypassword'] = 'secret';
        $array['address']['mypassword'] = 'secret';
        $json = $this->getUtil()->convertToJson($array);
        $maskedJson = $this->getUtil()->maskJson($json);
        $this->assertStringContainsString('"mypassword":"REMOVED FOR SECURITY"', $maskedJson);
        $maskedArray = $this->getUtil()->convertJsonToArray($maskedJson);
        self::assertSame('REMOVED FOR SECURITY', $maskedArray['customer']['mypassword']);
        self::assertSame('REMOVED FOR SECURITY', $maskedArray['address']['mypassword']);
    }

//    public function testConvertArrayToXml(): void
//    {
//    }
//
//    public function testConvertHtmlToPlainText(): void
//    {
//    }
//
//    public function testMaskXmlOrJsonString(): void
//    {
//    }

    public function testMaskArray(): void
    {
        $object = (new GetTestData())->getJson();
        $json = $this->getUtil()->convertToJson($object);
        $array = $this->getUtil()->convertJsonToArray($json);
        $array['customer']['mypassword'] = 'secret';
        $array['address']['mypassword'] = 'secret';
        $maskedArray = $this->getUtil()->maskArray($array);
        self::assertSame('REMOVED FOR SECURITY', $maskedArray['customer']['mypassword']);
        self::assertSame('REMOVED FOR SECURITY', $maskedArray['address']['mypassword']);
    }

    public function testMaskHtml(): void
    {
        $html = (new GetTestData())->getHtml();
        $maskedHtml = $this->getUtil()->maskHtml($html);
        self::assertStringContainsString('value="REMOVED FOR SECURITY"', $maskedHtml);
    }

//    public function testConvertXmlToArray(): void
//    {
//    }
//
//    public function testMaskXml(): void
//    {
//    }
}
