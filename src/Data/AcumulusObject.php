<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use ArrayAccess;
use BadMethodCallException;
use RuntimeException;

use function array_key_exists;
use function count;
use function strlen;

/**
 * AcumulusObject represents an Acumulus API call message structure.
 *
 * The structure may be a part of a full API call message structure, e.g. the
 * parts 'contract' and 'connector' of the basic submit structure are
 * represented as separate objects here.
 *
 * In some parts of the Acumulus API specification, not sending a tag differs in
 * meaning from sending that tag with no value:
 * - In the 'customer' part of the Data add call this is  handled as leaving
 *   an existing value as is vs clearing an existing value.
 * - Tags having a default value can be given that default value by not sending
 *   that tag, not by sending that tag without value.
 *
 * Therefore, {@see AcumulusObject} and {@see AcumulusProperty} will handle not
 * set values and empty values differently.
 */
abstract class AcumulusObject implements ArrayAccess
{
    use AcumulusObjectArrayAccessTrait;

    /**
     * @var array[]
     *   an array of property definitions, a property definition being a keyed
     *   array with keys 'name', 'type', 'required' (optional), and
     *   'allowedValues' (optional).
     */
    protected static array $propertyDefinitions = [];

    /** @var \Siel\Acumulus\Data\AcumulusProperty[] $data */
    private array $data = [];
    private MetadataCollection $metadata;

    public function __construct()
    {
        foreach (static::$propertyDefinitions as $propertyDefinition) {
            $property = new AcumulusProperty($propertyDefinition);
            $this->data[$property->getName()] = $property;
        }
        $this->metadata = new MetadataCollection();
    }

    // PHP 8.1: a read-only property suffices here.
    /** @noinspection PhpEnforceDocCommentInspection */
    public function getMetadata(): MetadataCollection
    {
        return $this->metadata;
    }

    /**
     * Implements direct property get access for the
     * {@see AcumulusProperty}s of this object, thus not
     * properties referring to another {@see Acumulusobject}, nor
     * {@see MetadataCollection} properties.
     *
     * @throws \RuntimeException
     *   $name is not an existing name of an {@see AcumulusProperty}.
     */
    public function __get(string $name)
    {
        $this->checkIsProperty($name);
        return $this->data[$name]->getValue();
    }

    /**
     * Implements direct property set access with
     * {@see AcumulusProperty::Set_Always} semantics for the
     * {@see AcumulusProperty}s of this object, thus not
     * properties referring to another {@see Acumulusobject}, nor
     * {@see MetadataCollection} properties.
     *
     * @throws \RuntimeException
     *   $name is not an existing name of an {@see AcumulusProperty}.
     */
    public function __set(string $name, $value): void
    {
        $this->set($name, $value);
    }

    /**
     * Implements direct property isset() acces for the {@see AcumulusProperty}s
     * of this object, thus not properties referring to another
     * {@see Acumulusobject}, nor {@see MetadataCollection} properties.
     *
     * @throws \RuntimeException
     *   $name is not an existing name of an {@see AcumulusProperty}.
     */
    public function __isset(string $name): bool
    {
        $this->checkIsProperty($name);
        return $this->data[$name]->getValue() !== null;
    }

    /**
     * @param string $name
     * @param array $arguments
     *
     * @return bool
     *
     * @throws \RuntimeException
     *   $name is not an existing getter or setter of an
     *   {@see AcumulusProperty}.
     */
    public function __call(string $name, array $arguments)
    {
        $method = substr($name, 0, 3);
        $propertyName = lcfirst(substr($name, 3));
        $count = count($arguments);
        switch ($method) {
            case 'get':
                if ($count !== 0) {
                    throw new BadMethodCallException("No arguments expected for method '$name', $count passed");
                }
                return $this->__get($propertyName);
            case 'set':
                if ($count !== 1 && $count !== 2) {
                    throw new BadMethodCallException("Expected 1 or 2 arguments for method $name, $count passed'");
                }
                $propertyName = lcfirst(substr($name, strlen('get')));
                return $this->set($propertyName, ...$arguments);
            default:
                throw new BadMethodCallException("Undefined method '$name'");
        }
    }

    /**
     * Implements direct property unset access , but only for
     * {@see AcumulusProperty}s, not properties referring to another
     * {@see Acumulusobject}, nor {@see MetadataCollection} properties.
     *
     * @param string $name
     *   The name of the property
     *
     * @throws \RuntimeException
     *   $name is not an existing name of an {@see AcumulusProperty}.
     */
    public function __unset(string $name): void
    {
        $this->checkIsProperty($name);
        $this->data[$name]->setValue(null);
    }

    /**
     * Returns whether $name is an {@see AcumulusPorperty} of this
     * {@see AcumulusObject}
     */
    protected function isProperty(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }

    /**
     * Checks whether  $name is an {@see AcumulusPorperty} of this
     * {@see AcumulusObject}.
     *
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
     *   One of the AcumulusProperty::Set_... constants that can be used to
     *   prevent setting an empty value and/or overwriting an already set value.
     *   Default is to unconditionally set the value.
     *
     * @return bool
     *   True if the value was actually set, false otherwise.
     *
     * @throws \RuntimeException
     *   $name is not an existing name of an {@see AcumulusProperty}.
     */
    public function set(string $name, $value, int $mode = AcumulusProperty::Set_Always): bool
    {
        $this->checkIsProperty($name);
        return $this->data[$name]->setValue($value, $mode);
    }
}
