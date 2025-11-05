<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Tests\Utils\AcumulusContainer;
use Siel\Acumulus\TestWebShop\TestDoubles\Helpers\FieldExpander;

/**
 * FieldParsingTest tests the - internal - parsing mechanisms of the
 * {@see \Siel\Acumulus\Helpers\FieldExpander} Class.
 *
 * These tests indicate that, if the tests with more basic field definitions
 * succeed, more complex field definitions with multiple occurrences of multiple
 * operators will continue to function correctly.
 */
class FieldParsingTest extends TestCase
{
    use AcumulusContainer;

    protected static string $shopNamespace = 'TestWebShop\TestDoubles';
    protected static string $language = 'en';

    private function createPropertySources(): PropertySources
    {
        return self::getContainer()->createPropertySources();
    }

    public static function fieldsProvider1(): array
    {
        return [
            ['No variable fields'],
            ['No variable [ fields'],
            ['No variable ] fields'],
            ['No variable ][ fields'],
        ];
    }

    /**
     * @dataProvider fieldsProvider1
     */
    public function testExpandNoExpansionSpecifications(string $field): void
    {
        $log = self::getContainer()->getLog();
        $stopAt = 'expandSpecification';
        $vf = new FieldExpander($log, $stopAt);
        $result = $vf->expand($field, $this->createPropertySources());
        $this->assertSame($field, $result);
        $this->assertEmpty($vf->trace);
    }

    public static function fieldsProvider2(): array
    {
        return [
            ['1 variable [field]', 'field'],
            ['1 [variable] field', 'variable'],
            ['1 [variable field]', 'variable field'],
            ['[1 variable field]', '1 variable field'],
        ];
    }

    /**
     * @dataProvider fieldsProvider2
     */
    public function testExpand1ExpansionSpecification(string $field, string $match): void
    {
        $log = self::getContainer()->getLog();
        $stopAt = 'expandSpecification';
        $vf = new FieldExpander($log, $stopAt);
        /** @noinspection PhpUnusedLocalVariableInspection */
        $result = $vf->expand($field, $this->createPropertySources());
        $this->assertCount(1, $vf->trace[$stopAt]);
        $this->assertSame($match, reset($vf->trace[$stopAt]));
    }

    public static function fieldsProvider3(): array
    {
        return [
            ['multiple [variable] [fields]', ['variable', 'fields']],
            ['[multiple] [variable] fields', ['multiple', 'variable']],
            ['[multiple] [variable] [fields]', ['multiple', 'variable', 'fields']],
            ['multiple[vari][able]fields', ['vari', 'able']],
        ];
    }

    /**
     * @dataProvider fieldsProvider3
     */
    public function testExpandMultipleExpansionSpecifications(string $field, array $parts): void
    {
        $log = self::getContainer()->getLog();
        $stopAt = 'expandSpecification';
        $vf = new FieldExpander($log, $stopAt);
        /** @noinspection PhpUnusedLocalVariableInspection */
        $result = $vf->expand($field, $this->createPropertySources());
        $this->assertEqualsCanonicalizing($parts, $vf->trace[$stopAt]);
    }

    public static function fieldsProvider4(): array
    {
        return [
            ['[multiple|alternative|fields]', ['multiple', 'alternative', 'fields']],
            ['[multiple|alternatives|"literal"]', ['multiple', 'alternatives', '"literal"']],
        ];
    }

    /**
     * @dataProvider fieldsProvider4
     */
    public function testExpandPropertyAlternatives(string $field, array $parts): void
    {
        $log = self::getContainer()->getLog();
        $stopAt = 'expandAlternative';
        $vf = new FieldExpander($log, $stopAt);
        /** @noinspection PhpUnusedLocalVariableInspection */
        $result = $vf->expand($field, $this->createPropertySources());
        $this->assertEqualsCanonicalizing($parts, $vf->trace[$stopAt]);
    }

    public static function fieldsProvider5(): array
    {
        return [
            ['[multiple+concatenated+fields]', ['multiple', 'concatenated', 'fields']],
            ['[multiple+concatenated+"literal"]', ['multiple', 'concatenated', '"literal"']],
            ['["literal"+multiple+concatenated]', ['"literal"', 'multiple', 'concatenated']],
        ];
    }

    /**
     * @dataProvider fieldsProvider5
     */
    public function testExpandSpaceConcatenatedProperties(string $field, array $parts): void
    {
        $log = self::getContainer()->getLog();
        $stopAt = 'expandSpaceConcatenatedProperty';
        $vf = new FieldExpander($log, $stopAt);
        /** @noinspection PhpUnusedLocalVariableInspection */
        $result = $vf->expand($field, $this->createPropertySources());
        $this->assertEqualsCanonicalizing($parts, $vf->trace[$stopAt]);
    }

    public static function fieldsProvider6(): array
    {
        return [
            ['[multiple&single&properties]', ['multiple', 'single', 'properties'], ['multiple&single&properties']],
            ['[multiple+single&"literal"]', ['multiple', 'single', '"literal"'], ['multiple', 'single&"literal"']],
            ['["literal"&multiple+single]', ['"literal"', 'multiple', 'single'], ['"literal"&multiple', 'single']],
        ];
    }

    /**
     * @dataProvider fieldsProvider6
     */
    public function testExpandSingleProperties(string $field, array $parts, array $spaceConcatenatedProperties): void
    {
        $log = self::getContainer()->getLog();
        $stopAt = 'expandProperty';
        $vf = new FieldExpander($log, $stopAt);
        /** @noinspection PhpUnusedLocalVariableInspection */
        $result = $vf->expand($field, $this->createPropertySources());
        $this->assertEqualsCanonicalizing($parts, $vf->trace[$stopAt]);
        $logEntry = 'expandSpaceConcatenatedProperty';
        $this->assertEqualsCanonicalizing($spaceConcatenatedProperties, $vf->trace[$logEntry]);
    }

    public static function fieldsProvider7(): array
    {
        return [
            ['[object1::object2::my_property+"literal"+my_property]', ['object1::object2::my_property'], ['my_property'], ['"literal"']],
            ['[object1::object2::my_property|"literal"+my_property]', ['object1::object2::my_property'], ['my_property'], ['"literal"']],
            ['["literal"+my_property] [object1::object2::my_property]', ['object1::object2::my_property'], ['my_property'], ['"literal"']],
        ];
    }

    /**
     * @dataProvider fieldsProvider7
     */
    public function testExpandPropertiesAndLiterals(string $field, array $propertiesInObject, array $properties, array $literals): void
    {
        $log = self::getContainer()->getLog();
        $stopAt = '';
        $vf = new FieldExpander($log, $stopAt);
        /** @noinspection PhpUnusedLocalVariableInspection */
        $result = $vf->expand($field, $this->createPropertySources());
        $this->assertEqualsCanonicalizing($propertiesInObject, $vf->trace['expandPropertyInObject']);
        $this->assertEqualsCanonicalizing($properties, $vf->trace['expandSinglePropertyName']);
        $this->assertEqualsCanonicalizing($literals, $vf->trace['getLiteral']);
    }
}
