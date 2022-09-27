<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

namespace Siel\Acumulus\Unit\Invoice;

use DateTime;
use DomainException;
use Siel\Acumulus\Invoice\AcumulusProperty;
use PHPUnit\Framework\TestCase;

class AcumulusPropertyTest extends TestCase
{

    public function testConstructorValidationName1()
    {
        $this->expectException(DomainException::class);
        $pd = ['type' => 'int', 'allowedValues' => [1,2]];
        new AcumulusProperty($pd);
    }

    public function testConstructorValidationName2()
    {
        $this->expectException(DomainException::class);
        $pd = ['name' => 3, 'type' => 'int', 'allowedValues' => [1,2]];
        new AcumulusProperty($pd);
    }

    public function testConstructorValidationType()
    {
        $this->expectException(DomainException::class);
        $pd = ['name' => 'property', 'type' => 'error', 'allowedValues' => [1,2]];
        new AcumulusProperty($pd);
    }

    public function testConstructorValidationRequired()
    {
        $this->expectException(DomainException::class);
        $pd = ['name' => 'property', 'type' => 'int', 'required' => 1, 'allowedValues' => [1,2]];
        new AcumulusProperty($pd);
    }

    public function testConstructorValidationAllowedValues()
    {
        $this->expectException(DomainException::class);
        $pd = ['name' => 'property', 'type' => 'int', 'required' => true, 'allowedValues' => true];
        new AcumulusProperty($pd);
    }

    public function testConstructor()
    {
        $pd = ['name' => 'property', 'type' => 'int', 'required' => true, 'allowedValues' => [1,2]];
        $p = new AcumulusProperty($pd);
        $this->assertEquals('property', $p->getName());
        $this->assertEquals(true, $p->isRequired());
        $this->assertNull($p->getValue());
    }

    public function setValueDataProvider(): array
    {
        $now = time();
        $floatNow = ((float) $now) + 0.5;
        return [
            'int-int' => ['int', 1, 1],
            'int-string' => ['int', '2', 2],
            'int-float' => ['int', 3.99999, 4],
            'int-negative' => ['int', -3, -3],
            'id-positive' => ['id', 3, 3],
            'float-int' => ['float', 1, 1.0],
            'float-string' => ['float', '1.234e2', 123.4],
            'float-negative' => ['float', -3.5, -3.5],
            'date-string' => ['date', '2022-09-01', DateTime::createFromFormat('Y-m-d', '2022-09-01')->setTime(0, 0, 0)],
            'date-int' => ['date', $now, DateTime::createFromFormat('U', $now)->setTime(0, 0, 0)],
            'date-float' => ['date', $floatNow, DateTime::createFromFormat('U.u', $floatNow)->setTime(0, 0, 0)],
        ];
    }

    /**
     * @dataProvider setValueDataProvider
     */
    public function testSetValue(string $type, $value, $castValue)
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
    public function testSetValueWrongValue(string $type, $value)
    {
        $this->expectException(DomainException::class);
        $pd = ['name' => 'property', 'type' => $type];
        $p = new AcumulusProperty($pd);
        $p->setValue($value);
    }

    public function testSetValueNotAllowedValue()
    {
        $this->expectException(DomainException::class);
        $pd = ['name' => 'property', 'type' => 'int', 'allowedValues' => [1,2]];
        $p = new AcumulusProperty($pd);
        $value = 3;
        $p->setValue($value);
    }
}
