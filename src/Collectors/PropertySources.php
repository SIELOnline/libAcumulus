<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Config\Mappings;

/**
 * PropertySources is a set of objects that provides properties to the collector phase.
 *
 * The Collector phase is all about extracting values from shop objects into
 * {@see \Siel\Acumulus\Data\AcumulusObject}s, be it in the:
 * - The {@see \Siel\Acumulus\Collectors\Collector::collectMappedFields()} phase, where
 *   {@see Mappings} are passed to a {@see \Siel\Acumulus\Helpers\FieldExpander}, or the
 * - The {@see \Siel\Acumulus\Collectors\Collector::collectLogicFields()} phase, where the
 *   code needs access to the shop objects.
 *
 * In both cases a PropertySources object is used to provide access to a shop-specific
 * set of objects on top of a set of Wrapper/Adapter objects from the
 * {@see \Siel\Acumulus\Invoice) namespace like e.g. {@see \Siel\Acumulus\Invoice\Source}
 * or {@see \Siel\Acumulus\Invoice\Item}.
 */
class PropertySources
{
    private array $propertySources = [];

    /**
     * Returns the property source with the given name, or null if it is not set.
     *
     * @param string $name
     *   The name of the source
     *
     * @return mixed
     *   The property source with the given name. This is typically an object or keyed
     *   array, but it may also be a scalar; null when no source with the given name
     *   exists.
     */
    public function get(string $name): mixed
    {
        return $this->propertySources[$name] ?? null;
    }

    /**
     * Clears the list of property sources.
     *
     * @return $this
     */
    public function clear(): static
    {
        $this->propertySources = [];
        return $this;
    }

    /**
     * Adds an object as a property source.
     *
     * The object is added to the start of the array. Thus, upon token expansion where a
     * token is used that refers directly to a property, instead of referring to its
     * parent first, it will be searched before other (already added) property sources.
     * If an object already exists under that name, the existing one will be
     * overwritten.
     *
     * NOTE: This is a deprecated way of token specification: do not rely on it: specify
     *   the full path!
     *
     * @param string $name
     *   The name to use for the source
     * @param mixed $propertySource
     *   The source to add, typically an object or an array, but may be a scalar.
     *
     * @return $this
     */
    public function add(string $name, mixed $propertySource): static
    {
        // Add in front.
        $this->propertySources = [$name => $propertySource] + $this->propertySources;
        return $this;
    }

    /**
     * Removes an object as a property source.
     *
     * @param string $name
     *   The name of the source to remove.
     *
     * @return $this
     */
    public function remove(string $name): static
    {
        unset($this->propertySources[$name]);
        return $this;
    }

    /**
     * Returns the set of property sources as a keyed array.
     */
    public function toArray(): array
    {
        return $this->propertySources;
    }
}
