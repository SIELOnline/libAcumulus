<?php
/**
 * @noinspection PhpMissingDocCommentInspection
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection DuplicatedCode
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Data;

use LogicException;
use RuntimeException;
use Siel\Acumulus\Api;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Data\AcumulusProperty;
use Siel\Acumulus\Tests\TestWebShop\Data\SimpleTestObject;

class AcumulusObjectTest extends TestCase
{
    public function testConstructor1(): void
    {
        $ao = new SimpleTestObject();
        $this->assertNull($ao->itemNumber);
        $this->assertNull($ao->nature);
        $this->assertNull($ao->unitPrice);
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
        $this->assertFalse(isset($ao->itemNumber));

        $value1 = 'PRD0001';
        $ao->itemNumber = $value1;
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertTrue(isset($ao->itemNumber));
        $this->assertSame($value1, $ao->itemNumber);
        $this->assertFalse(isset($ao->nature));
        $this->assertNull($ao->nature);

        $value2 = Api::Nature_Product;
        $ao->nature = $value2;
        $this->assertSame($value2, $ao->nature);

        $value3 = 19.99;
        $ao->unitPrice = $value3;
        $this->assertSame($value3, $ao->unitPrice);
    }

    public function testUnsetProperties(): void
    {
        $ao = new SimpleTestObject();
        $this->assertFalse(isset($ao->itemNumber));

        $value1 = 'PRD0001';
        $ao->itemNumber = $value1;
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertTrue(isset($ao->itemNumber));

        unset($ao->itemNumber);
        $this->assertFalse(isset($ao->itemNumber));
        $this->assertNull($ao->itemNumber);
    }

    public function testSetterAndGetter(): void
    {
        $ao = new SimpleTestObject();
        $this->assertNull($ao->getItemNumber());

        $value1 = 'PRD0001';
        $ao->setItemNumber($value1);
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertSame($value1, $ao->itemNumber);
        $this->assertSame($value1, $ao->getItemNumber());
        $this->assertNull($ao->getNature());

        $value2 = Api::Nature_Product;
        $ao->nature = $value2;
        $this->assertSame($value2, $ao->getNature());
        $this->assertNull($ao->getUnitPrice());

        $value3 = 0.0;
        $ao->setUnitPrice($value3, AcumulusProperty::Set_NotEmpty);
        $this->assertNull($ao->getUnitPrice());

        $ao->setUnitPrice($value3, AcumulusProperty::Set_NotOverwrite);
        $this->assertSame($value3, $ao->getUnitPrice());

        $value4 = 19.99;
        $ao->setUnitPrice($value4, AcumulusProperty::Set_NotEmpty);
        $this->assertSame($value4, $ao->getUnitPrice());

        $value5 = 0.0;
        $ao->setUnitPrice($value5, AcumulusProperty::Set_NotEmpty);
        $this->assertSame($value4, $ao->getUnitPrice());

        $value6 = 1.99;
        $ao->setUnitPrice($value4, AcumulusProperty::Set_NotOverwrite);
        $this->assertSame($value4, $ao->getUnitPrice());

        /** @noinspection PhpRedundantOptionalArgumentInspection */
        $ao->setUnitPrice($value6, AcumulusProperty::Set_Always);
        $this->assertSame($value6, $ao->getUnitPrice());
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
        /** @noinspection PhpUnusedLocalVariableInspection */
        /** @noinspection PhpParamsInspection */
        $v = $ao->setUnitPrice();
    }

    public function testArgumentsException3(): void
    {
        $this->expectException(LogicException::class);
        $ao = new SimpleTestObject();
        /** @noinspection PhpUnusedLocalVariableInspection */
        /** @noinspection PhpMethodParametersCountMismatchInspection */
        $v = $ao->setUnitPrice(19.99, AcumulusProperty::Set_Always, true);
    }
}
