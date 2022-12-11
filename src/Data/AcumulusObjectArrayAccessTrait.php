<?php
/**
 * @noinspection PhpUnused  The methods here are part of an interface tha maps
 *   array access syntax to these methods, so they will never be called
 *   directly, only internally in this trait.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use RuntimeException;

/**
 * Allows access to AcumulusObjects with array bracket syntax.
 *
 * This trait allows to access an {@see AcumulusObject} as if it is
 * an array. The preferred way of accessing object properties is by using the
 * getters and setters, but as we have a lot of old code that needs to be
 * transformed, we do implement the array acces way. However, we do so in a
 * separate trait as, eventually, we want to convert all usages of array access
 * to property access, direct or via a getter or setter, or the ominous
 * {@see AcumulusObject::set} method, and remove this code.
 *
 * Note: as the old Acumulus arrays are already strict string key based arrays,
 * we don't allow numeric or null offsets.
 */
trait AcumulusObjectArrayAccessTrait
{
    /**
     * @inheritdoc
     */
    public function offsetSet($offset, $value): void
    {
        $this->checkOffset($offset);
        if ($this->isProperty($offset)) {
            $this->set($offset, $value);
        } elseif (property_exists($this, $offset)) {
            /** @noinspection PhpVariableVariableInspection */
            $this->$offset = $value;
        } else {
            // Metadata.
            $this->metadata->set($offset, $value);
        }
    }

    /**
     * @inheritdoc
     */
    public function offsetExists($offset): bool
    {
        $this->checkOffset($offset);
        if ($this->isProperty($offset)) {
            $result = $this->__isset($offset);
        } elseif (property_exists($this, $offset)) {
            /** @noinspection PhpVariableVariableInspection */
            $result = isset($this->$offset);
        } else {
            // Metadata.
            $result = $this->metadata->exists($offset);
        }
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function offsetUnset($offset): void
    {
        $this->checkOffset($offset);
        if ($this->isProperty($offset)) {
            $this->__unset($offset);
        } elseif (property_exists($this, $offset)) {
            /** @noinspection PhpVariableVariableInspection */
            unset($this->$offset);
        } else {
            // Metadata.
            $this->metadata->remove($offset);
        }
    }

    /**
     * @inheritdoc
     */
    public function offsetGet($offset)
    {
        $this->checkOffset($offset);
        if ($this->isProperty($offset)) {
            $result = $this->__get($offset);
        } elseif (property_exists($this, $offset)) {
            /** @noinspection PhpVariableVariableInspection */
            $result = $this->$offset;
        } else {
            // Metadata.
            $result = $this->metadata->getValue($offset);
        }
        return $result;
    }

    /**
     * @param $offset
     *
     * @return void
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
