<?php

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Api;

/**
 * @property ?string $itemNumber
 * @property ?string $product
 * @property ?string $nature
 * @property float $unitPrice
 * @property float $vatRate
 * @property float $quantity
 * @property ?float $costPrice
 */
class Line extends AcumulusObject
{
    static protected array $propertyDefinitions = [
        ['name' => 'itemNumber', 'type' => 'string'],
        ['name' => 'product', 'type' => 'string'],
        ['name' => 'nature', 'type' => 'string', 'allowedValues' => [Api::Nature_Product, Api::Nature_Service]],
        ['name' => 'unitPrice', 'type' => 'float', 'required' => true],
        ['name' => 'vatRate', 'type' => 'float', 'required' => true],
        ['name' => 'quantity', "type" => 'float', 'required' => true],
        ['name' => 'costPrice', 'type' => 'float'],
    ];
}
