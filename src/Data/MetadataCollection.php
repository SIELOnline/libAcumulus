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

    /**
     * Completes cloning a {@see MetadataCollection}.
     *
     * The clone will contain a copy of the array but all entries will refer to the same
     * {@see MetadataValue} object as the entries in the original array, so we need to
     * clone each array entry.
     */
    public function __clone(): void
    {
        foreach ($this->metadata as &$metadata) {
            $metadata = clone $metadata;
        }
    }

    /**
     * Returns whether the metadata name exists, even if it is null.
     */
    public function exists(string $name): bool
    {
        return array_key_exists($name, $this->metadata);
    }

    /**
     * Returns the {@see MetadataValue} object for $name, or null if not set.
     */
    public function getMetadataValue(string $name): ?MetadataValue
    {
        return $this->metadata[$name] ?? null;
    }

    /**
     * Returns the value for the given metadata name, or null if not set.
     *
     * If the metadata contains multiple values, and $index = null, an array of values
     * will be returned, but if the value itself is an array, also an array will be
     * returned, so iit may be difficult to distinguish these 2 situations. For these
     * cases, use {@see MetadataValue::count()}.
     *
     * @param string $name
     *   The name of the metadata value to return.
     * @param int|null $index
     *   The index of the value to return (if it is a list).
     *
     * @return array|mixed|null
     *   The value for the given metadata name at the given index, or null if not set or
     *   the index does not exist.
     */
    public function get(string $name, ?int $index = null): mixed
    {
        return $this->getMetadataValue($name)?->get($index);
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
        $this->metadata[$name] = (new MetadataValue(false))->add($value);
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
     *   The value to add to (or set for) the metadata field. If $isList = true and this
     *   is a new metadata value and $value is null: an empty list is created (not a list
     *   with null as first value).
     * @param bool $isList
     *   Whether to handle this metadata field as a list (if it has only 1 value).
     */
    public function add(string $name, mixed $value, bool $isList = true): void
    {
        if (!$this->exists($name)) {
            $this->metadata[$name] = new MetadataValue($isList);
            if ($isList && $value === null) {
                return;
            }
        }
        $this->metadata[$name]->add($value);
    }

    /**
     * Sets a set of metadata fields.
     *
     * @param string $name
     *   The name for the metadata field.
     * @param array $values
     *   The values to add to the metadata field.
     */
    public function addMultiple(string $name, array $values): void
    {
        // Creates an empty list (if $values is empty).
        $this->add($name, null, true);
        foreach ($values as $value) {
            $this->add($name, $value, true);
        }
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
