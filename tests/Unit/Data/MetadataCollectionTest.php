<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Data\MetadataCollection;
use Siel\Acumulus\Data\MetadataValue;

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
        self::assertFalse($mdc->exists($name1));
        self::assertFalse($mdc->exists($no_name));
        self::assertNull($mdc->getMetadataValue($name1));
        self::assertNull($mdc->get($name1));
        self::assertSame([], $mdc->toArray());

        // Test set that creates.
        $mdc->set($name1, $value1);
        self::assertTrue($mdc->exists($name1));
        self::assertFalse($mdc->exists($no_name));
        self::assertInstanceOf(MetadataValue::class, $mdc->getMetadataValue($name1));
        self::assertSame($value1, $mdc->get($name1));
        self::assertSame([$name1], array_keys($mdc->toArray()));

        // Test add that creates.
        $mdc->add($name2, $value2);
        self::assertTrue($mdc->exists($name1));
        self::assertTrue($mdc->exists($name2));
        self::assertFalse($mdc->exists($no_name));
        self::assertInstanceOf(MetadataValue::class, $mdc->getMetadataValue($name2));
        self::assertSame([$value2], $mdc->get($name2));
        self::assertSame([$name1, $name2], array_keys($mdc->toArray()));

        // Test set that overwrites.
        $mdc->set($name1, $value2);
        self::assertTrue($mdc->exists($name1));
        self::assertTrue($mdc->exists($name2));
        $mdv1 = $mdc->getMetadataValue($name1);
        self::assertInstanceOf(MetadataValue::class, $mdv1);
        self::assertSame(1, $mdv1->count());
        self::assertSame($value2, $mdc->get($name1));

        // Test add that adds.
        $mdc->add($name1, $value1);
        self::assertTrue($mdc->exists($name1));
        self::assertTrue($mdc->exists($name2));
        $mdv1 = $mdc->getMetadataValue($name1);
        self::assertInstanceOf(MetadataValue::class, $mdv1);
        self::assertSame(2, $mdv1->count());
        self::assertSame([$value2, $value1], $mdc->get($name1));

        // Test remove.
        $mdc->remove($name2);
        self::assertTrue($mdc->exists($name1));
        self::assertFalse($mdc->exists($name2));
        self::assertNull($mdc->getMetadataValue($name2));
        self::assertNull($mdc->get($name2));
        $mdv1 = $mdc->getMetadataValue($name1);
        self::assertInstanceOf(MetadataValue::class, $mdv1);
    }

    public function testMetadataCollectionWithNull(): void
    {
        $name1 = 'my_metadata1';
        $value1 = null;

        // Test set that creates.
        $mdc = new MetadataCollection();
        $mdc->set($name1, $value1);
        self::assertTrue($mdc->exists($name1));
        self::assertInstanceOf(MetadataValue::class, $mdc->getMetadataValue($name1));
        self::assertNull($mdc->get($name1));
        self::assertSame([$name1], array_keys($mdc->toArray()));

        // Test add that creates.
        $name2 = 'my_metadata2';
        $mdc->add($name2, $value1, false);
        self::assertTrue($mdc->exists($name2));
        self::assertInstanceOf(MetadataValue::class, $mdc->getMetadataValue($name2));
        self::assertNull($mdc->get($name2));
        self::assertSame([$name1, $name2], array_keys($mdc->toArray()));

        // Test set that overwrites.
        $value2 = 2;
        $mdc->set($name1, $value2);
        self::assertTrue($mdc->exists($name1));
        $mdv1 = $mdc->getMetadataValue($name1);
        self::assertInstanceOf(MetadataValue::class, $mdv1);
        self::assertSame(1, $mdv1->count());
        self::assertSame($value2, $mdc->get($name1));

        // Test add that adds.
        $mdc->add($name1, $value1);
        self::assertTrue($mdc->exists($name1));
        $mdv1 = $mdc->getMetadataValue($name1);
        self::assertInstanceOf(MetadataValue::class, $mdv1);
        self::assertSame(2, $mdv1->count());
        self::assertSame([$value2, $value1], $mdc->get($name1));

        // Test remove.
        $mdc->remove($name1);
        self::assertFalse($mdc->exists($name1));
        self::assertNull($mdc->getMetadataValue($name1));
        self::assertNull($mdc->get($name1));

        $mdc->remove($name2);
        self::assertFalse($mdc->exists($name2));
        self::assertNull($mdc->getMetadataValue($name2));
        self::assertNull($mdc->get($name2));
    }

    public function testMetadataCollectionList(): void
    {
        $name1 = 'my_metadata1';

        // Test empty set creation by passing null.
        $mdc = new MetadataCollection();
        $mdc->add($name1, null, true);
        self::assertTrue($mdc->exists($name1));
        self::assertCount(0, $mdc->get($name1));

        // Test "not a list" creation with passing null.
        $mdc = new MetadataCollection();
        $mdc->add($name1, null, false);
        self::assertTrue($mdc->exists($name1));
        self::assertNull( $mdc->get($name1));
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
        self::assertSame($expected, $array);
    }
}
