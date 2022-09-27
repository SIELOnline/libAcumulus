<?php

namespace Siel\Acumulus\Invoice;

use ArgumentCountError;
use BadMethodCallException;
use RuntimeException;

/**
 * AcumulusObject represents an Acumulus API call message structure.
 *
 * The structure may be a part of a full API call message structure, e.g. the
 * parts 'contract' and 'connector' of the basic submit structure are
 * represented as separate objects here.
 *
 * In some parts of the Acumulus API specification, not sending a tag differs in
 * meaning from sending that tag with no value:
 * - In the 'customer' part of the Invoice add call this is  handled as leaving
 *   an existing value as is vs clearing an existing value.
 * - Tags having a default value can be given that default value by not sending
 *   that tag, not by sending that tag without value.
 *
 * Therefore, {@see AcumulusObject} and {@see AcumulusProperty} will handle not
 * set values and empty values differently.
 */
abstract class AcumulusObject extends Metadata
{
    /**
     * @var array[]
     *   an array of property definitions, a property definition being a keyed
     *   array with keys 'name', 'type', 'required' (optional), and
     *   'allowedValues' (optional).
     */
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
        $this->checkIsProperty($name);
        return $this->data[$name]->getValue();
    }

    public function __set(string $name, $value): void
    {
        $this->set($name, $value);
    }

    public function __isset(string $name): bool
    {
        $this->checkIsProperty($name);
        return $this->data[$name]->getValue() !== null;
    }

    public function __unset(string $name): void
    {
        $this->checkIsProperty($name);
        $this->data[$name]->setValue(null);
    }

    public function __call(string $name, array $arguments)
    {

        if (substr($name, 0, strlen('set')) !== 'set') {
            throw new BadMethodCallException("Undefined method '$name'");
        }
        $count = count($arguments);
        if ($count < 1 || $count > 2) {
            throw new ArgumentCountError("Incorrect number of arguments ($count) for method '$name'");
        }
        $propertyName = lcfirst(substr($name, strlen('set')));
        return $this->set($propertyName, ...$arguments);
    }

    private function isProperty(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }

    /**
     * @throws \RuntimeException
     *   $name is not an existing property name.
     */
    private function checkIsProperty(string $name): void
    {
        if (!$this->isProperty($name)) {
            throw new RuntimeException("Unknown property: $name");
        }
    }

    /**
     * Assigns a value to the property.
     *
     * @param string $name
     *   The name of the property to set.
     * @param string|int|float|\DateTime|null $value
     *   The value to assign to this property, null is a valid value and will
     *   "unset" this property (it will not appear in the Acumulus API message).
     * @param int $mode
     *   1 of the AcumulusProperty::Set_... constants to prevent setting an
     *   empty value and/or overwriting an already set value. Default is to
     *   unconditionally set the value.
     *
     * @return bool
     *   true if the value was actually set, false otherwise.
     */
    public function set(string $name, $value, int $mode = AcumulusProperty::Set_Always): bool
    {
        $this->checkIsProperty($name);
        return $this->data[$name]->setValue($value, $mode);
    }
}
