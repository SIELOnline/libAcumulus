<?php
/**
 * @noinspection DuplicatedCode
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

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
        $this->assertFalse($mdc->exists($name1));
        $this->assertFalse($mdc->exists($no_name));
        $this->assertNull($mdc->getMetadataValue($name1));
        $this->assertNull($mdc->get($name1));
        $this->assertSame([], $mdc->toArray());

        // Test set that creates.
        $mdc->set($name1, $value1);
        $this->assertTrue($mdc->exists($name1));
        $this->assertFalse($mdc->exists($no_name));
        $this->assertInstanceOf(MetadataValue::class, $mdc->getMetadataValue($name1));
        $this->assertSame($value1, $mdc->get($name1));
        $this->assertSame([$name1], array_keys($mdc->toArray()));

        // Test add that creates.
        $mdc->add($name2, $value2);
        $this->assertTrue($mdc->exists($name1));
        $this->assertTrue($mdc->exists($name2));
        $this->assertFalse($mdc->exists($no_name));
        $this->assertInstanceOf(MetadataValue::class, $mdc->getMetadataValue($name2));
        $this->assertSame([$value2], $mdc->get($name2));
        $this->assertSame([$name1, $name2], array_keys($mdc->toArray()));

        // Test set that overwrites.
        $mdc->set($name1, $value2);
        $this->assertTrue($mdc->exists($name1));
        $this->assertTrue($mdc->exists($name2));
        $mdv1 = $mdc->getMetadataValue($name1);
        $this->assertInstanceOf(MetadataValue::class, $mdv1);
        $this->assertSame(1, $mdv1->count());
        $this->assertSame($value2, $mdc->get($name1));

        // Test add that adds.
        $mdc->add($name1, $value1);
        $this->assertTrue($mdc->exists($name1));
        $this->assertTrue($mdc->exists($name2));
        $mdv1 = $mdc->getMetadataValue($name1);
        $this->assertInstanceOf(MetadataValue::class, $mdv1);
        $this->assertSame(2, $mdv1->count());
        $this->assertSame([$value2, $value1], $mdc->get($name1));

        // Test remove.
        $mdc->remove($name2);
        $this->assertTrue($mdc->exists($name1));
        $this->assertFalse($mdc->exists($name2));
        $this->assertNull($mdc->getMetadataValue($name2));
        $this->assertNull($mdc->get($name2));
        $mdv1 = $mdc->getMetadataValue($name1);
        $this->assertInstanceOf(MetadataValue::class, $mdv1);
    }

    public function testMetadataCollectionWithNull(): void
    {
        $name1 = 'my_metadata1';
        $value1 = null;

        // Test set that creates.
        $mdc = new MetadataCollection();
        $mdc->set($name1, $value1);
        $this->assertTrue($mdc->exists($name1));
        $this->assertInstanceOf(MetadataValue::class, $mdc->getMetadataValue($name1));
        $this->assertNull($mdc->get($name1));
        $this->assertSame([$name1], array_keys($mdc->toArray()));

        // Test add that creates.
        $name2 = 'my_metadata2';
        $mdc->add($name2, $value1, false);
        $this->assertTrue($mdc->exists($name2));
        $this->assertInstanceOf(MetadataValue::class, $mdc->getMetadataValue($name2));
        $this->assertNull($mdc->get($name2));
        $this->assertSame([$name1, $name2], array_keys($mdc->toArray()));

        // Test set that overwrites.
        $value2 = 2;
        $mdc->set($name1, $value2);
        $this->assertTrue($mdc->exists($name1));
        $mdv1 = $mdc->getMetadataValue($name1);
        $this->assertInstanceOf(MetadataValue::class, $mdv1);
        $this->assertSame(1, $mdv1->count());
        $this->assertSame($value2, $mdc->get($name1));

        // Test add that adds.
        $mdc->add($name1, $value1);
        $this->assertTrue($mdc->exists($name1));
        $mdv1 = $mdc->getMetadataValue($name1);
        $this->assertInstanceOf(MetadataValue::class, $mdv1);
        $this->assertSame(2, $mdv1->count());
        $this->assertSame([$value2, $value1], $mdc->get($name1));

        // Test remove.
        $mdc->remove($name1);
        $this->assertFalse($mdc->exists($name1));
        $this->assertNull($mdc->getMetadataValue($name1));
        $this->assertNull($mdc->get($name1));

        $mdc->remove($name2);
        $this->assertFalse($mdc->exists($name2));
        $this->assertNull($mdc->getMetadataValue($name2));
        $this->assertNull($mdc->get($name2));
    }

    public function testMetadataCollectionList(): void
    {
        $name1 = 'my_metadata1';

        // Test empty set creation by passing null.
        $mdc = new MetadataCollection();
        $mdc->add($name1, null, true);
        $this->assertTrue($mdc->exists($name1));
        $this->assertCount(0, $mdc->get($name1));

        // Test "not a list" creation with passing null.
        $mdc = new MetadataCollection();
        $mdc->add($name1, null, false);
        $this->assertTrue($mdc->exists($name1));
        $this->assertNull( $mdc->get($name1));
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
        $this->assertSame($expected, $array);
    }
}
