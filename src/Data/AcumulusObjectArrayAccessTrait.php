<?php
/**
 * @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use ReturnTypeWillChange;
use RuntimeException;

use function array_key_exists;
use function count;

/**
 * Allows access to AcumulusObjects with array bracket syntax.
 *
 * This trait allows to access an {@see AcumulusObject} as if it is
 * an array. The preferred way of accessing object properties is by using the
 * getters and setters, but as we have a lot of old code that needs to be
 * transformed, we do implement the array acces way. However, we do so in a
 * separate trait as, eventually, we want to convert all usages of array access
 * to property access, direct or via a getter or setter, or the ominous
 * {@see AcumulusObject::set()} method, and remove this code.
 *
 * Note: as the old Acumulus arrays are already strict string key based arrays,
 * we don't allow numeric or null offsets.
 */
trait AcumulusObjectArrayAccessTrait
{
    /**
     * @var string[]
     *   Mappings from (legacy) lower case keys to their new camel case replacement.
     */
    private array $propertyMappings = [];

    private function getPropertyMappings(): array
    {
        if (count($this->propertyMappings) === 0) {
            foreach ($this->getPropertyDefinitions() as $propertyDefinition) {
                $this->propertyMappings[strtolower($propertyDefinition['name'])] = $propertyDefinition['name'];
            }
        }
        return $this->propertyMappings;
    }

    /**
     * Returns the name of the property $offset refers to.
     *
     * @param string $offset
     *    The name to look for. This may be the lowercase version of a property name.
     *
     * @return string|null
     *   The name of the property if the offset refers to a property, null otherwise.
     */
    private function getAcumulusProperty(string $offset): ?string
    {
        if ($this->isProperty($offset)) {
            return $offset;
        } else {
            $propertyMappings = $this->getPropertyMappings();
            if (array_key_exists($offset, $propertyMappings)) {
                return $propertyMappings[$offset];
            }
        }
        return null;
    }

    /**
     * Sets the value of the storage place inferred by $offset.
     *
     * $offset can refer to:
     * - An {@see AcumulusProperty}. $offset can be an exact match or the lowercase
     *   version of a property.
     * - A "normal" object property (if one exists).
     * - A metadata key (all other cases).
     */
    public function offsetSet($offset, $value): void
    {
        $this->checkOffset($offset);
        $propertyName = $this->getAcumulusProperty($offset);
        if ($propertyName !== null) {
            $this->set($propertyName, $value);
        } elseif (property_exists($this, $offset)) {
            /** @noinspection PhpVariableVariableInspection */
            $this->$offset = $value;
        } else {
            // Metadata.
            $this->getMetadata()->set($offset, $value);
        }
    }

    /**
     * @inheritdoc
     */
    public function offsetExists($offset): bool
    {
        $this->checkOffset($offset);
        $propertyName = $this->getAcumulusProperty($offset);
        if ($propertyName !== null) {
            $result = $this->__isset($propertyName);
        } elseif (property_exists($this, $offset)) {
            /** @noinspection PhpVariableVariableInspection */
            $result = isset($this->$offset);
        } else {
            // Metadata.
            $result = $this->getMetadata()->exists($offset);
        }
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function offsetUnset($offset): void
    {
        $this->checkOffset($offset);
        $propertyName = $this->getAcumulusProperty($offset);
        if ($propertyName !== null) {
            $this->__unset($propertyName);
        } elseif (property_exists($this, $offset)) {
            /** @noinspection PhpVariableVariableInspection */
            unset($this->$offset);
        } else {
            // Metadata.
            $this->getMetadata()->remove($offset);
        }
    }

    /**
     * @inheritdoc
     *
     * @noinspection PhpLanguageLevelInspection
     */
    #[ReturnTypeWillChange]
    public function &offsetGet($offset)
    {
        $this->checkOffset($offset);
        $propertyName = $this->getAcumulusProperty($offset);
        if ($propertyName !== null) {
            $result = &$this->__get($propertyName);
        } elseif (property_exists($this, $offset)) {
            /** @noinspection PhpVariableVariableInspection */
            $result = &$this->$offset;
        } else {
            // Metadata.
            $result = &$this->getMetadata()->get($offset);
        }
        return $result;
    }

    /**
     * @param mixed $offset
     *   We only allow string-keyed access, thus this should be a string. If not
     *   a {@see \RuntimeException} will be thrown.
     *
     * @throws \RuntimeException
     */
    private function checkOffset($offset): void
    {
        if ($offset === null) {
            throw new RuntimeException('Offset cannot be null');
        }
        if (is_numeric($offset)) {
            throw new RuntimeException('Offset cannot be numeric');
        }
    }
}
