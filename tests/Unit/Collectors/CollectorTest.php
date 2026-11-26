<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Collectors;

use ArrayObject;
use Siel\Acumulus\Api;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Collectors\CollectorInterface;
use Siel\Acumulus\Tests\Utils\AcumulusContainer;
use Siel\Acumulus\TestWebShop\Data\SimpleTestObject;

/**
 * Tests for the {@see \Siel\Acumulus\Collectors\Collector} class.
 */
class CollectorTest extends TestCase
{
    use AcumulusContainer;

    protected ArrayObject $fieldMappings;
    protected ArrayObject $nullFieldMappings;

    public function getCollector(): CollectorInterface
    {
        return self::getContainer()->getCollector('SimpleTestObject');
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function setUp(): void
    {
        $this->fieldMappings = new ArrayObject([
            'itemNumber' => '[field_item_number]',
            'nature' => '[field_nature]',
            'unitPrice' => '[field_unit_price]',
        ]);
        $this->nullFieldMappings = new ArrayObject([
            'itemNumber' => '["null"]',
            'nature' => '[null]',
            'unitPrice' => '[""]',
        ]);
    }

    public function testCollectAllEmpty(): void
    {
        $collector = $this->getCollector();
        $propertySources = self::getContainer()->createPropertySources();
        $fieldMappings = new ArrayObject();
        $simpleTestObject = $collector->collect($propertySources, $fieldMappings);
        static::assertInstanceOf(SimpleTestObject::class, $simpleTestObject);
        static::assertNull($simpleTestObject->getItemNumber());
        static::assertNull($simpleTestObject->getNature());
        static::assertNull($simpleTestObject->getUnitPrice());
    }

    public function testCollectMappingsOnly(): void
    {
        $itemNumber = 3;
        $nature = Api::Nature_Product;
        $unitPrice = '4.99';

        $propertySources = self::getContainer()->createPropertySources([
            'line' => ['field_item_number' => $itemNumber, 'field_nature' => $nature, 'field_unit_price' => $unitPrice,],
        ]);

        $collector = $this->getCollector();
        $simpleTestObject = $collector->collect($propertySources, $this->fieldMappings);
        static::assertInstanceOf(SimpleTestObject::class, $simpleTestObject);
        static::assertSame((string) $itemNumber, $simpleTestObject->getItemNumber());
        static::assertSame($nature, $simpleTestObject->getNature());
        static::assertSame((float) $unitPrice, $simpleTestObject->getUnitPrice());
    }

    public function testCollectNullMappingsOnly(): void
    {
        $itemNumber = 3;
        $nature = Api::Nature_Product;
        $unitPrice = '4.99';

        $propertySources = self::getContainer()->createPropertySources([
            'line' => ['field_item_number' => $itemNumber, 'field_nature' => $nature, 'field_unit_price' => $unitPrice,],
        ]);
        $collector = $this->getCollector();
        $simpleTestObject = $collector->collect($propertySources, $this->nullFieldMappings);
        static::assertInstanceOf(SimpleTestObject::class, $simpleTestObject);
        static::assertNull($simpleTestObject->getItemNumber());
        static::assertNull($simpleTestObject->getNature());
        static::assertNull($simpleTestObject->getUnitPrice());
    }

    public function testCollectWithLogic(): void
    {
        $itemNumber = 3;
        $nature = Api::Nature_Product;
        $unitPrice = '4.99';

        $propertySources = self::getContainer()->createPropertySources([
            'line' => ['field_item_number' => $itemNumber, 'field_nature' => $nature, 'field_unit_price' => $unitPrice,],
            'customer' => ['reduction' => 0.05,],
        ]);

        $collector = $this->getCollector();
        $simpleTestObject = $collector->collect($propertySources, $this->fieldMappings);
        static::assertInstanceOf(SimpleTestObject::class, $simpleTestObject);
        static::assertSame((string) $itemNumber, $simpleTestObject->getItemNumber());
        static::assertSame($nature, $simpleTestObject->getNature());
        $reductionPrice = (1 - $propertySources['customer']['reduction']) * $propertySources['line']['field_unit_price'];
        static::assertEqualsWithDelta((float) $reductionPrice, $simpleTestObject->getUnitPrice(), 0.000001);
    }
}
