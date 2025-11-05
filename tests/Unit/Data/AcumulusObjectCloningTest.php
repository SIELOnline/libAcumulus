<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Data;

use Siel\Acumulus\Api;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\TestWebShop\Data\ComplexTestObject;
use Siel\Acumulus\TestWebShop\Data\SimpleTestObject;

/**
 * Tests the clone operations of {@see \Siel\Acumulus\Data\AcumulusObject}s
 */
class AcumulusObjectCloningTest extends TestCase
{
    public function testCloneSimple(): void
    {
        $ao1 = new SimpleTestObject();
        $ao1->itemNumber = 'I1';
        $ao1->nature = Api::Nature_Product;
        $ao1->unitPrice = 19.99;

        $ao2 = clone $ao1;
        $ao2->itemNumber = 'I3';
        $ao2->nature = Api::Nature_Service;
        $ao2->unitPrice = 50.55;

        self::assertSame('I1', $ao1->itemNumber);
        self::assertSame(Api::Nature_Product, $ao1->nature);
        self::assertSame(19.99, $ao1->unitPrice);
    }

    public function testCloneComplex(): void
    {
        $ao1 = new SimpleTestObject();
        $ao1->itemNumber = 'I1';
        $ao1->nature = Api::Nature_Product;
        $ao1->unitPrice = 19.99;

        $ao2 = new SimpleTestObject();
        $ao2->itemNumber = 'I3';
        $ao2->nature = Api::Nature_Service;
        $ao2->unitPrice = 50.55;

        $ao3 = new SimpleTestObject();
        $ao3->itemNumber = 'I5';
        $ao3->nature = null;
        $ao3->unitPrice = 99.99;

        $cao1 = new ComplexTestObject();
        $cao1->itemNumber = 'I7';
        $cao1->date = '2024-12-01';
        $cao1->simple = $ao1;
        $cao1->list = [$ao2, $ao3];

        $cao2 = clone $cao1;
        self::assertNotSame($cao1->date, $cao2->date);
        self::assertNotSame($cao1->simple, $cao2->simple);
        self::assertNotSame($cao1->list, $cao2->list);

        $cao2->itemNumber = 'I2';
        $cao2->date = '2024-12-05';
        $cao2->simple->itemNumber = 'I4';
        $cao2->list[0]->itemNumber = 'I6';
        $cao2->list[1]->itemNumber = 'I8';
        self::assertSame('I1', $ao1->itemNumber);
        self::assertSame('I3', $ao2->itemNumber);
        self::assertSame('I5', $ao3->itemNumber);
        self::assertSame('I7', $cao1->itemNumber);
        self::assertSame('2024-12-01', $cao1->date->format('Y-m-d'));
    }
}
