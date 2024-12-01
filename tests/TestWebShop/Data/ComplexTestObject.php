<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Data;

use DateTimeInterface;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\PropertySet;

/**
 * ComplexTestObject contains some properties, another AcumulusObject and an array of
 * other AcumulusObjects.
 *
 * @property null|string $itemNumber
 * @property null|DateTimeInterface $date
 *
 * @method null|string getItemNumber()
 * @method null|DateTimeInterface getDate()
 *
 * @method void setItemNumber(string $value, int $mode = PropertySet::Always)
 * @method void setDate(mixed $value, int $mode = PropertySet::Always)
 */
class ComplexTestObject extends AcumulusObject
{
    public ?SimpleTestObject $simple;
    /** @var SimpleTestObject[] */
    public array $list = [];

    /**
     * Completes the shallow clone that PHP automatically performs.
     *
     * This override (deep) clones all properties referring to other
     * {@see AcumulusObject}s, being the invoice and shipping {@see Address}.
     * The $invoice property will be set from the {@see Invoice::__clone() cloned}
     * {@see Invoice}.
     */
    public function __clone(): void
    {
        parent::__clone();
        if (isset($this->simple)) {
            $this->simple = clone $this->simple;
        }
        foreach ($this->list as &$item) {
            $item = clone $item;
        }
    }

    protected function getPropertyDefinitions(): array
    {
        return [
            ['name' => 'itemNumber', 'type' => 'string'],
            ['name' => 'date', 'type' => 'date'],
        ];
    }
}
