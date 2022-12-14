<?php
/**
 * @noinspection PhpMissingDocCommentInspection
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Collectors;

use Siel\Acumulus\Api;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Tests\TestWebShop\Data\SimpleTestObject;

class CollectorTest extends TestCase
{
    protected Container $container;
    protected array $fieldMappings = [
        'itemNumber' => '[field_item_number]',
        'nature' => '[field_nature]',
        'unitPrice' => '[field_unit_price]',
    ];

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function setUp(): void
    {
        $this->container = new Container('Tests\\TestWebShop', 'nl');
    }

    public function testCollectAllEmpty(): void
    {
        $collector = $this->container->getCollector('SimpleTest');
        $propertySources = [];
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

        $propertySources = [
            'line' => [
                'field_item_number' => $itemNumber,
                'field_nature' => $nature,
                'field_unit_price' => $unitPrice,
            ],
        ];

        $collector = $this->container->getCollector('SimpleTest');
        $simpleTestObject = $collector->collect($propertySources, $this->fieldMappings);
        $this->assertInstanceOf(SimpleTestObject::class, $simpleTestObject);
        $this->assertSame((string) $itemNumber, $simpleTestObject->getItemNumber());
        $this->assertSame($nature, $simpleTestObject->getNature());
        $this->assertSame((float) $unitPrice, $simpleTestObject->getUnitPrice());
    }

    public function testCollectWithLogic(): void
    {
        $itemNumber = 3;
        $nature = Api::Nature_Product;
        $unitPrice = '4.99';

        $propertySources = [
            'line' => [
                'field_item_number' => $itemNumber,
                'field_nature' => $nature,
                'field_unit_price' => $unitPrice,
            ],
            'customer' => [
                'reduction' => 0.05,
            ]
        ];

        $collector = $this->container->getCollector('SimpleTest');
        $simpleTestObject = $collector->collect($propertySources, $this->fieldMappings);
        $this->assertInstanceOf(SimpleTestObject::class, $simpleTestObject);
        $this->assertSame((string) $itemNumber, $simpleTestObject->getItemNumber());
        $this->assertSame($nature, $simpleTestObject->getNature());
        $reductionPrice = (1 - $propertySources['customer']['reduction']) * $propertySources['line']['field_unit_price'];
        $this->assertEqualsWithDelta((float) $reductionPrice, $simpleTestObject->getUnitPrice(), 0.000001);
    }
}
