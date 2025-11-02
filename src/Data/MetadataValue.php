<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use DateTimeInterface;
use JsonSerializable;
use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Meta;

use Stringable;

use function array_key_exists;
use function count;
use function is_array;
use function is_object;
use function is_scalar;
use function is_string;

/**
 * MetadataValue represents a metadata value.
 *
 * The Acumulus API will ignore any additional values that are sent as part of the message
 * structure. Additional values are fields that Acumulus does not know.
 * We use this to add additional information - metadata - to these structures for the
 * following reasons:
 * - Processing: {@see \Siel\Acumulus\Collectors\Collector Collectors} collect all
 *   information from the webshop that is needed to create a complete and correct Acumulus
 *   API message. This may contain information that is not directly mappable to an
 *   Acumulus property, as, e.g, it may require more complex algorithms that may include
 *   multiple values and settings. To keep Collectors as simple as possible, a Collector
 *   just adds the raw data without processing it, which is left to the webshop
 *   independent code in the completor phase. This raw data is added as metadata.
 * - Logging and debugging: if users file a support request, it is extremely useful to be
 *   able to know what happened, e.g. which processing decisions were taken to arrive at
 *   certain Acumulus values. So, besides the processing metadata from above, we also add
 *   metadata that gives us more context and tells us e.g. which strategy or algorithm was
 *   actually used to arrive at a given Acumulus value.
 *
 * Metadata values:
 * - Are typically scalar values, but null, {@see DateTimeInterface dates and times},
 *   (small) objects, and keyed arrays are accepted as well.
 * - Can hold multiple values. Therefore, setting or adding a
 *   {@see \array_is_list() numerically indexed array} will be seen as adding all values
 *   of that array separately.
 * - Will typically be rendered in JSON notation by using {@see json_encode()}. However,
 *   dates and times will be converted to ISO8601 notation and {@see Stringable} objects
 *   will be cast to strings.
 * - Note that objects that contain circular references will lead to failure.
 */
class MetadataValue
{
    protected const ScalarConstants = ['true' => true, 'false' => false, 'null' => null];
    /**
     * @var bool
     *   Indicates whether the value is to be seen as a list. In fact, each MetaDataValue
     *   is a list, possibly having only 1 value. This property only influences what is
     *   returned with {@see get()} when we have only 0 or 1 values.
     */
    private bool $isList;
    private array $value = [];

    /**
     * Completes (deep) cloning a {@see MetadataValue}.
     *
     * The clone will contain a copy of the array, but all entries referring to an object
     * will refer to the same object as in the original array, so we need to clone these
     * objects.
     */
    public function __clone(): void
    {
        foreach ($this->value as &$singleMetadataValue) {
            if (is_object($singleMetadataValue)) {
                $singleMetadataValue = clone $singleMetadataValue;
            }
        }
    }

    public function __construct(bool $isList = false)
    {
        $this->isList = $isList;
    }

    /**
     * Indicates whether this metadata value is to be seen as a list. This influences what
     * is returned with {@see get()} when we have 0 or 1 values.
     */
    public function isList(): bool
    {
        return $this->isList;
    }

    public function count(): int
    {
        return count($this->value);
    }

    /**
     * Adds a value to the metadata property.
     *
     * @param mixed $value
     *   The value to add to this property. If this is a
     *   {@see array_is_list() numerically indexed array}:
     *   - The property $isList is set to true (to correctly handle adding an empty array)
     *   - And each value is added separately
     *
     * @return $this
     */
    public function add(mixed $value): static
    {
        if (is_array($value) && array_is_list($value)) {
            $this->isList = $this->isList || count($value) !== 1;
            foreach ($value as $singleValue) {
                $this->value[] = $this->simplifyValue($singleValue, 1);
            }
        } else {
            $this->value[] = $this->simplifyValue($value, 1);
        }
        return $this;
    }

    /**
     * Recursively 'simplifies' values before adding them.
     *
     * Simplifying is mainly converting numeric strings or scalar constants as string to
     * their scalar value.
     */
    private function simplifyValue(mixed $value, int $recursionLevel): mixed
    {
        if (is_string($value)) {
            // Cast strings that are a scalar constant or are numeric.
            if (array_key_exists($value, self::ScalarConstants)) {
                $result = self::ScalarConstants[$value];
            } else {
                $result = Number::castNumericValue($value);
            }
        } elseif (is_array($value)) {
            // Recursively simplify values, but prevent endless loops.
            if ($recursionLevel > 4) {
                $result = get_debug_type($value);
            } else {
                $result = [];
                foreach ($value as $key => $singleValue) {
                    $result[$key] = $this->simplifyValue($singleValue, $recursionLevel + 1);
                }
            }
        } else {
            // $value is null, a scalar (but not a string), or an object: add as is.
            // (resources are not expected may lead to failure)
            $result = $value;
        }
        return $result;
    }

    /**
     * Returns the value of this metadata field.
     *
     * @param int|null $index
     *   The index of the value to return. If the index does not exist, null is returned.
     *   If index is null, all values are returned
     *
     * @return array|mixed|null
     *   If $this->isList is:
     *     - true: an array, possibly empty, with all values for this field.
     *     - false:
     *       - Null if no value is set.
     *       - The value, probably a scalar, if 1 value was added.
     *       - An array with all values if multiple values were added.
     */
    public function get(?int $index = null): mixed
    {
        if ($index === null) {
            return $this->isList || $this->count() > 1
                ? $this->value
                : match ($this->count()) {
                    0 => '',
                    1 => $this->value[0],
                };
        } else {
            return $this->value[$index] ?? null;
        }
    }

    /**
     * Converts the metadata value to a representation that fits in an API message.
     *
     * Scalars are not converted, a DatetimeInterface is formatted in the ISO format,
     * leaving out the time part if it is 0, and complex types are json-encoded. However,
     * to get a "prettier print" in the final message, double quotes are replaced by
     * single quotes to prevent that these quotes would get escaped when the whole message
     * gets json-encoded.
     */
    public function getApiValue(): int|float|string|bool|null
    {
        return $this->toScalar($this->get(), 0);
    }

    /**
     * Converts a (metadata) value to a scalar that fits in an API message (XML or JSON).
     *
     * How are values converted to a scalar?:
     * - Scalars are not converted.
     * - A DatetimeInterface is formatted in ISO 8601 format, leaving out the time part if
     *   it is 0.
     * - Stringable objects are cast to a string.
     * - Other objects are converted to an array using {@see get_object_vars()}.
     * - Individual values from arrays are first recursively converted to a "scalar".
     *   However, above 3 levels deep, we just return the type of and object or array.
     * - Arrays are json_encoded. However, to get a "prettier print" in the final message,
     *   double quotes are replaced by single quotes to prevent that these quotes would
     *   get escaped when the whole message gets json-encoded.
     */
    protected function toScalar(mixed $value, int $level): int|float|bool|null|string|array
    {
        if (is_scalar($value) || $value === null || $value instanceof JsonSerializable) {
            $result = $value;
        } elseif ($value instanceof DateTimeInterface) {
            if ($value->format('H:i:s') === '00:00:00') {
                $result = $value->format(Api::DateFormat_Iso);
            } else {
                $result = $value->format(Api::Format_TimeStamp);
            }
        } elseif ($value instanceof Stringable) {
            $result = (string) $value;
        } elseif ($level > 3) {
            $result = get_debug_type($value);
        } else {
            if (is_object($value)) {
                $value = get_object_vars($value);
            }
            $result = array_map(fn(mixed $singleValue) => $this->toScalar($singleValue, $level + 1), $value);
            if ($level === 0) {
                $result = json_encode($result, Meta::JsonFlags);
                $result = str_replace('"', "'", $result);
            }
        }
        return $result;
    }
}
