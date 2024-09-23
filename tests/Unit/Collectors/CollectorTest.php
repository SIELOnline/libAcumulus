<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection DuplicatedCode
 * @noinspection PropertyCanBeStaticInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Collectors;

use Siel\Acumulus\Api;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Collectors\CollectorInterface;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\TestWebShop\Data\SimpleTestObject;

/**
 * Tests for the {@see \Siel\Acumulus\Collectors\Collector} class.
 */
class CollectorTest extends TestCase
{
    protected Container $container;
    protected array $fieldMappings = [
        'itemNumber' => '[field_item_number]',
        'nature' => '[field_nature]',
        'unitPrice' => '[field_unit_price]',
    ];
    protected array $nullFieldMappings = [
        'itemNumber' => '["null"]',
        'nature' => '[null]',
        'unitPrice' => '[""]',
    ];

    public function getCollector(): CollectorInterface
    {
        return $this->container->getCollector('SimpleTestObject');
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function setUp(): void
    {
        $this->container = new Container('TestWebShop', 'nl');
    }

    public function testCollectAllEmpty(): void
    {
        $collector = $this->getCollector();
        $propertySources = $this->container->createPropertySources();
        $fieldMappings = [];
        $simpleTestObject = $collector->collect($propertySources, $fieldMappings);
        $this->assertInstanceOf(SimpleTestObject::class, $simpleTestObject);
        $this->assertNull($simpleTestObject->getItemNumber());
        $this->assertNull($simpleTestObject->getNature());
        $this->assertNull($simpleTestObject->getUnitPrice());
    }

    public function testCollectMappingsOnly(): void
    {
        $itemNumber = 3;
        $nature = Api::Nature_Product;
        $unitPrice = '4.99';

        $propertySources = $this->container->createPropertySources()->add('line', [
            'field_item_number' => $itemNumber,
            'field_nature' => $nature,
            'field_unit_price' => $unitPrice,
        ]);

        $collector = $this->getCollector();
        $simpleTestObject = $collector->collect($propertySources, $this->fieldMappings);
        $this->assertInstanceOf(SimpleTestObject::class, $simpleTestObject);
        $this->assertSame((string) $itemNumber, $simpleTestObject->getItemNumber());
        $this->assertSame($nature, $simpleTestObject->getNature());
        $this->assertSame((float) $unitPrice, $simpleTestObject->getUnitPrice());
    }

    public function testCollectNullMappingsOnly(): void
    {
        $itemNumber = 3;
        $nature = Api::Nature_Product;
        $unitPrice = '4.99';

        $propertySources = $this->container->createPropertySources()->add('line', [
            'field_item_number' => $itemNumber,
            'field_nature' => $nature,
            'field_unit_price' => $unitPrice,
        ]);

        $collector = $this->getCollector();
        $simpleTestObject = $collector->collect($propertySources, $this->nullFieldMappings);
        $this->assertInstanceOf(SimpleTestObject::class, $simpleTestObject);
        $this->assertNull($simpleTestObject->getItemNumber());
        $this->assertNull($simpleTestObject->getNature());
        $this->assertNull($simpleTestObject->getUnitPrice());
    }

    public function testCollectWithLogic(): void
    {
        $itemNumber = 3;
        $nature = Api::Nature_Product;
        $unitPrice = '4.99';

        $propertySources = $this->container->createPropertySources()
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
        $this->assertInstanceOf(SimpleTestObject::class, $simpleTestObject);
        $this->assertSame((string) $itemNumber, $simpleTestObject->getItemNumber());
        $this->assertSame($nature, $simpleTestObject->getNature());
        $reductionPrice = (1 - $propertySources->get('customer')['reduction']) * $propertySources->get('line')['field_unit_price'];
        $this->assertEqualsWithDelta((float) $reductionPrice, $simpleTestObject->getUnitPrice(), 0.000001);
    }
}
