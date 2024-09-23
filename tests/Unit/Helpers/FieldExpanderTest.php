<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection DuplicatedCode
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Helpers;

use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Helpers\Container;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Helpers\FieldExpander;
use Siel\Acumulus\Helpers\FormRenderer;
use Siel\Acumulus\Tests\Unit\GetTestData;

/**
 * Tests for the {@see FieldExpander} class.
 */
class FieldExpanderTest extends TestCase
{
    public const Language = 'en';

    private static Container $container;

    public function getContainer(): Container
    {
        if (!isset(self::$container)) {
            self::$container = new Container('TestWebShop', self::Language);
        }
        return self::$container;
    }

    private function createPropertySources(): PropertySources
    {
        return $this->getContainer()->createPropertySources();
    }

    public function getFieldExpander(): FieldExpander
    {
        return $this->getContainer()->getFieldExpander();
    }

    /**
     * Returns a few "objects" to test{@see  FieldExpander::expand()} with.
     */
    private function getTestDataPropertySources(): PropertySources
    {
        $testData = (new GetTestData())->get();
        $result = $this->createPropertySources();
        foreach ($testData as $name => $data) {
            $result->add($name, $data);
        }
        return $result;
    }

    public function fieldsNoFieldsProvider(): array
    {
        return [
            ['No variable fields', 'No variable fields'],
            ['No variable [ fields', 'No variable [ fields'],
            ['No variable ] fields', 'No variable ] fields'],
            ['No variable ][ fields', 'No variable ][ fields'],
        ];
    }

    /**
     * @dataProvider fieldsNoFieldsProvider
     */
    public function testExpandNoVariableFields(string $fieldDefinition, string $expected): void
    {
        $field = $this->getFieldExpander();
        $result = $field->expand($fieldDefinition, $this->getTestDataPropertySources());
        $this->assertSame($expected, $result);
    }

    public function fieldsNoObjectsProvider(): array
    {
        return [
            ['No objects [fields]', 'No objects '],
        ];
    }

    /**
     * @dataProvider fieldsNoObjectsProvider
     */
    public function testExpandNoObjects(string $fieldDefinition, string $expected): void
    {
        $field = $this->getFieldExpander();
        $result = $field->expand($fieldDefinition, $this->createPropertySources());
        $this->assertSame($expected, $result);
    }

    public function fields1PropertyProvider(): array
    {
        return [
            ['[id]', 3],
            ['[invoiceSource::customer::id]', 2],
            ['[invoiceSource::customer::invoice_address::id]', 4],
            ['[invoiceSource::customer::telephone]', '0123456789'],
            ['[invoiceSource::customer::mobile]', '+33612345978'],
            ['[date]', '2022-12-01'],
            ['[amount]', 50.40],
            ['[order::payment::paid]', 50.40],
            ['[paid]', true],
            ['[returned]', null],
        ];
    }

    /**
     * @dataProvider fields1PropertyProvider
     */
    public function testExpand1Property(string $fieldDefinition, $expected): void
    {
        $field = $this->getFieldExpander();
        $result = $field->expand($fieldDefinition, $this->getTestDataPropertySources());
        $this->assertSame($expected, $result);
    }

    public function fieldsWithAlternativesProvider(): array
    {
        return [
            ['[street|street2]', 'Stationsstraat 3'],
            ['[street2|street]', 'Stationsstraat 3'],
            ['[non_existing1|non_existing2]', null],
            ['[non_existing1|returned]', null],
            ['[returned|non_existing2]', null],
            ['[invoiceSource::customer::invoice_address::street|invoiceSource::customer::invoice_address::street2]', 'Lindelaan 4'],
            ['[invoiceSource::customer::invoice_address::street2|invoiceSource::customer::invoice_address::street]', 'Achter de Linden'],
        ];
    }

    /**
     * @dataProvider fieldsWithAlternativesProvider
     */
    public function testExpandAlternatives(string $fieldDefinition, $expected): void
    {
        $field = $this->getFieldExpander();
        $result = $field->expand($fieldDefinition, $this->getTestDataPropertySources());
        $this->assertSame($expected, $result);
    }

    public function fieldsWithSpaceConcatenatedPropertiesProvider(): array
    {
        return [
            ['[first_name+last_name]', 'Erwin Derksen'],
            ['[first_name+middle_name+last_name]', 'Erwin Derksen'],
            ['[first_name+middle_name]', 'Erwin'],
            ['[middle_name+last_name]', 'Derksen'],
        ];
    }

    /**
     * @dataProvider fieldsWithSpaceConcatenatedPropertiesProvider
     */
    public function testExpandSpaceConcatenatedProperties(string $fieldDefinition, $expected): void
    {
        $field = $this->getFieldExpander();
        $result = $field->expand($fieldDefinition, $this->getTestDataPropertySources());
        $this->assertSame($expected, $result);
    }

    public function fieldsWithConcatenatedPropertiesProvider(): array
    {
        return [
            ['[first_name&last_name]', 'ErwinDerksen'],
            ['[first_name&middle_name&last_name]', 'ErwinDerksen'],
            ['[first_name&middle_name]', 'Erwin'],
            ['[middle_name&last_name]', 'Derksen'],
        ];
    }

    /**
     * @dataProvider fieldsWithSpaceConcatenatedPropertiesProvider
     */
    public function testExpandConcatenatedProperties(string $fieldDefinition, $expected): void
    {
        $field = $this->getFieldExpander();
        $result = $field->expand($fieldDefinition, $this->getTestDataPropertySources());
        $this->assertSame($expected, $result);
    }

    public function fieldsWithLiteralsProvider(): array
    {
        return [
            ['[returned|"No returns yet"]', 'No returns yet'],
            ['["Straat"+street]', 'Straat Stationsstraat 3'],
            ['["Straat"+street2]', ''],
            ['[street+"Straat"]', 'Stationsstraat 3 Straat'],
            ['[street2+"Straat"]', ''],
            ['["Straat"&street]', 'StraatStationsstraat 3'],
            ['["Straat"&street2]', ''],
            ['[street&"Straat"]', 'Stationsstraat 3Straat'],
            ['[street2&"Straat"]', ''],
        ];
    }

    /**
     * @dataProvider fieldsWithLiteralsProvider
     */
    public function testExpandWithLiterals(string $fieldDefinition, $expected): void
    {
        $field = $this->getFieldExpander();
        $result = $field->expand($fieldDefinition, $this->getTestDataPropertySources());
        $this->assertSame($expected, $result);
    }

    public function complexFieldsProvider(): array
    {
        return [
            ['Beste [first_name] ([middle_name+last_name]),', 'Beste Erwin (Derksen),'],
            ['Beste [first&middle_name&last|"klant"],', 'Beste klant,'],
            ['Ins[paid]ren', 'Instrueren'],
        ];
    }

    /**
     * @dataProvider complexFieldsProvider
     */
    public function testExpandComplexFields(string $fieldDefinition, $expected): void
    {
        $field = $this->getFieldExpander();
        $result = $field->expand($fieldDefinition, $this->getTestDataPropertySources());
        $this->assertSame($expected, $result);
    }

    public function objectsProvider(): array
    {
        return [
            ['[container::language]', self::Language],
            ['[container::translator::language]', self::Language],
            ['[container::createAcumulusObject(address)::fullName]', null], //magic __get
            ['[container::createSource(Order,10)::id]', 10], //magic __get
            ['[container::createSource(Order,10)::getCreditNote()]', null], // () is not '' as a single argument
            ['[container::createSource(Order,10)::getCreditNote()::id]', null], // null in middle of chain
        ];
    }

    /**
     * @dataProvider objectsProvider
     */
    public function testObjects(string $fieldDefinition, $expected): void
    {
        $objects = $this->createPropertySources()->add('container', $this->getContainer());
        $field = $this->getFieldExpander();
        $result = $field->expand($fieldDefinition, $objects);
        $this->assertSame($expected, $result);
    }

    public function objectWithNumericArrayAnd0ValuesProvider(): array
    {
        return [
            ['[order::lines::0::product]', 'Buro'],
            ['[order::lines::2::product]', 'Comment'],
            ['[order::lines::2::qty]', 0],
            ['[order::lines::2::qty|source::getSign()]', 1],
            ['[order::lines::2::price]', 0.0],
            ['[order::lines::2::price|source::getSign()]', 1],
            ['[order::lines::3::qty]', 0],
            ['[order::lines::3::price]', 0.0],
        ];
    }

    /**
     * @dataProvider objectWithNumericArrayAnd0ValuesProvider
     */
    public function testObjectWithNumericArray(string $fieldDefinition, $expected): void
    {
        $objects = $this->getTestDataPropertySources()->add(
            'source',
            new class {
                public function getSign(): int
                {
                    return 1;
                }
            }
        );
        $field = $this->getFieldExpander();
        $result = $field->expand($fieldDefinition, $objects);
        $this->assertSame($expected, $result);
    }

    /**
     * Tests parameter passing without type strictness.
     */
    public function testParameterPassing(): void
    {
        $objects = $this->createPropertySources()->add('container', $this->getContainer());
        $field = $this->getFieldExpander();
        $result1 = $field->expand('[container::getFormRenderer(true)]', $objects);
        $this->assertInstanceOf(FormRenderer::class, $result1);
        $result2 = $field->expand('[container::getFormRenderer(0)]', $objects);
        $this->assertInstanceOf(FormRenderer::class, $result2);
        $this->assertSame($result1, $result2);
        $result3 = $field->expand('[container::getFormRenderer(true)]', $objects);
        $this->assertInstanceOf(FormRenderer::class, $result3);
        $this->assertNotSame($result1, $result3);
    }

    /**
     * Tests using sign for numeric values.
     */
    public function testSign(): void
    {
        $objects = $this->getTestDataPropertySources()->add(
            'source',
            new class {
                public function getSign(): int
                {
                    return 1;
                }
            }
        );
        $field = $this->getFieldExpander();
        $result1 = $field->expand('[order::amount]', $objects);
        $result2 = $field->expand('[sign*order::amount]', $objects);
        $this->assertSame($result1, $result2);
        $result3 = $field->expand('[order::amount1|order::amount]', $objects);
        $result4 = $field->expand('[sign*order::amount1|order::amount]', $objects);
        $this->assertSame($result3, $result4);

        $objects = $this->getTestDataPropertySources()->add(
            'source',
            new class {
                public function getSign(): int
                {
                    return -1;
                }
            }
        );
        $field = $this->getFieldExpander();
        $result1 = $field->expand('[order::amount]', $objects);
        $result2 = $field->expand('[sign*order::amount]', $objects);
        $this->assertSame(-$result1, $result2);
        $result3 = $field->expand('[order::amount1|order::amount]', $objects);
        $result4 = $field->expand('[sign*order::amount1|order::amount]', $objects);
        $this->assertSame(-$result3, $result4);
    }
}
