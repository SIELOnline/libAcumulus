<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

namespace Siel\Acumulus\Unit\Data;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\TestWebShop\Data\SimpleTestObject;

class AcumulusObjectArrayAccessTest extends TestCase
{
    private const itemNumber = 'itemNumber';
    private const nature = 'nature';
    private const unitPrice = 'unitPrice';

    public function testIsset()
    {
        $ao = new SimpleTestObject();
        $this->assertFalse(isset($ao[self::itemNumber]));
        $this->assertFalse(isset($ao[self::nature]));
        $this->assertFalse(isset($ao[self::unitPrice]));
    }

    public function testGetEmpty()
    {
        $ao = new SimpleTestObject();
        $this->assertNull($ao[self::itemNumber]);
        $this->assertNull($ao[self::nature]);
        $this->assertNull($ao[self::unitPrice]);
    }

    public function testSetThenGetThenUnset()
    {
        $value1 = 'PRD0001';
        $value2 = Api::Nature_Product;
        $value3 = 19.99;
        $ao = new SimpleTestObject();

        $ao[self::itemNumber] = $value1;
        $this->assertSame($value1, $ao[self::itemNumber]);

        $ao->nature = $value2;
        $this->assertSame($value2, $ao[self::nature]);

        $ao->setUnitPrice($value3);
        $this->assertSame($value3, $ao[self::unitPrice]);

        unset($ao[self::itemNumber]);
        $this->assertNull($ao[self::itemNumber]);

        unset($ao->nature);
        $this->assertNull($ao[self::nature]);
    }
}