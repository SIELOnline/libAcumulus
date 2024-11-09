<?php
/**
 * @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use RuntimeException;

use function array_key_exists;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Allows access to AcumulusObjects with array bracket syntax and Acumulus tags (all lower
 * case).
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
 *
 * @legacy: ArrayAccess is implemented on {@See \Siel\Acumulus\Data\AcumulusObject} to
 *   allow legacy code to access the objects like arrays.
 */
trait AcumulusObjectArrayAccessTrait
{
    /**
     * @var string[]
     *   Mappings from (legacy) lower case keys to their new camel case replacement.
     */
    private array $offsetMappings;

    /**
     * @return string[]
     */
    protected function getOffsetMappings(): array
    {
        if (!isset($this->offsetMappings)) {
            $this->offsetMappings = [];
            foreach ($this->getPropertyDefinitions() as $propertyDefinition) {
                if (strtolower($propertyDefinition['name']) !== $propertyDefinition['name']) {
                    $this->offsetMappings[strtolower($propertyDefinition['name'])] = $propertyDefinition['name'];
                }
            }
        }
        return $this->offsetMappings;
    }

    /**
     * Returns the name of the property $offset refers to.
     *
     * @param string $offset
     *    The name to look for. This may be:
     *    - The correct name of an existing property ($invoice['customer']['email']).
     *    - The lowercase version of a property name ($invoice['customer']['vatnumber']).
     *    - The (lowercase) name of an address field but called on Customer
     *      ($invoice['customer']['companyname']).
     *    - The singular version of lines ($invoice['customer']['invoice']['line']).
     *    - The name of a sub AcumulusObject ($invoice['customer']['invoice']).
     *    - All other offsets are seen as metadata keys
     *
     * @return string|array
     *   The mapped name of $offset if it refers to a property under a (slightly) other
     *   name, $offset self otherwise. An array will be returned if the offset can be
     *   found in another object: the first element being the object, the 2nd being the
     *   name of the property in that object.
     */
    private function mapOffset(string $offset): array|string
    {
        if (!$this->isProperty($offset) && !property_exists($this, $offset)) {
            $propertyMappings = $this->getOffsetMappings();
            if (array_key_exists($offset, $propertyMappings)) {
                $offset = $propertyMappings[$offset];
            }
        }
        return $offset;
    }

    /**
     * @inheritdoc
     *
     * Note: the return by reference is to make the ArrayAccess working.
     */
    public function &offsetGet(mixed $offset): mixed
    {
        $this->checkOffset($offset);
        $offset = $this->mapOffset($offset);
        if (is_array($offset)) {
            $result = &$offset[0]->{$offset[1]};
        } elseif ($this->isProperty($offset)) {
            $result = &$this->__get($offset);
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
     * Sets the value of the storage place inferred by $offset.
     *
     * $offset can refer to:
     * - An {@see AcumulusProperty}. $offset can be an exact match or the lowercase
     *   version of a property.
     * - A "normal" object property (if one exists).
     * - A metadata key (all other cases).
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->checkOffset($offset);
        $offset = $this->mapOffset($offset);
        if (is_array($offset)) {
            $offset[0]->{$offset[1]} = $value;
        } elseif ($this->isProperty($offset)) {
            $this->set($offset, $value);
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
    public function offsetExists(mixed $offset): bool
    {
        $this->checkOffset($offset);
        $offset = $this->mapOffset($offset);
        if (is_array($offset)) {
            $result = isset($offset[0]->{$offset[1]});
        } elseif ($this->isProperty($offset)) {
            $result = $this->__isset($offset);
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
    public function offsetUnset(mixed $offset): void
    {
        $this->checkOffset($offset);
        $offset = $this->mapOffset($offset);
        if (is_array($offset)) {
            unset($offset[0]->{$offset[1]});
        } elseif ($this->isProperty($offset)) {
            $this->__unset($offset);
        } elseif (property_exists($this, $offset)) {
            /** @noinspection PhpVariableVariableInspection */
            unset($this->$offset);
        } else {
            // Metadata.
            $this->getMetadata()->remove($offset);
        }
    }

    /**
     * @param mixed $offset
     *   We only allow string-keyed access, thus this should be a string. If not
     *   a {@see \RuntimeException} will be thrown.
     *
     * @throws \RuntimeException
     */
    private function checkOffset(mixed $offset): void
    {
        if (!is_string($offset)) {
            throw new RuntimeException(sprintf('Offset must be a string: %s given', get_debug_type($offset)));
        }
    }
}
