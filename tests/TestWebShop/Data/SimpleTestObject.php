<?php

namespace Siel\Acumulus\Tests\TestWebShop\Data;

use Siel\Acumulus\Api;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\AcumulusProperty;

/**
 * Used to test basics of abstract parent classes, does not add features.
 *
 * @property ?string $itemNumber
 * @property ?string $nature
 * @property float $unitPrice
 *
 * @method getItemNumber(): string
 * @method getNature(): string
 * @method getUnitPrice(): float
 *
 * @method setItemNumber(string $value, int $mode = AcumulusProperty::Set_Always): void
 * @method setNature(string $value, int $mode = AcumulusProperty::Set_Always): void
 * @method setUnitPrice(float $value, int $mode = AcumulusProperty::Set_Always): void
 */
class SimpleTestObject extends AcumulusObject
{
    protected static array $propertyDefinitions = [
        ['name' => 'itemNumber', 'type' => 'string'],
        ['name' => 'nature', 'type' => 'string', 'allowedValues' => [Api::Nature_Product, Api::Nature_Service]],
        ['name' => 'unitPrice', 'type' => 'float', 'required' => true],
    ];
}
