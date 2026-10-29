<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use ArrayObject;
use Siel\Acumulus\Config\Mappings;

/**
 * PropertySources is a set of objects that provides properties to the collector phase.
 *
 * The Collector phase is all about extracting values from shop objects into
 * {@see \Siel\Acumulus\Data\AcumulusObject}s, be it in the:
 * - {@see \Siel\Acumulus\Collectors\Collector::collectMappedFields()} phase, where
 *   {@see Mappings} are passed to a {@see \Siel\Acumulus\Helpers\FieldExpander}, or the
 * - {@see \Siel\Acumulus\Collectors\Collector::collectLogicFields()} phase, where code
 *   can directly access the shop objects.
 *
 * In both cases a PropertySources object is used to provide access to a shop-specific
 * set of objects on top of a set of Wrapper/Adapter objects from the
 * {@see \Siel\Acumulus\Invoice) namespace like e.g. {@see \Siel\Acumulus\Invoice\Source}
 * or {@see \Siel\Acumulus\Invoice\Item}.
 */
class PropertySources extends ArrayObject
{
    public function __construct(array $array = [])
    {
        parent::__construct(array: $array, flags: ArrayObject::ARRAY_AS_PROPS);
    }

    /**
     * Adds a collection of objects as property sources.
     *
     * The objects are (in reverse order) added to the beginning of the array. Thus, upon
     * token expansion where a token is used that refers directly to a property instead of
     * referring to its parent(s) first, the latest added objects will be searched first,
     * thus before other already added property sources.
     *
     * If an object already exists under that name, the existing one will be
     * overwritten.
     *
     * @param array $objects
     *   The objects to be added, keyed by a string that can be used to refer to the
     *   object by the field expander mapping specifications or retrieve the object in
     *   code.
     */
    public function add(array $objects): void
    {
        $this->exchangeArray(array_reverse($objects) + $this->getArrayCopy());
    }

    /**
     * Removes objects from the set of property sources.
     *
     * @param string[] $names
     *   The names of the objects to remove.
     */
    public function remove(string ...$names): void
    {
        foreach ($names as $name) {
            unset($this[$name]);
        }
    }
}
