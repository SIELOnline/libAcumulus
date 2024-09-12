<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use function array_key_exists;

/**
 * MetadataCollection represents a collection of {@see MetadataValue}s.
 */
class MetadataCollection
{
    /** @var MetadataValue[] $metadata */
    private array $metadata = [];

    public function exists(string $name): bool
    {
        return array_key_exists($name, $this->metadata);
    }

    /**
     * Returns the {@see MetadataValue} object for $name, or null if not set.
     *
     * @legacy: the return by reference is to make the ArrayAccess working.
     */
    public function &getMetadataValue(string $name): ?MetadataValue
    {
        $result = $this->metadata[$name] ?? null;
        return $result;
    }

    /**
     * Returns the value for the given metadata name, or null if not set.
     *
     * If the metadata contains multiple values, an array of values will be,
     * returned, but if the value itself is an array, it may be difficult to
     * distinguish these 2 situations. For these cases, use
     * {@see MetadataValue::count()}.
     *
     * @return array|mixed|null
     *   The value for the given metadata name, or null if not set.
     *
     * @legacy: the return by reference is to make the ArrayAccess working.
     */
    public function &get(string $name)
    {
        /** @noinspection NullPointerExceptionInspection */
        $result = $this->exists($name) ? $this->getMetadataValue($name)->get() : null;
        return $result;
    }

    /**
     * Unsets a metadata field.
     */
    public function remove(string $name): void
    {
        unset($this->metadata[$name]);
    }

    /**
     * Sets a metadata field, overwriting it if it already exists.
     *
     * @param string $name
     *   The name for the metadata field.
     * @param mixed $value
     *   The value for the metadata field.
     */
    public function set(string $name, mixed $value): void
    {
        $this->metadata[$name] = new MetadataValue($value);
    }

    /**
     * Sets a set of metadata fields.
     *
     * @param array $values
     *   An array keyed by the names of the metadata field to set.
     */
    public function setMultiple(array $values): void
    {
        foreach ($values as $name => $value) {
            $this->set($name, $value);
        }
    }

    /**
     * Adds a value to a metadata field, creating it if it not already exists.
     *
     * - If the metadata name does not already exist, it will be set to this
     *   value.
     * - If the metadata name already exists, this value will be added to it
     *   (without checking for double entries). A metadata field can thus
     *   contain multiple values.
     *
     * @param string $name
     *   The name for the metadata field.
     * @param mixed $value
     *   The value to add to (or set for) the metadata field.
     */
    public function add(string $name, mixed $value): void
    {
        $this->exists($name) ? $this->metadata[$name]->add($value) : $this->set($name, $value);
    }

    /**
     * @todo: has this a use outside testing?
     *
     * @return string[]
     */
    public function getKeys(): array
    {
        return array_keys($this->metadata);
    }

    public function toArray(): array
    {
        $result = [];
        foreach ($this->metadata as $key => $value) {
            $result[$key] = $value->getApiValue();
        }
        return $result;
    }
}
