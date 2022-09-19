<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

namespace Siel\Acumulus\Unit\Invoice;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\TestWebShop\Invoice\TestAcumulusObject;

class MetadataTest extends TestCase
{
    public function testMetadata()
    {
        $name = 'my_metadata';
        $false_name = 'no_metadata_name';
        $value1 = 'value1';
        $value2 = 2;
        $md = new TestAcumulusObject();
        $this->assertNull($md->getMetadata($name));
        $this->assertNull($md->getMetadata($false_name));

        $md->addMetadata($name, $value1);
        $this->assertEquals(1, $md->getMetadataCount($name));
        $this->assertEquals($value1, $md->getMetadata($name));
        $this->assertNull($md->getMetadata($false_name));

        $md->setMetadata($name, $value2);
        $this->assertEquals(1, $md->getMetadataCount($name));
        $this->assertEquals($value2, $md->getMetadata($name));
        $this->assertNull($md->getMetadata($false_name));

        $md->addMetadata($name, $value1);
        $this->assertEquals(2, $md->getMetadataCount($name));
        $this->assertEquals([$value2, $value1], $md->getMetadata($name));
        $this->assertNull($md->getMetadata($false_name));
    }

    public function testGetMetadataNames()
    {
        $name1 = 'my_metadata1';
        $name2 = 'my_metadata2';
        $value1 = 'value1';
        $value2 = 2;
        $md = new TestAcumulusObject();

        $this->assertEquals([], $md->getMetadataNames());

        $md->setMetadata($name1, $value1);
        $this->assertEquals([$name1], $md->getMetadataNames());

        $md->setMetadata($name2, $value2);
        $this->assertEquals([$name1, $name2], $md->getMetadataNames());

        $md->addMetadata($name2, $value1);
        $this->assertEquals([$name1, $name2], $md->getMetadataNames());
    }
}
