<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use Siel\Acumulus\Api;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Meta;

/**
 * Represents an invoice line object of an Acumulus API invoice object.
 *
 * @property ?string $itemNumber
 * @property ?string $product
 * @property ?string $nature
 * @property ?float $unitPrice
 * @property ?float $vatRate
 * @property ?float $quantity
 * @property ?float $costPrice
 *
 * @method bool setItemNumber(?string $value, int $mode = PropertySet::Always)
 * @method bool setProduct(?string $value, int $mode = PropertySet::Always)
 * @method bool setNature(?string $value, int $mode = PropertySet::Always)
 * @method bool setUnitPrice(?float $value, int $mode = PropertySet::Always)
 * @method bool setVatRate(?float $value, int $mode = PropertySet::Always)
 * @method bool setQuantity(?float $value, int $mode = PropertySet::Always)
 * @method bool setCostPrice(?float $value, int $mode = PropertySet::Always)
 */
class Line extends AcumulusObject
{
    /** @var \Siel\Acumulus\Data\Line[] */
    protected array $children = [];

    /**
     * Completes the shallow clone that PHP automatically performs.
     *
     * This override (deep) clones all properties referring to other
     * {@see AcumulusObject}s, being the set of {@see $children child} {@see Line lines}.
     */
    public function __clone(): void
    {
        parent::__clone();
        foreach ($this->children as &$child) {
            $child = clone $child;
        }
    }

    protected function getPropertyDefinitions(): array
    {
        return [
            ['name' => Fld::ItemNumber, 'type' => 'string'],
            ['name' => Fld::Product, 'type' => 'string'],
            ['name' => Fld::Nature, 'type' => 'string', 'allowedValues' => [Api::Nature_Product, Api::Nature_Service]],
            ['name' => Fld::UnitPrice, 'type' => 'float', 'required' => true],
            ['name' => Fld::VatRate, 'type' => 'float', 'required' => true],
            ['name' => Fld::Quantity, 'type' => 'float', 'required' => true],
            ['name' => Fld::CostPrice, 'type' => 'float'],
        ];
    }

    /**
     * Returns the type of this line.
     *
     * @return string
     *   One of the {@see LineType} constants.
     */
    public function getType(): string
    {
        return $this->metadataGet(Meta::SubType);
    }

    /**
     * Sets the type of this line
     *
     * @param string $type
     *   One of the {@see LineType} constants.
     */
    public function setType(string $type): void
    {
        $this->metadataSet(Meta::SubType, $type);
    }

    /**
     * @return \Siel\Acumulus\Data\Line[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function addChild(Line $child): void
    {
        $this->children[] = $child;
    }

    public function removeChildren(): void
    {
        $this->children = [];
    }

    public function hasWarning(): bool
    {
        $hasWarning = parent::hasWarning();
        if (!$hasWarning) {
            foreach ($this->getChildren() as $line) {
                if ($line->hasWarning()) {
                    $hasWarning  = true;
                    break;
                }
            }
        }
        return $hasWarning;
    }
}
