<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection DuplicatedCode
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Data;

use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Data\MetadataValue;

use Stringable;

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
        $this->assertNull($mdv->getApiValue());

        $mdv = new MetadataValue(true);
        $this->assertSame(0, $mdv->count());
        $this->assertEqualsCanonicalizing([], $mdv->get());
        $this->assertSame('[]', $mdv->getApiValue());
    }

    public function testSingleValue(): void
    {
        $value1 = 2;

        $mdv = (new MetadataValue())->add($value1);
        $this->assertSame(1, $mdv->count());
        $this->assertSame($value1, $mdv->get());
        $this->assertSame($value1, $mdv->get(0));
        $this->assertNull($mdv->get(1));
        $this->assertSame($value1, $mdv->getApiValue());

        $mdv = (new MetadataValue(true))->add($value1);
        $this->assertSame(1, $mdv->count());
        $this->assertSame([$value1], $mdv->get());
        $this->assertSame($value1, $mdv->get(0));
        $this->assertNull($mdv->get(1));
        $this->assertSame("[$value1]", $mdv->getApiValue());
    }

    public function testNullValue(): void
    {
        $value1 = null;

        $mdv = (new MetadataValue())->add($value1);
        $this->assertSame(1, $mdv->count());
        $this->assertNull($mdv->get());
        $this->assertNull($mdv->get(0));
        $this->assertNull($mdv->get(1));
        $this->assertNull($mdv->getApiValue());
    }

    public function testMultipleValues(): void
    {
        $value1 = 'value1';
        $value2 = 2;

        $mdv = (new MetadataValue())->add($value1)->add($value2);
        $this->assertSame(2, $mdv->count());
        $this->assertSame([$value1, $value2], $mdv->get());
        // Note that this might fail depending on pretty print settings of json_encode().
        $this->assertSame(2, $mdv->get(1));
        $this->assertNull($mdv->get(2));
        $this->assertSame("['value1',2]", $mdv->getApiValue());

        $mdv = (new MetadataValue(true))->add($value1)->add($value2);
        $this->assertSame(2, $mdv->count());
        $this->assertSame([$value1, $value2], $mdv->get());
        // Note that this might fail depending on pretty print settings of json_encode().
        $this->assertSame(2, $mdv->get(1));
        $this->assertNull($mdv->get(2));
        $this->assertSame("['value1',2]", $mdv->getApiValue());
    }

    /**
     * Note that we test with both DateTime and DateTimeImmutable.
     */
    public static function getApiValueDataProvider(): array
    {
        return [
            [2, 2],
            [null, null],
            [true, true],
            [1.23, 1.23],
            ['2', 2],
            ['test', 'test'],
            [['test1', 'test2'], "['test1','test2']"],
            [new DateTime('2023-05-04'), '2023-05-04'],
            [new DateTimeImmutable('2023-05-04 13:14:15'), '2023-05-04 13:14:15'],
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

    public function testGetApiValueStringable(): void
    {
        $stringableClass = new class (1, 2, 3, 4) implements Stringable {
            public function __construct(private int $oct1, private int $oct2, private int $oct3, private int $oct4)
            {
            }

            public function __toString(): string
            {
                return "$this->oct1.$this->oct2.$this->oct3.$this->oct4";
            }
        };
        $notStringableClass = new class (1, 2, 3, 4) {
            /** @noinspection PhpPropertyOnlyWrittenInspection */
            public function __construct(public int $oct1, public int $oct2, private int $oct3, private int $oct4)
            {
            }
        };

        $mdv = new MetadataValue();
        $mdv->add($stringableClass);
        $this->assertSame('1.2.3.4', $mdv->getApiValue());

        $mdv = new MetadataValue();
        $mdv->add($notStringableClass);
        $this->assertSame("{'oct1':1,'oct2':2}", $mdv->getApiValue());
    }
}
