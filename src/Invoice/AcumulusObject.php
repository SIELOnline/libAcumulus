<?php

namespace Siel\Acumulus\Invoice;

use RuntimeException;

abstract class AcumulusObject extends Metadata
{
    /** @var array[]  */
    static protected array $propertyDefinitions = [];

    /** @var \Siel\Acumulus\Invoice\AcumulusProperty[] $data */
    private array $data = [];

    public function __construct()
    {
        foreach (static::$propertyDefinitions as $propertyDefinition) {
            $property = new AcumulusProperty($propertyDefinition);
            $this->data[$property->getName()] = $property;
        }
    }

    public function __get(string $name)
    {
        if (!array_key_exists($name, $this->data)) {
            throw new RuntimeException("Unknown property: $name");
        }
        return $this->data[$name]->getValue();
    }

    public function __set(string $name, $value): void
    {
        if (!array_key_exists($name, $this->data)) {
            throw new RuntimeException("Unknown property: $name");
        }
        $this->data[$name]->setValue($value);
    }

    public function __isset(string $name): bool
    {
        return $this->data[$name]->getValue() !== null;
    }

    public function __unset(string $name): void
    {
        $this->data[$name]->setValue(null);
    }
}
