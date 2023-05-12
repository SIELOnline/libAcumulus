<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Data;

use Siel\Acumulus\Api;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\PropertySet;

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
 * @method setItemNumber(string $value, int $mode = PropertySet::Always): void
 * @method setNature(string $value, int $mode = PropertySet::Always): void
 * @method setUnitPrice(float $value, int $mode = PropertySet::Always): void
 */
class SimpleTestObject extends AcumulusObject
{
    protected function getPropertyDefinitions(): array
    {
        return [
            ['name' => 'itemNumber', 'type' => 'string'],
            ['name' => 'nature', 'type' => 'string', 'allowedValues' => [Api::Nature_Product, Api::Nature_Service]],
            ['name' => 'unitPrice', 'type' => 'float', 'required' => true],
        ];
    }
}
