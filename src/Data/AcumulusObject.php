<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use BadMethodCallException;
use ReflectionClass;
use RuntimeException;

use Siel\Acumulus\Meta;

use function array_key_exists;
use function count;
use function sprintf;
use function strlen;

/**
 * AcumulusObjects represent Acumulus API message structures.
 *
 * An AcumulusObject may represent only a part of a full API message structure.
 * E.g. the parts 'contract' and 'connector' of the "basic submit" structure are
 * represented as separate objects here.
 *
 * AcumulusObjects are made of:
 * - Elements of the message are represented by
 *   {@see AcumulusProperty AcumulusProperties}: a single (scalar) value data
 *   container that will check and cast values.
 * - Repeating elements are represented by an array of sub-AcumulusObjects.
 * - The Acumulus API accepts messages with additional fields, it just ignores
 *   them. We use this to add metadata, data that is thus not part of the
 *   specified message structure but contains additional information used in
 *   later stages, logging, or debugging.
 *
 * AcumulusObjects are easy to use:
 * - All message elements are accessible as a (magic) property: you can read,
 *   write, and unset them.
 * - They are also accessible via (magic) getters and setters methods.
 * - And they can be set via the {@see set()} method.
 * - Metadata is easily added via the {@see MetadataCollection} interface.
 * - For backwards compatibility, accessing elements is also possible via array
 *   access syntax.
 * In some parts of the Acumulus API specification, not sending a tag differs in
 * meaning from sending that tag with no value:
 * - In the 'customer' part of the Data add call this is handled as leaving an
 *   existing value as is vs clearing an existing value.
 * - Tags having a default value can be given that default value by not sending
 *   that tag, not by sending that tag without value.
 * Therefore:
 * - {@see AcumulusObject} and {@see AcumulusProperty} will handle not set
 *   values and empty values differently.
 * - The (magic) setters and the {@see set()} method accept an optional flag
 *   that defines how to handle overwriting already set values and if to set
 *   empty values.
 *
 * Field names on the child classes are copied from the API, though capitals are
 * introduced for readability and to prevent PhpStorm typo inspections.
 *
 * Metadata can be added via the {@see MetadataCollection} methods.
 */
abstract class AcumulusObject
{
    use AcumulusObjectMetadataTrait;

    /** @var \Siel\Acumulus\Data\AcumulusProperty[] */
    private array $data = [];

    public function __construct()
    {
        foreach ($this->getPropertyDefinitions() as $propertyDefinition) {
            $property = new AcumulusProperty($propertyDefinition);
            $this->data[$property->getName()] = $property;
        }
    }

    /**
     * Completes the shallow clone that PHP automatically performs.
     *
     * This base implementation (deep) clones all
     * {@see AcumulusProperty AcumulusProperties} and the {@see MetadataCollection}.
     */
    public function __clone(): void
    {
        foreach ($this->data as &$acumulusProperty) {
            $acumulusProperty = clone $acumulusProperty;
        }
        $this->cloneMetadata();
    }

    /**
     * @return array[]
     *   An array of property definitions, a property definition being a keyed
     *   array with keys 'name', 'type', 'required' (optional), and
     *   'allowedValues' (optional).
     *
     * @todo: make static? => lighter objects?
     */
    abstract protected function getPropertyDefinitions(): array;

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
        $name = $this->checkIsProperty($name);
        return $this->data[$name]->getValue();
    }

    /**
     * Implements direct property set access with
     * {@see PropertySet::Always} semantics for the
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
        $name = $this->checkIsProperty($name);
        return $this->data[$name]->getValue() !== null;
    }

    /**
     * @param string $name
     *   Method name.
     * @param array $arguments
     *   Arguments to pass to the method call.
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
     * Implements direct property unset access, but only for
     * {@see AcumulusProperty}s, not properties referring to another
     * {@see AcumulusObject}, nor {@see MetadataCollection} properties.
     *
     * @param string $name
     *   The name of the property
     *
     * @throws \RuntimeException
     *   $name is not an existing name of an {@see AcumulusProperty}.
     */
    public function __unset(string $name): void
    {
        $name = $this->checkIsProperty($name);
        $this->data[$name]->setValue(null);
    }

    /**
     * Returns whether $name is an {@see AcumulusProperty} of this {@see AcumulusObject}.
     *
     * @param string $name
     *   The name to search for.
     */
    public function isProperty(string $name): bool
    {
        return $this->getPropertyName($name) !== null;
    }

    /**
     * Checks whether $name is an {@see AcumulusProperty} of this {@see AcumulusObject}.
     *
     * @param string $name
     *   The name to search for.
     *
     * @return string
     *   The real name under which $name is stored as property, this may be the lower
     *   cased version of $name.
     *
     * @throws \RuntimeException
     *   $name is not an existing property name.
     */
    private function checkIsProperty(string $name): string
    {
        $realName = $this->getPropertyName($name);
        if ($realName === null) {
            throw new RuntimeException("Unknown property: $name");
        }
        return $realName;
    }

    /**
     * Returns the name under which $name is stored as an {@see AcumulusProperty} of this
     * {@see AcumulusObject}.
     *
     * @param string $name
     *   The name to search for.
     *
     * @return null|string
     *   The real name under which the property is stored, or null if $name is not a
     *   property.
     */
    public function getPropertyName(string $name): ?string
    {
        if (array_key_exists(strtolower($name), $this->data)) {
            return strtolower($name);
        } elseif (array_key_exists($name, $this->data)) {
            return $name;
        } else {
            return null;
        }
    }

    /**
     * Assigns a value to the property.
     *
     * @param string $name
     *   The name of the property to set.
     * @param mixed $value
     *   The value to assign to this property, null is a valid value and will
     *   "unset" this property (it will not appear in the Acumulus API message).
     * @param int $mode
     *   One of the {@see PropertySet}::... constants that can be used to
     *   prevent setting an empty value and/or overwriting an already set value.
     *   Default is to unconditionally set the value.
     *
     * @return bool
     *   True if the value was actually set, false otherwise.
     *
     * @throws \RuntimeException
     *   $name is not an existing name of an {@see AcumulusProperty}.
     */
    public function set(string $name, mixed $value, int $mode = PropertySet::Always): bool
    {
        $name = $this->checkIsProperty($name);
        return $this->data[$name]->setValue($value, $mode);
    }

    /**
     * Returns the AcumulusObject as a keyed array.
     *
     * @return array
     *   The properties and metadata of this object as a keyed array. The keys
     *   are the (non-lower-cased) property names or metadata keys, the values will
     *   be scalars or a(n) (recursive) array of scalars.
     */
    public function toArray(): array
    {
        return $this->propertiesToArray() + $this->metadataToArray();
    }

    /**
     *  Returns the AcumulusObject properties as a keyed array of strings.
     *
     * @return array
     *   The properties as a keyed array of values, the keys being the (non-lower-cased)
     *   names of the property
     */
    protected function propertiesToArray(): array
    {
        $result = [];
        foreach ($this->data as $name => $property) {
            /** @noinspection PhpVariableVariableInspection */
            if (isset($this->$name)) {
                $result[$name] = $property->getApiValue();
            } elseif ($this->data[$name]->isRequired()) {
                // Do not throw an exception as that will prevent the message being part
                // of the error mail, but instead add a meta-warning.
                $result[Meta::Error] = sprintf(
                    'Required property %s::%s is not set',
                    (new ReflectionClass($this))->getShortName(),
                    $name
                );
            }
        }
        return $result;
    }
}
