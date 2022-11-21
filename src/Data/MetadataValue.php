<?php

namespace Siel\Acumulus\Data;

/**
 * MetadataValue represents a metadata value.
 *
 * The Acumulus API will ignore any additional properties that are sent as part
 * of the message structure that it does not know. We use this to add additional
 * information, metadata, to these structures for the following reasons:
 * - Processing: {@see InvoiceCollector}s collect all information from the webshop that
 *   is needed to create a complete and correct Acumulus API message. This may
 *   contain information that is not directly mappable to Acumulus, as it
 *   requires more complex algorithms that may include multiple values and
 *   settings. To keep Collectors as simple as possible, a Collector just adds
 *   the raw data without processing it which is left to the webshop independent
 *   code in the completor phase.
 * - Logging and debugging: if users file a support request, it is extremely
 *   useful to be able to know what happened, e.g. which processing decisions
 *   were taken to arrive at certain Acumulus values. So, besides the processing
 *   metadata from above, we also add metadata that tells us e.g. which strategy
 *   or algorithm was actually used to arrive at a given Acumulus value.
 *
 * Metadata values are typically scalar values, but (small) objects or keyed
 * arrays, and sets of similar values are accepted as well. Non-scalar values
 * will be rendered in json notation.
 */
class MetadataValue
{
    protected array $value = [];
    protected int $count = 0;

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
     * Note: this will often be scalar value, but this may also be a keyed array
     * as value, or an array of values. To distinguish between the latter 2, use
     * {@see MetadataValue::getCount()}.
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
        $value = $this->get(0);
        return is_string($value) ? $value : json_encode($value);
    }
}
