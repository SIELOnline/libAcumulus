<?php
/**
 * @noinspection PhpMissingDocCommentInspection
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection DuplicatedCode
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Helpers;

use Siel\Acumulus\Helpers\Container;
use PHPUnit\Framework\TestCase;

class FieldTest extends TestCase
{
    private static Container $container;

    public static function setUpBeforeClass(): void
    {
        self::$container = new Container('TestWebShop', 'en');
    }

    /**
     * Returns a few "objects" to test{@see  Field::expand()} with.
     */
    private function getObjects(): array
    {
        return [
            'order' => json_decode('{
                "id": 3,
                "date": "2022-12-01",
                "amount" : 19.95,
                "paid": true,
                "returned": null
            }'),
            'customer' => json_decode('{
                "id": 2,
                "first_name": "Erwin",
                "middle_name": "",
                "last_name": "Derksen",
                "address": {
                    "id": 4,
                    "street": "Lindelaan 4",
                    "street2": "Achter de Linden",
                    "postal_code": "1234 AB",
                    "city": "Utrecht",
                    "country_code": "NL"
                }
            }'),
            'invoice_address' => json_decode('{
                "id": 5,
                "street": "Stationsstraat 3",
                "street2": "",
                "postal_code": "4321 BA",
                "city": "Amsterdam",
                "country_code": "NL"
            }'),
            'keyed_array' => [
                'p1' => 'v1',
                'p2' => 'v2',
            ],
        ];
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
        $field = self::$container->getField();
        $result = $field->expand($fieldDefinition, $this->getObjects());
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
        $field = self::$container->getField();
        $result = $field->expand($fieldDefinition, []);
        $this->assertSame($expected, $result);
    }

    public function fields1PropertyProvider(): array
    {
        return [
            ['[id]', 3],
            ['[customer::id]', 2],
            ['[customer::address::id]', 4],
            ['[date]', '2022-12-01'],
            ['[amount]', 19.95],
            ['[paid]', true],
            ['[returned]', null],
        ];
    }

    /**
     * @dataProvider fields1PropertyProvider
     */
    public function testExpand1Property(string $fieldDefinition, $expected): void
    {
        $field = self::$container->getField();
        $result = $field->expand($fieldDefinition, $this->getObjects());
        $this->assertSame($expected, $result);
    }

    public function fieldsWithAlternativesProvider(): array
    {
        return [
            ['[street|street2]', 'Stationsstraat 3'],
            ['[street2|street]', 'Stationsstraat 3'],
            ['[customer::address::street|customer::address::street2]', 'Lindelaan 4'],
            ['[customer::address::street2|customer::address::street]', 'Achter de Linden'],
        ];
    }

    /**
     * @dataProvider fieldsWithAlternativesProvider
     */
    public function testExpandAlternatives(string $fieldDefinition, $expected): void
    {
        $field = self::$container->getField();
        $result = $field->expand($fieldDefinition, $this->getObjects());
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
        $field = self::$container->getField();
        $result = $field->expand($fieldDefinition, $this->getObjects());
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
        $field = self::$container->getField();
        $result = $field->expand($fieldDefinition, $this->getObjects());
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
        $field = self::$container->getField();
        $result = $field->expand($fieldDefinition, $this->getObjects());
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
        $field = self::$container->getField();
        $result = $field->expand($fieldDefinition, $this->getObjects());
        $this->assertSame($expected, $result);
    }
}