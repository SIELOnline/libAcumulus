<?php

namespace Siel\Acumulus\Invoice;

abstract class Metadata
{
    private array $metadata = [];

    /**
     * Returns the value for the given metadata name, or null if not set.
     *
     * If the metadata contains multiple values, an array of values will be,
     * returned, but if the value itself is an array, it may be difficult to
     * distinguish these 2 situations.
     *
     * @param string $name
     *
     * @return mixed|null
     */
    public function getMetadata(string $name)
    {
        return array_key_exists($name, $this->metadata) ? $this->metadata[$name]['value'] : null;
    }

    /**
     * Returns the number of values set for the given metadata name.
     *
     * If the metadata contains multiple values, an array of values will be,
     * returned, but if the value itself is an array, it may be difficult to
     * distinguish these 2 situations. In these cases this method may be useful.
     *
     * @param string $name
     *
     * @return int
     */
    public function getMetadataCount(string $name): int
    {
        return array_key_exists($name, $this->metadata) ? $this->metadata[$name]['count'] : 0;
    }

    /**
     * Sets a metadata field.
     *
     * If the metadata name already exists, the value will be overwritten.
     *
     * @param string $name
     *   The name for the metadata field.
     * @param $value
     *   The value for the metadata field.
     */
    public function setMetadata(string $name, $value): void
    {
        unset($this->metadata[$name]);
        $this->addMetadata($name, $value);
    }

    /**
     * Adds a metadata field.
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
    public function addMetadata(string $name, $value): void
    {
        if (!array_key_exists($name, $this->metadata)) {
            $this->metadata[$name] = ['count' => 1, 'value' => $value];
        } else {
            if ($this->metadata['count'] === 1) {
                $this->metadata['value'] = [$this->metadata['value']];
            }
            $this->metadata['count']++;
            $this->metadata['value'][] = $value;
        }
    }

    /**
     * @return string[]
     */
    public function getMetadataNames(): array
    {
        return array_keys($this->metadata);
    }
}
