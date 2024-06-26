<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection DuplicatedCode
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\TestWebShop\Data\ComplexTestObject;
use Siel\Acumulus\TestWebShop\Data\SimpleTestObject;

/**
 * Tests for the array access interface part of the
 * {@see \Siel\Acumulus\Data\AcumulusObject} class.
 */
class AcumulusObjectArrayAccessTest extends TestCase
{
    private const itemNumber = 'itemNumber';
    private const nature = 'nature';
    private const unitPrice = 'unitPrice';

    public function testIsset(): void
    {
        $ao = new SimpleTestObject();
        $this->assertFalse(isset($ao[self::itemNumber]));
        $this->assertFalse(isset($ao[self::nature]));
        $this->assertFalse(isset($ao[self::unitPrice]));
    }

    public function testGetEmpty(): void
    {
        $ao = new SimpleTestObject();
        $this->assertNull($ao[self::itemNumber]);
        $this->assertNull($ao[self::nature]);
        $this->assertNull($ao[self::unitPrice]);
    }

    public function testSetThenGetThenUnset(): void
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

    /**
     * @noinspection UnsupportedStringOffsetOperationsInspection
     */
    public function testComplex(): void
    {
        $value1 = 'PRD0001';
        $value2 = Api::Nature_Product;
        $value3 = 19.99;
        $value4 = 'meta';
        $ao = new ComplexTestObject();

        $ao[self::itemNumber] = $value1;
        $this->assertSame($value1, $ao[self::itemNumber]);

        $ao['simple'] = new SimpleTestObject();
        $ao['simple'][self::nature] = $value2;
        $this->assertSame($value2, $ao['simple'][self::nature]);

        $ao['list'][] = new SimpleTestObject();
        $ao['list'][0][self::unitPrice] = $value3;
        $this->assertSame($value3, $ao['list'][0][self::unitPrice]);

        $ao['meta'] = $value4;
        $this->assertSame($value4, $ao['meta']);
    }
}
