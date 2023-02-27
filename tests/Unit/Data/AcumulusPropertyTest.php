<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection DuplicatedCode
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Data;

use DateTime;
use DomainException;
use Siel\Acumulus\Data\AcumulusProperty;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the {@see AcumulusProperty} class.
 */
class AcumulusPropertyTest extends TestCase
{

    public function testConstructorValidationName1(): void
    {
        $this->expectException(DomainException::class);
        $pd = ['type' => 'int', 'allowedValues' => [1, 2]];
        new AcumulusProperty($pd);
    }

    public function testConstructorValidationName2(): void
    {
        $this->expectException(DomainException::class);
        $pd = ['name' => 3, 'type' => 'int', 'allowedValues' => [1, 2]];
        new AcumulusProperty($pd);
    }

    public function testConstructorValidationType(): void
    {
        $this->expectException(DomainException::class);
        $pd = ['name' => 'property', 'type' => 'error', 'allowedValues' => [1, 2]];
        new AcumulusProperty($pd);
    }

    public function testConstructorValidationRequired(): void
    {
        $this->expectException(DomainException::class);
        $pd = ['name' => 'property', 'type' => 'int', 'required' => 1, 'allowedValues' => [1, 2]];
        new AcumulusProperty($pd);
    }

    public function testConstructorValidationAllowedValues(): void
    {
        $this->expectException(DomainException::class);
        $pd = ['name' => 'property', 'type' => 'int', 'required' => true, 'allowedValues' => true];
        new AcumulusProperty($pd);
    }

    public function testConstructor(): void
    {
        $pd = ['name' => 'property', 'type' => 'int', 'required' => true, 'allowedValues' => [1, 2]];
        $p = new AcumulusProperty($pd);
        $this->assertSame('property', $p->getName());
        $this->assertTrue($p->isRequired());
        $this->assertNull($p->getValue());
    }

    public function setValueDataProvider(): array
    {
        return [
            'int-int' => ['int', 1, 1],
            'int-string' => ['int', '2', 2],
            'int-float' => ['int', 3.99999, 4],
            'int-negative' => ['int', -3, -3],
            'id-positive' => ['id', 3, 3],
            'float-int' => ['float', 1, 1.0],
            'float-string' => ['float', '1.234e2', 123.4],
            'float-negative' => ['float', -3.5, -3.5],
        ];
    }

    /**
     * @dataProvider setValueDataProvider
     */
    public function testSetValue(string $type, $value, $propertyValue): void
    {
        $pd = ['name' => 'property', 'type' => $type];
        $p = new AcumulusProperty($pd);
        $p->setValue($value);
        $this->assertSame($propertyValue, $p->getValue());
    }

    public function setBoolValueDataProvider(): array
    {
        return [
            'bool-true' => [[0, 1], true, true],
            'bool-false' => [[0, 1], false, false],
            'bool-1' => [[0, 1], 1, true],
            'bool-0' => [[0, 1], 0, false],
            'bool-reversed-1' => [[1, 0], 0, true],
            'bool-reversed-0' => [[1, 0], 1, false],
            'bool-yes' => [['no', 'yes'], 'yes', true],
            'bool-no' => [['no', 'yes'], 'no', false],
        ];
    }

    /**
     * @dataProvider setBoolValueDataProvider
     */
    public function testSetBoolValue(array $allowedValues, $value, $propertyValue): void
    {
        $pd = ['name' => 'property', 'type' => 'bool', 'allowedValues' => $allowedValues];
        $p = new AcumulusProperty($pd);
        $p->setValue($value);
        $this->assertSame($propertyValue, $p->getValue());
    }

    /**
     * @noinspection PhpRedundantOptionalArgumentInspection
     */
    public function setDateValueDataProvider(): array
    {
        $now = time();
        $floatNow = ((float) $now) + 0.5;
        return [
            'date-string' => ['date', '2022-09-01', DateTime::createFromFormat('Y-m-d', '2022-09-01')->setTime(0, 0, 0)],
            'date-int' => ['date', $now, DateTime::createFromFormat('U', (string) $now)->setTime(0, 0, 0)],
            'date-float' => ['date', $floatNow, DateTime::createFromFormat('U.u', (string) $floatNow)->setTime(0, 0, 0)],
        ];
    }

    /**
     * @dataProvider setDateValueDataProvider
     */
    public function testDateSetValue(string $type, $value, $castValue): void
    {
        $pd = ['name' => 'property', 'type' => $type];
        $p = new AcumulusProperty($pd);
        $p->setValue($value);
        $this->assertEquals($castValue, $p->getValue());
    }

    public function setValueWrongTypeDataProvider(): array
    {
        return [
            'int-array' => ['int', [1]],
            'int-string' => ['int', 'a'],
            'int-bool' => ['int', true],
            'int-float' => ['int', 3.5],
            'id-negative' => ['id', -3],
            'float-array' => ['int', [1.2]],
            'float-string' => ['float', 'a'],
            'float-bool' => ['float', true],
            'date-array' => ['date', ['2022-09-01']],
            'date-string' => ['date', '200220901'],
        ];
    }

    /**
     * @dataProvider setValueWrongTypeDataProvider
     */
    public function testSetValueWrongValue(string $type, $value): void
    {
        $this->expectException(DomainException::class);
        $pd = ['name' => 'property', 'type' => $type];
        $p = new AcumulusProperty($pd);
        $p->setValue($value);
    }

    public function testSetValueNotAllowedValue(): void
    {
        $this->expectException(DomainException::class);
        $pd = ['name' => 'property', 'type' => 'int', 'allowedValues' => [1,2]];
        $p = new AcumulusProperty($pd);
        $value = 3;
        $p->setValue($value);
    }
}
