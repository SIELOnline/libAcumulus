<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Data;

use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Data\MetadataValue;
use stdClass;
use Stringable;

/**
 * Tests for the {@see MetadataValue} class.
 */
class MetadataValueTest extends TestCase
{
    public function testEmpty(): void
    {
        $mdv = new MetadataValue();
        static::assertSame(0, $mdv->count());
        static::assertSame(null, $mdv->get());
        static::assertNull($mdv->get(0));
        static::assertSame(null, $mdv->getApiValue());

        $mdv = new MetadataValue(MetadataValue::List);
        static::assertSame(0, $mdv->count());
        static::assertEqualsCanonicalizing([], $mdv->get());
        static::assertNull($mdv->get(0));
        static::assertSame('[]', $mdv->getApiValue());
    }

    public function testSingleValue(): void
    {
        $value1 = 2;

        $mdv = (new MetadataValue())->add($value1);
        static::assertSame(1, $mdv->count());
        static::assertSame($value1, $mdv->get());
        static::assertSame($value1, $mdv->get(0));
        static::assertNull($mdv->get(1));
        static::assertSame($value1, $mdv->getApiValue());

        $mdv = (new MetadataValue(MetadataValue::List))->add($value1);
        static::assertSame(1, $mdv->count());
        static::assertSame([$value1], $mdv->get());
        static::assertSame($value1, $mdv->get(0));
        static::assertNull($mdv->get(1));
        static::assertSame("[$value1]", $mdv->getApiValue());
    }

    public function testNullValue(): void
    {
        $value1 = null;

        $mdv = (new MetadataValue())->add($value1);
        static::assertSame(1, $mdv->count());
        static::assertNull($mdv->get());
        static::assertNull($mdv->get(0));
        static::assertNull($mdv->get(1));
        static::assertNull($mdv->getApiValue());

        $mdv = (new MetadataValue(MetadataValue::List))->add($value1);
        static::assertSame(1, $mdv->count());
        static::assertSame([null], $mdv->get());
        static::assertNull($mdv->get(0));
        static::assertNull($mdv->get(1));
        static::assertSame('[null]', $mdv->getApiValue());
    }

    public function testEmptyArray(): void
    {
        $value = [];

        $mdv = (new MetadataValue())->add($value);
        static::assertSame(0, $mdv->count());
        static::assertEqualsCanonicalizing([], $mdv->get());
        static::assertNull($mdv->get(0));
        static::assertNull($mdv->get(1));
        static::assertSame('[]', $mdv->getApiValue());

        $mdv = (new MetadataValue(MetadataValue::List))->add($value);
        static::assertSame(0, $mdv->count());
        static::assertEqualsCanonicalizing([], $mdv->get());
        static::assertNull($mdv->get(0));
        static::assertNull($mdv->get(1));
        static::assertSame('[]', $mdv->getApiValue());
    }

    public function testMultipleValues(): void
    {
        $value1 = 'value1';
        $value2 = 2;

        $mdv = (new MetadataValue())->add($value1)->add($value2);
        static::assertSame(2, $mdv->count());
        static::assertSame([$value1, $value2], $mdv->get());
        // Note that this might fail depending on pretty print settings of json_encode().
        static::assertSame(2, $mdv->get(1));
        static::assertNull($mdv->get(2));
        static::assertSame("['value1',2]", $mdv->getApiValue());

        $mdv = (new MetadataValue(MetadataValue::List))->add($value1)->add($value2)->add($value1);
        static::assertSame(3, $mdv->count());
        static::assertSame([$value1, $value2, $value1], $mdv->get());
        // Note that this might fail depending on pretty print settings of json_encode().
        static::assertSame(2, $mdv->get(1));
        static::assertNull($mdv->get(3));
        static::assertSame("['value1',2,'value1']", $mdv->getApiValue());

        $mdv = (new MetadataValue(MetadataValue::Unique))->add($value1)->add($value2)->add($value1)->add($value2);
        static::assertSame(2, $mdv->count());
        static::assertSame([$value1, $value2], $mdv->get());
        // Note that this might fail depending on pretty print settings of json_encode().
        static::assertSame(2, $mdv->get(1));
        static::assertNull($mdv->get(2));
        static::assertSame("['value1',2]", $mdv->getApiValue());
    }

    public function testAddArray(): void
    {
        $value1 = 'value1';
        $value2 = 2;
        $value3 = 'value3';
        $value4 = '4';
        $valueN1 = [$value1, $value2];
        $valueN2 = [$value3, $value4];
        $mdv = (new MetadataValue())->add($valueN1);
        static::assertSame(2, $mdv->count());
        static::assertSame([$value1, $value2], $mdv->get());
        static::assertSame(2, $mdv->get(1));
        static::assertNull($mdv->get(2));
        static::assertSame("['value1',2]", $mdv->getApiValue());
        // Add 2nd array.
        $mdv->add($valueN2);
        static::assertSame(4, $mdv->count());
        static::assertSame([$value1, $value2, $value3, 4], $mdv->get());
        static::assertSame(2, $mdv->get(1));
        static::assertSame(4, $mdv->get(3));
        static::assertNull($mdv->get(4));
        static::assertSame("['value1',2,'value3',4]", $mdv->getApiValue());


        $valueK1 = ['a' => $value1, 'b' => $value2];
        $valueK2 = ['c' => $value3, 'd' => $value4];
        $mdv = (new MetadataValue())->add($valueK1);
        static::assertSame(1, $mdv->count());
        static::assertSame($valueK1, $mdv->get());
        static::assertNull($mdv->get(1));
        static::assertSame("{'a':'value1','b':2}", $mdv->getApiValue());
        // Add 2nd array.
        $mdv->add($valueK2);
        static::assertSame(2, $mdv->count());
        static::assertSame([$valueK1, ['c' => $value3, 'd' => 4]], $mdv->get());
        static::assertSame(['c' => $value3, 'd' => 4], $mdv->get(1));
        static::assertNull($mdv->get(2));
        static::assertSame("[{'a':'value1','b':2},{'c':'value3','d':4}]", $mdv->getApiValue());

        $value = [[$value1, $value2]];
        $mdv = (new MetadataValue(MetadataValue::Single))->add($value);
        static::assertSame(1, $mdv->count());
        static::assertSame([$value1, $value2], $mdv->get());
        static::assertNull($mdv->get(1));
        static::assertSame("['value1',2]", $mdv->getApiValue());

        $mdv = (new MetadataValue(MetadataValue::List))->add($value);
        static::assertSame(1, $mdv->count());
        static::assertSame([[$value1, $value2]], $mdv->get());
        static::assertNull($mdv->get(1));
        static::assertSame("[['value1',2]]", $mdv->getApiValue());
    }

    public function testClone(): void
    {
        $value1 = 'value1';
        $value2 =  new stdClass();
        $value2->s2 = 'value2';
        $value2->s3 = 'value3';
        $mdv = (new MetadataValue())->add($value1)->add($value2);
        $clone = clone $mdv;
        static::assertSame($mdv->get(0), $clone->get(0));
        static::assertNotSame($mdv->get(1), $clone->get(1));
        static::assertEquals($mdv->get(1), $clone->get(1));
        $value2->s4 = 'value4';
        $value2->s3 = 'value5';
        static::assertSame($value2, $mdv->get(1));
        static::assertNotSame($mdv->get(1), $clone->get(1));
        static::assertNotEquals($mdv->get(1), $clone->get(1));
    }

    /**
     * Note that we test with both DateTime and DateTimeImmutable.
     */
    public static function getSingleValueDataProvider(): array
    {
        return [
            [2, 2],
            [null, null],
            [true, true],
            [false, false],
            ['null', null],
            ['true', true],
            ['false', false],
            [1.23, 1.23],
            ['2', 2],
            ['+33612345678', '+33612345678'],
            ['00 33 6 12 34 56 78', '00 33 6 12 34 56 78'],
            ['test', 'test'],
            [['test1', 'test2'], "['test1','test2']"],
            [new DateTime('2023-05-04'), '2023-05-04'],
            [new DateTimeImmutable('2023-05-04 13:14:15'), '2023-05-04 13:14:15'],
        ];
    }

    #[DataProvider('getSingleValueDataProvider')]
    public function testGetSingleApiValue($value, $expected): void
    {
        $mdv = new MetadataValue();
        $mdv->add($value);
        static::assertSame($expected, $mdv->getApiValue());
    }

    /**
     * Note that we test with both DateTime and DateTimeImmutable.
     */
    public static function getMultiValueDataProvider(): array
    {
        return [
            [
                [
                    2,
                    null,
                    'true',
                    1.23,
                    '2',
                    'test',
                    ['date' => new DateTime('2023-05-04'), 'time' => new DateTimeImmutable('2023-05-04 13:14:15')],
                    self::getStringableObject(),
                    self::getNonStringableObject(),
                ],
                "[2,null,true,1.23,2,'test',{'date':'2023-05-04','time':'2023-05-04 13:14:15'},'1.2.3.4',{'oct1':1,'oct2':2}]",
            ],
            [
                [
                    ['level2' => ['level' => 2, 'level3' => ['level' =>3, 'level4' => ['level' => 4, 'level5' => [5]]]]],
                    ['level2' => ['level' => 2, 'level3' => ['level' =>3, 'level4' => self::getNonStringableObject()]]],
                ],
                "[{'level2':{'level':2,'level3':{'level':3,'level4':'array'}}},{'level2':{'level':2,'level3':{'level':3,'level4':'class@anonymous'}}}]",
            ],
        ];
    }

    #[DataProvider('getMultiValueDataProvider')]
    public function testGetMultiApiValue($value, $expected): void
    {
        $mdv = new MetadataValue();
        $mdv->add($value);
        static::assertSame($expected, $mdv->getApiValue());
    }

    public function testGetApiValueStringable(): void
    {
        $stringableClass = self::getStringableObject();
        $notStringableClass = self::getNonStringableObject();

        $mdv = new MetadataValue();
        $mdv->add($stringableClass);
        static::assertSame('1.2.3.4', $mdv->getApiValue());

        $mdv = new MetadataValue();
        $mdv->add($notStringableClass);
        static::assertSame("{'oct1':1,'oct2':2}", $mdv->getApiValue());
    }

    private static function getStringableObject(): Stringable
    {
        return new class (1, 2, 3, 4) implements Stringable {
            public function __construct(
                private readonly int $oct1,
                private readonly int $oct2,
                private readonly int $oct3,
                private readonly int $oct4
            ) {
            }

            public function __toString(): string
            {
                return "$this->oct1.$this->oct2.$this->oct3.$this->oct4";
            }
        };
    }

    private static function getNonStringableObject(): object
    {
        return new class (1, 2, 3, 4) {
            /** @noinspection PhpPropertyOnlyWrittenInspection */
            public function __construct(public int $oct1, public int $oct2, private readonly int $oct3, private readonly int $oct4)
            {
            }
        };
    }
}
