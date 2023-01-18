<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Helpers;

use Siel\Acumulus\Helpers\Token as BaseToken;
use WC_Data;

use function array_key_exists;
use function call_user_func_array;
use function count;
use function is_array;

/**
 * WC3 override of Token.
 */
class Token extends BaseToken
{
    /**
     * {@inheritdoc}
     */
    protected function getObjectProperty(object $variable, string $property, array $args)
    {
        if ($variable instanceof WC_Data) {
            $method1 = $property;
            $method3 = 'get_' . $property;
            if (method_exists($variable, $method1)) {
                $value = call_user_func_array([$variable, $method1], $args);
            } elseif (method_exists($variable, $method3)) {
                $value = call_user_func_array([$variable, $method3], $args);
            } else {
                $value = $this->getDataValue($variable->get_data(), $property);
            }
        } else {
            $value = parent::getObjectProperty($variable, $property, $args);
        }
        return $value;
    }

    /**
     * Extracts a value from a WooCommerce data object data array.
     * A WooCommerce data array (array with key value pairs) returned from the
     * WC_Data::get_data() method may contain recursive data sets, e.g.
     * 'billing' for the billing address, and a separate meta_data set.
     * This method recursively searches for the property by stripping it into
     * separate pieces delimited by underscores. E.g. billing_email may be found
     * in $data['billing']['email'].
     *
     * @param array $data
     *   The key value data set to search in.
     * @param string $property
     *   The name of the property to search for.
     *
     * @return mixed
     *   The value for the property of the given name, or null or the empty
     *   string if not available (or the property really equals null or the
     *   empty string). The return value may be a scalar (numeric type) that can
     *   be converted to a string.
     *
     * @noinspection OffsetOperationsInspection
     *   explode can return false but won't.
     */
    protected function getDataValue(array $data, string $property)
    {
        $value = null;
        if (array_key_exists($property, $data)) {
            // Found: return the value.
            $value = $data[$property];
        } else {
            // Not found: check in metadata or descend recursively.
            if (isset($data['meta_data'])) {
                $value = $this->getMetaDataValue($data['meta_data'], $property);
            }
            if ($value === null) {
                // Not found in meta_data: check if we should descend a level.
                $propertyParts = explode('_', $property, 2);
                if (count($propertyParts) === 2 && array_key_exists($propertyParts[0], $data) && is_array($data[$propertyParts[0]])) {
                    $value = $this->getDataValue($data[$propertyParts[0]], $propertyParts[1]);
                }
            }
        }
        return $value;
    }

    /**
     * Extracts a value from a set of WooCommerce metadata objects.
     * WooCommerce's metadata is stored in objects having twice the set of
     * properties 'id', 'key', and 'value', once in the property 'current_value'
     * and once in the property 'data'. If 1 of the properties 'id', 'key' or
     * 'value' is retrieved, its value from the 'current_value' set is returned.
     *
     * @param object[] $metaData
     *   The metadata set to search in.
     * @param string $property
     *   The name of the property to search for. May be with or without a
     *   leading underscore.
     *
     * @return null|string
     *   The value for the property of the given name, or null or the empty
     *   string if not available (or the property really equals null or the
     *   empty string). The return value may be a scalar (numeric type) that can
     *   be converted to a string.
     */
    protected function getMetaDataValue(array $metaData, string $property): ?string
    {
        $property = ltrim($property, '_');
        $value = null;
        foreach ($metaData as $metaItem) {
            $key = ltrim($metaItem->key, '_');
            if ($property === $key) {
                $value = $metaItem->value;
                break;
            }
        }
        return $value;
    }
}
