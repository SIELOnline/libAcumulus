<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

namespace Siel\Acumulus\Unit\Invoice;

use RuntimeException;
use Siel\Acumulus\Api;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\TestWebShop\Invoice\TestAcumulusObject;

class AcumulusObjectTest extends TestCase
{
    public function testConstructor1()
    {
        $o = new TestAcumulusObject();
        $this->assertNull($o->itemNumber);
        $this->assertNull($o->nature);
        $this->assertNull($o->unitPrice);
    }

    public function testConstructor2()
    {
        $this->expectException(RuntimeException::class);
        $o = new TestAcumulusObject();
        /** @noinspection PhpUndefinedFieldInspection */
        $this->assertNull($o->notExisting);
    }

    public function testSetValues()
    {
        $o = new TestAcumulusObject();

        $value = 'PRD0001';
        $o->itemNumber = $value;
        $this->assertEquals($value, $o->itemNumber);

        $value = Api::Nature_Product;
        $o->nature = $value;
        $this->assertEquals($value, $o->nature);

        $value = 19.99;
        $o->unitPrice = $value;
        $this->assertEquals($value, $o->unitPrice);
    }

    public function testUnsetValue()
    {
        $o = new TestAcumulusObject();

        $this->assertFalse(isset($o->itemNumber));

        $value = 'PRD0001';
        $o->itemNumber = $value;
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertTrue(isset($o->itemNumber));

        unset($o->itemNumber);
        $this->assertNull($o->itemNumber);
        $this->assertFalse(isset($o->itemNumber));
    }
}
