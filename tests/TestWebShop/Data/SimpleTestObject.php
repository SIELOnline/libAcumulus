<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Data;

use Siel\Acumulus\Api;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\PropertySet;

/**
 * Used to test basics of abstract parent classes, does not add features.
 *
 * @property null|string $itemNumber
 * @property null|string $nature
 * @property null|float $unitPrice
 *
 * @method null|string getItemNumber()
 * @method null|string getNature()
 * @method null|float getUnitPrice()
 *
 * @method void setItemNumber(string $value, int $mode = PropertySet::Always)
 * @method void setNature(string $value, int $mode = PropertySet::Always)
 * @method void setUnitPrice(float $value, int $mode = PropertySet::Always)
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
