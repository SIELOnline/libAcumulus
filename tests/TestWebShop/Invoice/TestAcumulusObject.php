<?php

namespace Siel\Acumulus\TestWebShop\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Invoice\AcumulusObject;

/**
 * Used to test basics of abstract parent classes, does not add features.
 *
 * @property ?string $itemNumber
 * @property ?string $nature
 * @property float $unitPrice
 */
class TestAcumulusObject extends AcumulusObject
{
    protected static array $propertyDefinitions = [
        ['name' => 'itemNumber', 'type' => 'string'],
        ['name' => 'nature', 'type' => 'string', 'allowedValues' => [Api::Nature_Product, Api::Nature_Service]],
        ['name' => 'unitPrice', 'type' => 'float', 'required' => true],
    ];
}
