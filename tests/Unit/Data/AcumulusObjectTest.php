<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Data;

use LogicException;
use RuntimeException;
use Siel\Acumulus\Api;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Data\PropertySet;
use Siel\Acumulus\TestWebShop\Data\SimpleTestObject;

/**
 * Tests for the basic operations of the {@see \Siel\Acumulus\Data\AcumulusObject} class.
 */
class AcumulusObjectTest extends TestCase
{
    public function testConstructor1(): void
    {
        $ao = new SimpleTestObject();
        self::assertNull($ao->itemNumber);
        self::assertNull($ao->nature);
        self::assertNull($ao->unitPrice);
    }

    public function testConstructor2(): void
    {
        $this->expectException(RuntimeException::class);
        $ao = new SimpleTestObject();
        /** @noinspection PhpUndefinedFieldInspection */
        /** @noinspection PhpUnusedLocalVariableInspection */
        $v = $ao->notExisting;
    }

    public function testSetAndGetProperties(): void
    {
        $ao = new SimpleTestObject();
        self::assertFalse(isset($ao->itemNumber));

        $value1 = 'PRD0001';
        $ao->itemNumber = $value1;
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        self::assertTrue(isset($ao->itemNumber));
        self::assertSame($value1, $ao->itemNumber);
        self::assertFalse(isset($ao->nature));
        self::assertNull($ao->nature);

        $value2 = Api::Nature_Product;
        $ao->nature = $value2;
        self::assertSame($value2, $ao->nature);

        $value3 = 19.99;
        $ao->unitPrice = $value3;
        self::assertSame($value3, $ao->unitPrice);
    }

    public function testUnsetProperties(): void
    {
        $ao = new SimpleTestObject();
        self::assertFalse(isset($ao->itemNumber));

        $value1 = 'PRD0001';
        $ao->itemNumber = $value1;
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        self::assertTrue(isset($ao->itemNumber));

        unset($ao->itemNumber);
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        self::assertFalse(isset($ao->itemNumber));
        self::assertNull($ao->itemNumber);
    }

    public function testSetterAndGetter(): void
    {
        $ao = new SimpleTestObject();
        self::assertNull($ao->getItemNumber());

        $value1 = 'PRD0001';
        $ao->setItemNumber($value1);
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        self::assertSame($value1, $ao->itemNumber);
        self::assertSame($value1, $ao->getItemNumber());
        self::assertNull($ao->getNature());

        $value2 = Api::Nature_Product;
        $ao->nature = $value2;
        self::assertSame($value2, $ao->getNature());
        self::assertNull($ao->getUnitPrice());

        $value3 = 0.0;
        $ao->setUnitPrice($value3, PropertySet::NotEmpty);
        self::assertNull($ao->getUnitPrice());

        $ao->setUnitPrice($value3, PropertySet::NotOverwrite);
        self::assertSame($value3, $ao->getUnitPrice());

        $value4 = 19.99;
        $ao->setUnitPrice($value4, PropertySet::NotEmpty);
        self::assertSame($value4, $ao->getUnitPrice());

        $value5 = 0.0;
        $ao->setUnitPrice($value5, PropertySet::NotEmpty);
        self::assertSame($value4, $ao->getUnitPrice());

        $value6 = 1.99;
        $ao->setUnitPrice($value4, PropertySet::NotOverwrite);
        self::assertSame($value4, $ao->getUnitPrice());

        /** @noinspection PhpRedundantOptionalArgumentInspection */
        $ao->setUnitPrice($value6, PropertySet::Always);
        self::assertSame($value6, $ao->getUnitPrice());
    }

    public function testArgumentsException1(): void
    {
        $this->expectException(LogicException::class);
        $ao = new SimpleTestObject();
        /** @noinspection PhpUnusedLocalVariableInspection */
        /** @noinspection PhpMethodParametersCountMismatchInspection */
        $v = $ao->getUnitPrice(1);
    }

    public function testArgumentsException2(): void
    {
        $this->expectException(LogicException::class);
        $ao = new SimpleTestObject();
        /** @noinspection PhpParamsInspection */
        $ao->setUnitPrice();
    }

    public function testArgumentsException3(): void
    {
        $this->expectException(LogicException::class);
        $ao = new SimpleTestObject();
        /** @noinspection PhpMethodParametersCountMismatchInspection */
        $ao->setUnitPrice(19.99, PropertySet::Always, true);
    }

    public function testToArray(): void
    {
        $ao = new SimpleTestObject();
        $ao->metadataSet('My_Metadata', 'meta');
        $value1 = 'PRD0001';
        $ao->itemNumber = $value1;
        $value3 = 19.99;
        $ao->unitPrice = $value3;
        $expected = [
            'itemNumber' => 'PRD0001',
            'unitPrice' => 19.99,
            'My_Metadata' => 'meta',
        ];
        self::assertSame($expected, $ao->toArray());

        $value2 = Api::Nature_Product;
        $ao->nature = $value2;
        $expected = [
            'itemNumber' => 'PRD0001',
            'nature' => Api::Nature_Product,
            'unitPrice' => 19.99,
            'My_Metadata' => 'meta',
        ];
        self::assertSame($expected, $ao->toArray());

    }
}
