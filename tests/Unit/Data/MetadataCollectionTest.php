<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

namespace Siel\Acumulus\Unit\Data;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Data\MetadataCollection;
use Siel\Acumulus\Data\MetadataValue;
use Siel\Acumulus\TestWebShop\Data\SimpleTestObject;

class MetadataCollectionTest extends TestCase
{
    public function testMetadata()
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
        $this->assertNull($mdc->get($name1));
        $this->assertNull($mdc->getValue($name1));
        $this->assertSame([], $mdc->getKeys());

        // Test set that creates.
        $mdc->set($name1, $value1);
        $this->assertTrue($mdc->exists($name1));
        $this->assertFalse($mdc->exists($no_name));
        $this->assertInstanceOf(MetadataValue::class, $mdc->get($name1));
        $this->assertSame($value1, $mdc->getValue($name1));
        $this->assertSame([$name1], $mdc->getKeys());

        // Test add that creates.
        $mdc->add($name2, $value2);
        $this->assertTrue($mdc->exists($name1));
        $this->assertTrue($mdc->exists($name2));
        $this->assertFalse($mdc->exists($no_name));
        $this->assertInstanceOf(MetadataValue::class, $mdc->get($name2));
        $this->assertSame($value2, $mdc->getValue($name2));
        $this->assertSame([$name1, $name2], $mdc->getKeys());

        // Test set that overwrites.
        $mdc->set($name1, $value2);
        $this->assertTrue($mdc->exists($name1));
        $this->assertTrue($mdc->exists($name2));
        $mdv1 = $mdc->get($name1);
        $this->assertInstanceOf(MetadataValue::class, $mdv1);
        $this->assertSame(1, $mdv1->count());
        $this->assertSame($value2, $mdc->getValue($name1));

        // Test add that adds.
        $mdc->add($name1, $value1);
        $this->assertTrue($mdc->exists($name1));
        $this->assertTrue($mdc->exists($name2));
        $mdv1 = $mdc->get($name1);
        $this->assertInstanceOf(MetadataValue::class, $mdv1);
        $this->assertSame(2, $mdv1->count());
        $this->assertSame([$value2, $value1], $mdc->getValue($name1));

        // Test remove.
        $mdc->remove($name2);
        $this->assertTrue($mdc->exists($name1));
        $this->assertFalse($mdc->exists($name2));
        $this->assertNull($mdc->get($name2));
        $this->assertNull($mdc->getValue($name2));
        $mdv1 = $mdc->get($name1);
        $this->assertInstanceOf(MetadataValue::class, $mdv1);
    }
}
