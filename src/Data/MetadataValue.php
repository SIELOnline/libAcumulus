<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use Siel\Acumulus\Meta;

use function is_string;

/**
 * MetadataValue represents a metadata value.
 *
 * The Acumulus API will ignore any additional properties that are sent as part
 * of the message structure that it does not know. We use this to add additional
 * information, metadata, to these structures for the following reasons:
 * - Processing: {@see \Siel\Acumulus\Collectors\Collector Collectors} collect
 *   all information from the webshop that is needed to create a complete and
 *   correct Acumulus API message. This may contain information that is not
 *   directly mappable to an Acumulus property, as, e.g, it may require more
 *   complex algorithms that may include multiple values and settings. To keep
 *   Collectors as simple as possible, a Collector just adds the raw data
 *   without processing it, which is left to the webshop independent code in the
 *   completor phase. This raw data is added as metadata.
 * - Logging and debugging: if users file a support request, it is extremely
 *   useful to be able to know what happened, e.g. which processing decisions
 *   were taken to arrive at certain Acumulus values. So, besides the processing
 *   metadata from above, we also add metadata that gives us more context and
 *   tells us e.g. which strategy or algorithm was actually used to arrive at a
 *   given Acumulus value.
 *
 * Metadata values are typically scalar values, but null, (small) objects, keyed
 * arrays, and numeric arrays of similar values are accepted as well. Non string
 * values will be rendered in json notation.
 */
class MetadataValue
{
    private array $value = [];
    private int $count = 0;

    /**
     * @param mixed ...$values
     */
    public function __construct(... $values)
    {
        foreach ($values as $value) {
            $this->add($value);
        }
    }

    public function count(): int
    {
        return $this->count;
    }

    /**
     * Returns the value of this property.
     *
     * Note: this will often be a scalar value, but this may also be a (keyed)
     * array or a stdClass/object with all public properties.
     *
     * @return mixed|null
     */
    public function get()
    {
        switch ($this->count()) {
            case 0:
                return null;
            case 1:
                return $this->value[0];
            default:
                return $this->value;
        }
    }

    /**
     * Adds a value to the metadata property.
     *
     * @param mixed $value
     *   The value to add to this property.
     */
    public function add($value): void
    {
        $this->value[] = $value;
        $this->count++;
    }

    public function __toString(): string
    {
        $value = $this->get();
        return is_string($value) ? $value : json_encode($value, Meta::JsonFlags);
    }
}
