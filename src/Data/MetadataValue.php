<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use DateTimeInterface;
use Siel\Acumulus\Api;
use Siel\Acumulus\Meta;

use Stringable;

use function count;
use function is_callable;
use function is_object;

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
    private bool $isList;
    private array $value = [];

    public function __construct(bool $isList = false)
    {
        $this->isList = $isList;
    }

    public function count(): int
    {
        return count($this->value);
    }

    /**
     * Returns the value of this metadata field.
     *
     * @return array|mixed|null
     *   If $$this->isList is:
     *     - true: a, possibly empty, array with all values for this field.
     *     - false:
     *       - Null if no value is set.
     *       - The value, probably a scalar, if 1 value was added.
     *       - An array with all values if multiple values were added.
     *
     * @todo: add a parameter ?int $index = null ? in that case also add it to
     *   {@see \Siel\Acumulus\Data\MetadataCollection::get()}.
     * param int|null $index
     *
     */
    public function get(): mixed
    {
        return $this->isList
            ? $this->value
            : match ($this->count()) {
                0 => null,
                1 => $this->value[0],
                default => $this->value,
            };
    }

    /**
     * Adds a value to the metadata property.
     *
     * @param mixed $value
     *   The value to add to this property.
     *
     * @todo: add conversion of numeric strings like in FieldExpander. What if an array
     *   gets passed in: convert recursively?
     */
    public function add(mixed $value): static
    {
        $this->value[] = $value;
        return $this;
    }

    /**
     * Converts the metadata value to a representation that fits in an API message.
     *
     * Scalars are not converted, a DatetimeInterface is formatted in the ISO format,
     * leaving out the time part if it is 0, and complex types are json-encoded. However,
     * to get a "prettier print" in the final message double quotes are replaced by single
     * quotes to prevent that these quotes would get escaped when the whole message gets
     * json-encoded.
     */
    public function getApiValue(): int|float|string|bool|null
    {
        $value = $this->get();
        if (is_scalar($value) || $value === null) {
            $result = $value;
        } elseif ($value instanceof DateTimeInterface) {
            if ($value->format('H:i:s') === '00:00:00') {
                $result = $value->format(Api::DateFormat_Iso);
            } else {
                $result = $value->format(Api::Format_TimeStamp);
            }
        } else {
            $result = $value instanceof Stringable
                ? (string) $value
                : json_encode($value, Meta::JsonFlags);
            $result = str_replace('"', "'", $result);
        }
        return $result;
    }
}
