<?php

namespace Siel\Acumulus\Data;

use Siel\Acumulus\Api;

/**
 * @property ?string $itemNumber
 * @property ?string $product
 * @property ?string $nature
 * @property ?float $unitPrice
 * @property ?float $vatRate
 * @property ?float $quantity
 * @property ?float $costPrice
 *
 * @method bool setItemNumber(?string $value, int $mode = AcumulusProperty::Set_Always)
 * @method bool setProduct(?string $value, int $mode = AcumulusProperty::Set_Always)
 * @method bool setNature(?string $value, int $mode = AcumulusProperty::Set_Always)
 * @method bool setUnitPrice(?float $value, int $mode = AcumulusProperty::Set_Always)
 * @method bool setVatRate(?float $value, int $mode = AcumulusProperty::Set_Always)
 * @method bool setQuantity(?float $value, int $mode = AcumulusProperty::Set_Always)
 * @method bool setCostPrice(?float $value, int $mode = AcumulusProperty::Set_Always)
 */
class Line extends AcumulusObject
{
    protected static array $propertyDefinitions = [
        ['name' => 'itemNumber', 'type' => 'string'],
        ['name' => 'product', 'type' => 'string'],
        ['name' => 'nature', 'type' => 'string', 'allowedValues' => [Api::Nature_Product, Api::Nature_Service]],
        ['name' => 'unitPrice', 'type' => 'float', 'required' => true],
        ['name' => 'vatRate', 'type' => 'float', 'required' => true],
        ['name' => 'quantity', 'type' => 'float', 'required' => true],
        ['name' => 'costPrice', 'type' => 'float'],
    ];

    /** @var \Siel\Acumulus\Data\Line[] */
    protected array $children = [];

    /**
     * @return \Siel\Acumulus\Data\Line[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * @param \Siel\Acumulus\Data\Line $child
     */
    public function addChild(Line $child): void
    {
        $this->children[] = $child;
    }
}
