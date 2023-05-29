<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection DuplicatedCode
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Data;

use DateTime;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Data\MetadataValue;

use function is_array;

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
        $this->assertSame('null', $mdv->getApiValue());
    }

    public function test1Value(): void
    {
        $value1 = 'value1';
        $value2 = 2;

        $mdv = new MetadataValue($value1);
        $this->assertSame(1, $mdv->count());
        $this->assertSame($value1, $mdv->get());
        $this->assertSame($value1, $mdv->getApiValue());

        $mdv = new MetadataValue($value2);
        $this->assertSame($value2, $mdv->get());
        $this->assertSame($value2, $mdv->getApiValue());
    }

    public function testNullValue(): void
    {
        $value1 = null;

        $mdv = new MetadataValue($value1);
        $this->assertSame(1, $mdv->count());
        $this->assertNull($mdv->get());
        $this->assertSame('null', $mdv->getApiValue());
    }

    public function testMultipleValues(): void
    {
        $value1 = 'value1';
        $value2 = 2;

        $mdv = new MetadataValue($value1, $value2);
        $this->assertSame(2, $mdv->count());
        $this->assertSame([$value1, $value2], $mdv->get());
        // Note that this might fail depending on pretty print settings of json_encode
        $this->assertSame("['value1',2]", $mdv->getApiValue());
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
        $this->assertSame("['value1',2]", $mdv->getApiValue());

        $mdv = new MetadataValue();
        $mdv->add($value1);
        $mdv->add($value2);
        $this->assertSame(2, $mdv->count());
        $this->assertSame([$value1, $value2], $mdv->get());
        // Note that this might fail depending on pretty print settings of json_encode
        $this->assertSame("['value1',2]", $mdv->getApiValue());
    }

    public function getApiValueDataProvider(): array
    {
        return [
            [2, 2],
            [null, 'null'],
            [true, true],
            [1.23, 1.23],
            ['2', '2'],
            ['test', 'test'],
            [['test1', 'test2'], "['test1','test2']"],
            [new DateTime('2023-05-04'), '2023-05-04'],
            [new DateTime('2023-05-04 13:14:15'), '2023-05-04 13:14:15'],
        ];
    }

    /**
     * @dataProvider getApiValueDataProvider
     */
    public function testGetApiValue($value, $expected): void
    {
        $mdv = new MetadataValue();
        if (is_array($value)) {
            foreach ($value as $item) {
                $mdv->add($item);
            }
        } else {
            $mdv->add($value);
        }
        $this->assertSame($expected, $mdv->getApiValue());
    }
}
