<?php

namespace Siel\Acumulus\Data;

class MetadataCollection
{
    /** @var \Siel\Acumulus\Data\MetadataValue[]  */
    private array $metadata = [];

    public function exists(string $name): bool
    {
        return isset($this->metadata[$name]);
    }

    /**
     * Returns the MetadataValue for $name, or null if not set.
     */
    public function get(string $name): ?MetadataValue
    {
        return $this->metadata[$name] ?? null;
    }

    /**
     * Returns the value for the given metadata name, or null if not set.
     *
     * If the metadata contains multiple values, an array of values will be,
     * returned, but if the value itself is an array, it may be difficult to
     * distinguish these 2 situations. For these cases, use
     * {@see MetadataValue::count()}.
     *
     * @param string $name
     *
     * @return mixed|null
     */
    public function getValue(string $name)
    {
        return $this->exists($name) ? $this->get($name)->get() : null;
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
     * @param $value
     *   The value for the metadata field.
     */
    public function set(string $name, $value): void
    {
        $this->metadata[$name] = new MetadataValue($value);
    }

    /**
     * Adds a value to a metadata field, creating it if it not already exists.
     *
     * If the metadata name does not already exist, it will be set to this value.
     * If the metadata name already exists and has 1 value, the metadata value
     * will be changed to an array with its current value and this value as
     * entries. If the metadata name has already multiple values, this value will
     * be added to the set of values (without checking for double entries).
     *
     * @param string $name
     *   The name for the metadata field.
     * @param $value
     *   The value to add to (or set for) the metadata field.
     */
    public function add(string $name, $value): void
    {
        $this->exists($name) ? $this->metadata[$name]->add($value) : $this->set($name, $value);
    }

    /**
     * @return string[]
     */
    public function getKeys(): array
    {
        return array_keys($this->metadata);
    }
}
