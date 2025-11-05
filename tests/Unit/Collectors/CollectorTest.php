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
        self::assertInstanceOf(SimpleTestObject::class, $simpleTestObject);
        self::assertNull($simpleTestObject->getItemNumber());
        self::assertNull($simpleTestObject->getNature());
        self::assertNull($simpleTestObject->getUnitPrice());
    }

    public function testCollectMappingsOnly(): void
    {
        $itemNumber = 3;
        $nature = Api::Nature_Product;
        $unitPrice = '4.99';

        $propertySources = self::getContainer()->createPropertySources()->add('line', [
            'field_item_number' => $itemNumber,
            'field_nature' => $nature,
            'field_unit_price' => $unitPrice,
        ]);

        $collector = $this->getCollector();
        $simpleTestObject = $collector->collect($propertySources, $this->fieldMappings);
        self::assertInstanceOf(SimpleTestObject::class, $simpleTestObject);
        self::assertSame((string) $itemNumber, $simpleTestObject->getItemNumber());
        self::assertSame($nature, $simpleTestObject->getNature());
        self::assertSame((float) $unitPrice, $simpleTestObject->getUnitPrice());
    }

    public function testCollectNullMappingsOnly(): void
    {
        $itemNumber = 3;
        $nature = Api::Nature_Product;
        $unitPrice = '4.99';

        $propertySources = self::getContainer()->createPropertySources()->add('line', [
            'field_item_number' => $itemNumber,
            'field_nature' => $nature,
            'field_unit_price' => $unitPrice,
        ]);

        $collector = $this->getCollector();
        $simpleTestObject = $collector->collect($propertySources, $this->nullFieldMappings);
        self::assertInstanceOf(SimpleTestObject::class, $simpleTestObject);
        self::assertNull($simpleTestObject->getItemNumber());
        self::assertNull($simpleTestObject->getNature());
        self::assertNull($simpleTestObject->getUnitPrice());
    }

    public function testCollectWithLogic(): void
    {
        $itemNumber = 3;
        $nature = Api::Nature_Product;
        $unitPrice = '4.99';

        $propertySources = self::getContainer()->createPropertySources()
            ->add('line', [
                'field_item_number' => $itemNumber,
                'field_nature' => $nature,
                'field_unit_price' => $unitPrice,
            ])
            ->add('customer', [
                'reduction' => 0.05,
            ]);

        $collector = $this->getCollector();
        $simpleTestObject = $collector->collect($propertySources, $this->fieldMappings);
        self::assertInstanceOf(SimpleTestObject::class, $simpleTestObject);
        self::assertSame((string) $itemNumber, $simpleTestObject->getItemNumber());
        self::assertSame($nature, $simpleTestObject->getNature());
        $reductionPrice = (1 - $propertySources->get('customer')['reduction']) * $propertySources->get('line')['field_unit_price'];
        self::assertEqualsWithDelta((float) $reductionPrice, $simpleTestObject->getUnitPrice(), 0.000001);
    }
}
