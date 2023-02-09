<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection DuplicatedCode
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Data\MetadataValue;

/**
 * Tests for the {@see MetadataValue} class.
 */
class MetadataValueTest extends TestCase
{
    public function testEmpty(): void
    {
        $mdv = new MetadataValue();
        $this->assertSame(0, $mdv->count());
        $this->assertNull($mdv->get());
        $this->assertSame('null', (string) $mdv);
    }

    public function test1Value(): void
    {
        $value1 = 'value1';
        $value2 = 2;

        $mdv = new MetadataValue($value1);
        $this->assertSame(1, $mdv->count());
        $this->assertSame($value1, $mdv->get());
        $this->assertSame($value1, (string) $mdv);

        $mdv = new MetadataValue($value2);
        $this->assertSame($value2, $mdv->get());
        $this->assertSame((string) $value2, (string) $mdv);
    }

    public function testNullValue(): void
    {
        $value1 = null;

        $mdv = new MetadataValue($value1);
        $this->assertSame(1, $mdv->count());
        $this->assertNull($mdv->get());
        $this->assertSame('null', (string) $mdv);
    }

    public function testMultipleValues(): void
    {
        $value1 = 'value1';
        $value2 = 2;

        $mdv = new MetadataValue($value1, $value2);
        $this->assertSame(2, $mdv->count());
        $this->assertSame([$value1, $value2], $mdv->get());
        // Note that this might fail depending on pretty print settings of json_encode
        $this->assertJsonStringEqualsJsonString('["value1",2]', (string) $mdv);
    }

    public function testAdd(): void
    {
        $value1 = 'value1';
        $value2 = 2;

        $mdv = new MetadataValue($value1);
        $mdv->add($value2);
        $this->assertSame(2, $mdv->count());
        $this->assertSame([$value1, $value2], $mdv->get());
        // Note that this might fail depending on pretty print settings of json_encode
        $this->assertJsonStringEqualsJsonString('["value1",2]', (string) $mdv);

        $mdv = new MetadataValue();
        $mdv->add($value1);
        $mdv->add($value2);
        $this->assertSame(2, $mdv->count());
        $this->assertSame([$value1, $value2], $mdv->get());
        // Note that this might fail depending on pretty print settings of json_encode
        $this->assertJsonStringEqualsJsonString('["value1",2]', (string) $mdv);
    }
}
