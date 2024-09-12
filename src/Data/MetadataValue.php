<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use DateTime;
use Siel\Acumulus\Api;
use Siel\Acumulus\Meta;

use function count;

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
        return count($this->value);
    }

    /**
     * Returns the value of this property.
     *
     * Note: this will often be a scalar value, but this may also be a more
     * complex type, though to be useful, it should be "stringable" or "json
     * serializable" in that case.
     *
     * param int|null $index
     *   @todo: behavior for all these cases. Add a constructor parameter $isMulti?
     *
     * @return array|mixed|null
     *   If
     *   - $index = null: all values for thisMetadataValue, or  null if it has
     *     no values (note: thus not an empty array, as null is more logical
     *     when
     *
     * @todo: add a parameter ?int $index = null ? in that case also add it to
     *   {@see \Siel\Acumulus\Data\MetadataCollection::get()}
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
    }

    /**
     * Converts the metadata value to a representation that fits in an API message.
     *
     * Scalars are not converted, a Datetime is formatted in the ISO format. leaving out
     * the time part if it is 0, and complex types are json-encoded. However, to get a
     * "prettier print" in the final message double quotes are replaced by single quotes
     * to prevent that these quotes would get escaped when the whole message gets
     * json-encoded.
     */
    public function getApiValue()
    {
        $value = $this->get();
        if (is_scalar($value) || $value === null) {
            $result = $value;
        } elseif ($value instanceof DateTime) {
            if ($value->format('H:i:s') === '00:00:00') {
                $result = $value->format(Api::DateFormat_Iso);
            } else {
                $result = $value->format('Y-m-d H:i:s');
            }
        } else {
            $result = json_encode($value, Meta::JsonFlags);
            $result = str_replace('"', "'", $result);
        }
        return $result;
    }
}
