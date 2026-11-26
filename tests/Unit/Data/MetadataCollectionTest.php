<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Data\MetadataCollection;
use Siel\Acumulus\Data\MetadataValue;
use Siel\Acumulus\Meta;
use stdClass;

/**
 * Tests for the {@see MetadataCollection} class.
 */
class MetadataCollectionTest extends TestCase
{
    public function testMetadataCollectionSetAndAdd(): void
    {
        $name1 = 'my_metadata1';
        $name2 = 'my_metadata2';
        $no_name = 'no_metadata_name';
        $value1 = 'value1';
        $value2 = 2;

        // Test empty.
        $mdc = new MetadataCollection();
        static::assertFalse($mdc->exists($name1));
        static::assertFalse($mdc->exists($no_name));
        static::assertNull($mdc->getMetadataValue($name1));
        static::assertNull($mdc->get($name1));
        static::assertSame([], $mdc->toArray());

        // Test set that creates.
        $mdc->set($name1, $value1);
        static::assertTrue($mdc->exists($name1));
        static::assertFalse($mdc->exists($no_name));
        static::assertInstanceOf(MetadataValue::class, $mdc->getMetadataValue($name1));
        static::assertSame($value1, $mdc->get($name1));
        static::assertSame([$name1], array_keys($mdc->toArray()));

        // Test add that creates.
        $mdc->add($name2, $value2);
        static::assertTrue($mdc->exists($name1));
        static::assertTrue($mdc->exists($name2));
        static::assertFalse($mdc->exists($no_name));
        static::assertInstanceOf(MetadataValue::class, $mdc->getMetadataValue($name2));
        static::assertSame([$value2], $mdc->get($name2));
        static::assertSame([$name1, $name2], array_keys($mdc->toArray()));

        // Test set that overwrites.
        $mdc->set($name1, $value2);
        static::assertTrue($mdc->exists($name1));
        static::assertTrue($mdc->exists($name2));
        $mdv1 = $mdc->getMetadataValue($name1);
        static::assertInstanceOf(MetadataValue::class, $mdv1);
        static::assertSame(1, $mdv1->count());
        static::assertSame($value2, $mdc->get($name1));

        // Test add that adds.
        $mdc->add($name1, $value1);
        static::assertTrue($mdc->exists($name1));
        static::assertTrue($mdc->exists($name2));
        $mdv1 = $mdc->getMetadataValue($name1);
        static::assertInstanceOf(MetadataValue::class, $mdv1);
        static::assertSame(2, $mdv1->count());
        static::assertSame([$value2, $value1], $mdc->get($name1));

        // Test remove.
        $mdc->remove($name2);
        static::assertTrue($mdc->exists($name1));
        static::assertFalse($mdc->exists($name2));
        static::assertNull($mdc->getMetadataValue($name2));
        static::assertNull($mdc->get($name2));
        $mdv1 = $mdc->getMetadataValue($name1);
        static::assertInstanceOf(MetadataValue::class, $mdv1);
    }

    public function testMetadataCollectionWithNull(): void
    {
        $name1 = 'my_metadata1';
        $value1 = null;

        // Test set that creates.
        $mdc = new MetadataCollection();
        $mdc->set($name1, $value1);
        static::assertTrue($mdc->exists($name1));
        static::assertInstanceOf(MetadataValue::class, $mdc->getMetadataValue($name1));
        static::assertNull($mdc->get($name1));
        static::assertSame([$name1], array_keys($mdc->toArray()));

        // Test add that creates.
        $name2 = 'my_metadata2';
        $mdc->add($name2, $value1, MetadataValue::Single);
        static::assertTrue($mdc->exists($name2));
        static::assertInstanceOf(MetadataValue::class, $mdc->getMetadataValue($name2));
        static::assertNull($mdc->get($name2));
        static::assertSame([$name1, $name2], array_keys($mdc->toArray()));

        // Test set that overwrites.
        $value2 = 2;
        $mdc->set($name1, $value2);
        static::assertTrue($mdc->exists($name1));
        $mdv1 = $mdc->getMetadataValue($name1);
        static::assertInstanceOf(MetadataValue::class, $mdv1);
        static::assertSame(1, $mdv1->count());
        static::assertSame($value2, $mdc->get($name1));

        // Test add that adds.
        $mdc->add($name1, $value1);
        static::assertTrue($mdc->exists($name1));
        $mdv1 = $mdc->getMetadataValue($name1);
        static::assertInstanceOf(MetadataValue::class, $mdv1);
        static::assertSame(2, $mdv1->count());
        static::assertSame([$value2, $value1], $mdc->get($name1));

        // Test remove.
        $mdc->remove($name1);
        static::assertFalse($mdc->exists($name1));
        static::assertNull($mdc->getMetadataValue($name1));
        static::assertNull($mdc->get($name1));

        $mdc->remove($name2);
        static::assertFalse($mdc->exists($name2));
        static::assertNull($mdc->getMetadataValue($name2));
        static::assertNull($mdc->get($name2));
    }

    public function testMetadataCollectionList(): void
    {
        $name1 = 'my_metadata1';

        // Test empty set creation by passing null.
        $mdc = new MetadataCollection();
        $mdc->add($name1, null, MetadataValue::List);
        static::assertTrue($mdc->exists($name1));
        static::assertCount(0, $mdc->get($name1));

        // Test "not a list" creation with passing null.
        $mdc = new MetadataCollection();
        $mdc->add($name1, null);
        static::assertTrue($mdc->exists($name1));
        static::assertSame([], $mdc->get($name1));
    }

    public function testMetadataCollectionAddArray(): void
    {
        $name1 = 'my_metadata1';
        $data1 = ['a', 'b', 'c'];
        $data2 = ['d', 'e', 'f'];

        // Test list creation.
        $mdc = new MetadataCollection();
        $mdc->add($name1, $data1, MetadataValue::List);
        static::assertTrue($mdc->exists($name1));
        static::assertCount(3, $mdc->get($name1));
        $mdc->add($name1, $data2);
        static::assertCount(6, $mdc->get($name1));

        // Test list creation with $isList = false.
        $mdc = new MetadataCollection();
        $mdc->add($name1, $data1);
        static::assertTrue($mdc->exists($name1));
        static::assertCount(3, $mdc->get($name1));
        $mdc->add($name1, $data2);
        static::assertCount(6, $mdc->get($name1));

        // Test not a list creation.
        $data1 = array_combine($data1, $data1);
        $data2 = array_combine($data2, $data2);
        $mdc = new MetadataCollection();
        $mdc->add($name1, $data1);
        static::assertTrue($mdc->exists($name1));
        static::assertCount(1, $mdc->get($name1));
        $mdc->add($name1, $data2);
        static::assertCount(2, $mdc->get($name1));
    }

    public function testCopy(): void
    {
        $name1 = 'my_metadata1';
        $value1 = 'value1';
        $name2 = 'my_metadata2';
        $value2 =  new stdClass();
        $value2->s2 = 'value2';
        $value2->s3 = 'value3';
        $mdc = new MetadataCollection();
        $mdc->set($name1, $value1);
        $mdc->set($name2, $value2);

        $copy = new MetadataCollection();
        $copy->copy($mdc, $name1);
        $copy->copy($mdc, $name2);

        static::assertSame($mdc->get($name1), $copy->get($name1));
        static::assertNotSame($mdc->get($name2), $copy->get($name2));
        static::assertEquals($mdc->get($name2), $copy->get($name2));

        $value2->s4 = 'value4';
        $value2->s3 = 'value5';
        static::assertEquals($value2, $mdc->get($name2));
        static::assertNotEquals($mdc->get($name2), $copy->get($name2));
    }

    public function testToArray(): void
    {
        $name1 = 'my_metadata1';
        $value1 = 2;
        $name2 = 'my_metadata2';
        $value2 = 'test';

        // Test set that creates.
        $mdc = new MetadataCollection();
        $mdc->set($name1, $value1);
        $mdc->set($name2, $value2);

        $array = $mdc->toArray();
        $expected = [
            $name1 => 2,
            $name2 => 'test',
        ];
        static::assertSame($expected, $array);
    }
}
