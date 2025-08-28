<?php
/**
 * @noinspection DuplicatedCode
 */

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Helpers;

use ArrayAccess;
use Siel\Acumulus\Helpers\FieldExpander as BaseFieldExpander;
use WC_Data;
use WC_Meta_Data;

use function count;
use function is_array;
use function is_string;

/**
 * WC override of FieldExpander.
 */
class FieldExpander extends BaseFieldExpander
{
    /**
     * This Woocommerce override adds a WC specific way of retrieving properties, namely
     * via the {@see WC_Data::get_data()} method. This should be done first, as accessing
     * properties directly via __get() results in a:
     * wc_doing_it_wrong($key, 'Order properties should not be accessed directly.', '3.0')
     */
    protected function getValueFromProperty(object $object, string $property): mixed
    {
        if ($object instanceof WC_Data) {
            /** @noinspection PhpUndefinedMethodInspection false positive */
            $array = $object->get_data();
            $value = $this->getValueFromArray($array, $property) ?? $this->getValueFromMetadata($array['meta_data'], $property) ?? null;
        }
        return $value ?? parent::getValueFromProperty($object, $property);
    }

    /**
     * {inheritdoc}
     *
     * This override can also be called by the {@see getValueFromProperty()} override, in
     * which case $array is the result of a call to {@see WC_Data::get_data()} and $index
     * is the property name to get. The data array returned from the
     * {@see WC_Data::get_data()} method may contain:
     * - Recursive data sets, e.g. 'billing' for the billing address
     * - A separate meta_data set, but that is handled by {@see getValueFromMetadata()}.
     *
     * This method:
     * - Looks for $index in the data array.
     * - Recursively searches for property by splitting it into separate pieces delimited
     *   by underscores. E.g. 'billing_email' may be found in $array['billing']['email'].
     */
    protected function getValueFromArray(array|ArrayAccess $array, mixed $index): mixed
    {
        $value = parent::getValueFromArray($array, $index);
        if ($value === null && is_string($index)) {
            // Not found: check if we can search recursively.
            $propertyParts = explode('_', $index, 2);
            if (count($propertyParts) === 2 && isset($array[$propertyParts[0]]) && is_array($array[$propertyParts[0]])) {
                $value = $this->getValueFromArray($array[$propertyParts[0]], $propertyParts[1]);
            }
        }
        return $value;
    }

    /**
     * Extracts a value from a set of WooCommerce {@see WC_Meta_Data} objects.
     *
     * WooCommerce's metadata is stored in objects having twice the set of
     * properties 'id', 'key', and 'value', once in the property 'current_value'
     * and once in the property 'data'. If 1 of the properties 'id', 'key' or
     * 'value' is retrieved, its value from the 'current_value' set is returned.
     *
     * @param WC_Meta_Data[] $metaData
     *   The metadata set to search in.
     * @param string $key
     *   The name of the property to search for. This may be with or without a
     *   leading underscore.
     *
     * @return mixed
     *   The value for the metadata of the given name, or null or the empty
     *   string if not available (or the metadata really equals null or the
     *   empty string). The return value may be a scalar (numeric type) that can
     *   be converted to a string.
     *
     * @noinspection PhpUndefinedFieldInspection  Class WC_Meta_Data implements __get().
     */
    protected function getValueFromMetadata(array $metaData, string $key): mixed
    {
        $key = ltrim($key, '_');
        $value = null;
        foreach ($metaData as $metaItem) {
            $itemKey = ltrim($metaItem->key, '_');
            if ($key === $itemKey) {
                $value = $metaItem->value;
                break;
            }
        }
        return $value;
    }
}
