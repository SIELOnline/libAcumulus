<?php

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

use Exception;

use function array_key_exists;
use function call_user_func_array;
use function count;
use function is_array;
use function is_bool;
use function is_callable;
use function is_object;
use function is_string;
use function strlen;

/**
 * Contains functionality to expand a string that may contain variable fields.
 *
 * - A variable field is a string that refers to a "property" of an "object".
 * - "Objects" are typically the shop order, an order line, the customer, an
 *   address, etc. Depending on the webshop these "objects" may actually be
 *   (keyed) arrays.
 * - "Properties" are the values of an "object", all elements that have or can
 *   return a value can be used: properties on real objects, key names on
 *   arrays, or (getter) methods on real objects. Even methods with parameters
 *   can be used.
 * - Single properties are returned in the type they have, but as soon as
 *   properties get concatenated they are converted to a string:
 *     - bool: to the string 'true' or 'false'.
 *     - null: empty string
 *     - number: string representation of the number. (@todo: precision?)
 *     - array: imploded with glue=' '.
 *     - object: if the _toString() exists it will be called, otherwise
 *       {@see json_encode()} will be used.
 *
 * A variable field is recognised by enclosing the property specification within
 * square brackets, i.e. '[' and ']'.
 *
 * A variable field in its simplest form is just a property name, but to
 * cater for some special cases it can be made more complex. See the syntax
 * definition below:
 * - variable-field = '[' property-specification ']'
 * - property-specification = property-alternative('|'property-alternative)*
 * - property-alternative = space-concatenated-property('+'space-concatenated-property)*
 * - space-concatenated-property = single-property('&'single-property)*
 * - single-property = property-in-object|property-name|literal-text
 * - property-within-object = (object-name::)+property-name
 * - object-name = text
 * - property-name = text
 * - literal-text = "text"
 *
 * Notes:
 * - This syntax is quite simple: it does not accept the special symbols ], |, &
 *   and ":
 *     - Not as part of object or property names. This is not restricting as
 *       this is not normal for PHP object or property names, or array keys.
 *     - Not as part of literal strings. This is not considered restricting, as
 *       they will hardly be used given where this class will be used. Moreover,
 *       in most cases these characters can be placed outside variable field
 *       definitions with (mostly) the same results.
 * - Alternatives are expanded left to right until a property alternative is
 *   found that is not empty.
 *
 *
 * Example 1:
 * <pre>
 *   $propertySpec = sku|ean|isbn; sku = ''; ean = 'Hello'; isbn = 'World';
 *   Result: 'Hello'
 * </pre>
 *
 * Properties that are joined with a + in between them, are all expanded, where
 * the + gets replaced with a space if and only if the property directly
 * following it, is not empty.
 *
 * Properties that are joined with a & in between them, are all expanded and
 * concatenated directly, thus not with a space between them like with a +.
 *
 * Literal text that is joined with "real" properties using & or + only gets
 * returned when at least 1 of the "real" properties have a non-empty value.
 * (Otherwise, you can just place it outside the variable-field definition.)
 *
 * Example 2:
 * <pre>
 *   first = 'John'; middle = ''; last = 'Doe';
 *   $propertySpec1 = [first] [middle] [last];
 *   $propertySpec2 = [first&middle&last];
 *   $propertySpec3 = [first+middle+last];
 *   $propertySpec4 = For [middle];
 *   $propertySpec5 = ["For"+middle];
 *   $propertySpec6 = ["For"+first+middle+last];
 *   Result1: 'John  Doe'
 *   Result2: 'JohnDoe'
 *   Result3: 'John Doe'
 *   Result4: 'For '
 *   Result5: ''
 *   Result6: 'For john Doe'
 * </pre>
 *
 * A full property name may contain the "object" name followed by :: to
 * distinguish it from the "property" name itself, to allow specifying which
 * object the property should be taken from. This is useful when multiple
 * "objects" have some equally named "properties".
 *
 * Example 3:
 * <pre>
 *   objects = [
 *     'order => Order(id = 3, date_created = 2016-02-03, ...),
 *     'customer' => Customer(id = 5, date_created = 2016-01-01, name = 'Doe', ...),
 *    ];
 *   $pattern1 = '[id] [date_created] [name]'
 *   $pattern2 = '[customer::id] [customer::date_created] [name]'
 *   Result1: '3 2016-01-01 Doe'
 *   Result2: '5 2016-01-01 Doe'
 * </pre>
 *
 * A property name should:
 * - Be the name of a (public) property,
 * - Have a (public) getter in the form of getProperty() or get_property(),
 * - Or be handled by the magic method __get() (in the form property),
 * - Or be handled by the magic method __call(), in 1 of the 3 forms allowed:
 *   property(), getProperty(), or get_property().
 *
 * A property name may also be:
 * - Any method name that does not have required parameters
 * - Or a method that accepts scalar parameters Optionally followed by arguments between brackets, string arguments should
 *   not be quoted.
 *
 * A variable is:
 * - An array.
 * - An object.
 * - A {@see is_callable() callable}, in which case the callable is called with
 *   the property name passed as argument. No known usages anymore.
 */
class Field
{
    protected const TypeLiteral = 1;
    protected const TypeProperty = 2;

    /**
     * @var array
     *   A keyed array of "objects". The key indicates the name of the "object",
     *   typically the class name (with a lower cased 1st character) or the
     *   variable name typically used in the shop software. The "objects" are
     *   structures that contain information related to an order or associated
     *   objects like customer, shipping address, order line, credit note, ...,
     *   "Objects" can be objects or arrays.
     *   Internally, we see this list of "objects" as a super "object"
     *   containing all "objects" as (named) properties. in this sense it
     *   facilitates the recursive search algorithm when searching for a
     *   variable field object1::object2::property.
     */
    protected array $objects;
    protected Log $log;

    public function __construct(Log $log)
    {
        $this->log = $log;
    }

    /**
     * Expands a string that can contain variable fields.
     *
     * Variable fields are found using a regular expression. Each variable field
     * definition is expanded by searching the given "objects" for the
     * referenced property or properties.
     *
     * @param string $fieldDefinition
     *   The field definition to expand.
     * @param array $objects
     *   The "objects" to search for the properties that are referenced in the
     *   variable field parts. The key indicates the name of the "object",
     *   typically the class name (with a lower cased 1st character) or the
     *   variable name typically used in the shop software.
     *
     * @return mixed
     *   The pattern with variable field definitions expanded with their actual
     *   value, which may be empty, if the properties referred to do not exist
     *   or are empty themselves.
     */
    public function expand(string $fieldDefinition, array $objects)
    {
        $this->objects = $objects;
        // If the definition is exactly 1 variable field definition we return
        // the direct result of {@see expandVariableField()} so that the type
        // may be retained.
        if (strncmp($fieldDefinition, '[', 1) === 0
            && strpos($fieldDefinition, ']') === strlen($fieldDefinition) - 1
        ) {
            return $this->expandVariableField(substr($fieldDefinition, 1, -1));
        } else {
            return preg_replace_callback('/\[([^]]+)]/', [$this, 'variableFieldMatch'], $fieldDefinition);
        }
    }

    /**
     * Expands a single variable field definition.
     *
     * This is the callback for preg_replace_callback() in {@see expand()}.
     * This callback expands the variable field definition found in $matches[1].
     *
     * @param array $matches
     *   Array containing match information, $matches[0] contains the match
     *   including the [ and ]., $matches[1] contains the part between the [
     *   and ].
     *
     * @return string
     *   The expanded value for this token. The return value may be a scalar
     *   (numeric type) that can be converted to a string.
     */
    protected function variableFieldMatch(array $matches): string
    {
        $expandedValue = $this->expandVariableField($matches[1]);
        if(!is_string($expandedValue)) {
            $expandedValue = $this->valueToString($expandedValue);
        }
        return $expandedValue;
    }

    /**
     * Expands a variable field definition.
     *
     * - variable-field = '[' property-specification ']'
     * - property-specification = property-alternative('|'property-alternative)*
     *
     * The first alternative resulting in a non-empty value is returned.
     *
     * @param string $variableField
     *   The variable field to expand (without [ and ]).
     *
     * @return mixed
     *   The expanded value of the variable field. This may result in null or
     *   the empty string if the referenced property(ies) are (all) empty.
     */
    protected function expandVariableField(string $variableField)
    {
        $value = null;
        $propertyAlternatives = explode('|', $variableField);
        foreach ($propertyAlternatives as $propertyAlternative) {
            $value = $this->expandAlternative($propertyAlternative);
            // Stop as soon as an alternative resulted in a non-empty value.
            if ($value !== null && $value !== '') {
                break;
            }
        }

        if ($value === null || $value === '') {
            $this->log->debug("Field::expandVariableField('%s'): not found", $variableField);
        }

        return $value;
    }

    /**
     * Expands a Property alternative.
     *
     * - property-alternative = space-concatenated-property('+'space-concatenated-property)*
     *
     * @return mixed
     */
    protected function expandAlternative(string $propertyAlternative)
    {
        $spaceConcatenatedProperties = explode('+', $propertyAlternative);
        $spaceConcatenatedValues = [];
        foreach ($spaceConcatenatedProperties as $spaceConcatenatedProperty) {
            $spaceConcatenatedValues[] = $this->expandSpaceConcatenatedProperty($spaceConcatenatedProperty);
        }
        return $this->implodeValues(' ', $spaceConcatenatedValues)['value'];
    }

    /**
     * Expands a space concatenated property.
     *
     * - space-concatenated-property = single-property('&'single-property)*
     */
    protected function expandSpaceConcatenatedProperty(string $spaceConcatenatedProperty): array
    {
        $singleProperties = explode('&', $spaceConcatenatedProperty);
        $singlePropertyValues = [];
        foreach ($singleProperties as $singleProperty) {
            $singlePropertyValues[] = $this->expandSingleProperty($singleProperty);
        }
        return $this->implodeValues('', $singlePropertyValues);
    }

    /**
     * Expands a single property.
     *
     * - single-property = property-in-object|property-name|literal-text
     * - property-in-object = (object-name::)+property-name
     * - object-name = text
     * - property-name = text
     * - literal-text = "text"
     *
     * @return array
     *   A keyed array with 2 keys: 'value' and 'type'.
     */
    protected function expandSingleProperty(string $singleProperty): array
    {
        if ($this->isLiteral($singleProperty)) {
            $type = self::TypeLiteral;
            $value = $this->expandLiteral($singleProperty);
        } elseif (strpos($singleProperty, '::') !== false) {
            $type = self::TypeProperty;
            $value = $this->expandPropertyInObject($singleProperty);
        } else {
            $type = self::TypeProperty;
            $value = $this->expandProperty($singleProperty);
        }
        return compact('type', 'value');
    }

    protected function isLiteral(string $singleProperty): bool
    {
        return $singleProperty[0] === '"' && $singleProperty[strlen($singleProperty) - 1] === '"';
    }

    /**
     * Expands a literal string property.
     *
     * - literal-text = "text"
     *
     * @return string
     *   The text between the quotes
     */
    protected function expandLiteral(string $singleProperty): string
    {
        return substr($singleProperty, 1, -1);
    }

    /**
     * Expands a property-in-object.
     *
     * - property-in-object = (object-name::)+property-name
     * - object-name = text
     * - property-name = text
     *
     * @param string $propertyInObject
     *   The object names and property name to search for, e.g:
     *   object1::object2::property.
     *
     * @return mixed
     *   the value of the property, or the empty string or null if the property
     *   was not found (or equals null or the empty string).
     */
    protected function expandPropertyInObject(string $propertyInObject)
    {
        // Start searching in the "super object".
        $property = $this->objects;
        $propertyParts = explode('::', $propertyInObject);
        while (count($propertyParts) > 0 && $property !== null) {
            $propertyName = array_shift($propertyParts);
            $property = $this->getProperty($propertyName, $property);
        }
        return $property;
    }

    /**
     * Expands a property.
     *
     * - single-property = property-in-object|property-name|literal-text
     * - object-name = text
     * - property-name = text
     *
     * @param string $propertyName
     *   The name of the property, optionally restricted to a(n) (multi-level)
     *   object, to search for.
     *
     * @return mixed
     *   the value of the property, or null if the property was not found.
     */
    protected function expandProperty(string $propertyName)
    {
        foreach ($this->objects as $object) {
            $property = $this->getProperty($propertyName, $object);
            if ($property !== null && $property !== '') {
                break;
            }
        }
        return $property ?? null;
    }

    /**
     * Looks up a property in an "object".
     *
     * This default implementation looks for the property in the following ways:
     * If the passed variable is callable:
     * - returns the return value of the callable function or method.
     * If the passed variable is an array:
     * - looking up the property as key.
     * If the passed variable is an object:
     * - Looking up the property by name (as existing property or via __get).
     * - Calling the get{Property} getter.
     * - Calling the get_{property} getter.
     * - Calling the {property}() method (as existing method or via __call).
     *
     * Override if the property name or getter method is constructed differently.
     *
     * @param string $property
     *   The name of the property to extract from the "object".
     * @param object|array $object
     *   The "object" to extract the property from.
     *
     * @return mixed
     *   The value for the property of the given name, or null or the empty
     *   string if not available.
     */
    protected function getProperty(string $property, $object)
    {
        $value = null;

        $args = [];
        if (preg_match('/^(.+)\((.*)\)$/', $property, $matches)) {
            $property = $matches[1];
            $args = explode(',', $matches[2]);
        }
        if (is_array($object)) {
            if (is_callable($object)) {
                array_unshift($args, $property);
                $value = call_user_func_array($object, $args);
            } elseif (isset($object[$property])) {
                $value = $object[$property];
            }
        } elseif (is_object($object)) {
            // It's an object: try to get the property.
            // Safest and fastest way is via the get_object_vars() function.
            $properties = get_object_vars($object);
            if (array_key_exists($property, $properties)) {
                $value = $properties[$property];
            }
            // WooCommerce can have the property customer_id set to null, while
            // the data store does contain a non-null value: so if value is
            // still null, even if it is in the get_object_vars() result, we
            // try to get it the more difficult way.
            if ($value === null) {
                // Try some other ways.
                $value = $this->getPropertyFromObject($object, $property, $args);
            }
        }

        return $value;
    }

    /**
     * Looks up a property in a web shop specific object.
     * This part is extracted into a separate method, so it can be overridden
     * with web shop specific ways to access properties. The base implementation
     * will probably get the property anyway, so override mainly to prevent
     * notices or warnings.
     *
     * @param object $variable
     *   The variable to search for the property.
     * @param string $property
     *   The property or function to get its value.
     * @param array $args
     *   Optional arguments to pass if it is a function.
     *
     * @return mixed
     *   The value for the property of the given name, or null or the empty
     *   string if not available (or the property really equals null or the
     *   empty string). The return value may be a scalar (numeric type) that can
     *   be converted to a string.
     *
     * @noinspection PhpUsageOfSilenceOperatorInspection
     */
    protected function getPropertyFromObject(object $variable, string $property, array $args)
    {
        $value = null;
        $method1 = $property;
        $method2 = 'get' . ucfirst($property);
        $method3 = 'get_' . $property;
        if (method_exists($variable, $method1)) {
            $value = call_user_func_array([$variable, $method1], $args);
        } elseif (method_exists($variable, $method2)) {
            $value = call_user_func_array([$variable, $method2], $args);
        } elseif (method_exists($variable, $method3)) {
            $value = call_user_func_array([$variable, $method3], $args);
        } elseif (method_exists($variable, '__get')) {
            /** @noinspection PhpVariableVariableInspection */
            @$value = $variable->$property;
        } elseif (method_exists($variable, '__call')) {
            try {
                $value = @call_user_func_array([$variable, $property], $args);
            } catch (Exception $e) {
            }
            if ($value === null || $value === '') {
                try {
                    $value = call_user_func_array([$variable, $method1], $args);
                } catch (Exception $e) {
                }
            }
            if ($value === null || $value === '') {
                try {
                    $value = call_user_func_array([$variable, $method2], $args);
                } catch (Exception $e) {
                }
            }
            if ($value === null || $value === '') {
                try {
                    $value = call_user_func_array([$variable, $method3], $args);
                } catch (Exception $e) {
                }
            }
        }
        return $value;
    }

    /**
     * Concatenates a list of values using a glue between them.
     * Literal strings are only used if they are followed by a non-empty
     * property value. A literal string at the end is only used if the result so
     * far is not empty.
     *
     * @param array[] $values
     *   A list of type-value pairs.
     *
     * @return array
     *   Returns a type-value pair containing as value a string representation
     *   of all the values with the glue string between each value.
     */
    protected function implodeValues(string $glue, array $values): array
    {
        // Shortcut: if we have only 1 value, directly return it, so the type
        // may be retained.
        if (count($values) === 1) {
            return reset($values);
        }

        $result = '';
        $hasProperty = false;
        $previous = '';
        foreach ($values as $value) {
            $valueStr = $this->valueToString($value['value']);
            if ($value['type'] === self::TypeLiteral) {
                // Literal value: set aside and only add if next property value
                // is not empty.
                if (!empty($previous)) {
                    // Multiple literals after each other: treat as 1 literal
                    // but do glue them together.
                    $previous .= $glue;
                }
                $previous .= $valueStr;
            } else { // $value['type'] === self::TypeProperty
                // Property value: if it is not empty, add any previous literal
                // and the property value itself. If it is empty, discard any
                // previous literal value.
                if (!empty($valueStr)) {
                    if (!empty($previous)) {
                        if (!empty($result)) {
                            $result .= $glue;
                        }
                        $result .= $previous;
                    }
                    if (!empty($result)) {
                        $result .= $glue;
                    }
                    $result .= $valueStr;
                }
                // Discard any previous literal value, used or not.
                $previous = '';
                // Remember that this expression has at least 1 property
                $hasProperty = true;
            }
        }

        // Add a (set of) literal value(s) that came without property or if they
        // came as last value(s) and the result so far is not empty.
        if (!empty($previous) && (!$hasProperty || !empty($result))) {
            if (!empty($result)) {
                $result .= $glue;
            }
            $result .= $previous;
        }

        return [
            'type' => $hasProperty ? self::TypeProperty : self::TypeLiteral,
            'value' => $result,
        ];
    }

    /**
     * Converts a property to a string.
     *
     * Some properties may be arrays or objects: try to convert to a string
     * by "imploding" and/or calling __toString().
     *
     * Known usages: Magento2 street value is an array of street lines.
     *
     * @param mixed $value
     *
     * @return string
     */
    protected  function valueToString($value): string
    {
        try {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_scalar($value)) {
                $value = (string) $value;
            } elseif (is_array($value)) {
                $result = '';
                foreach ($value as $item) {
                    if (!is_object($item) || method_exists($item, '__toString')) {
                        if ($result !== '') {
                            $result .= ' ';
                        }
                        $result .= $item;
                    }
                }
                $value = $result !== '' ? $result : null;
            } elseif (!is_object($value) || method_exists($value, '__toString')) {
                // object with a _toString() method, null, or a resorce.
                $value = (string) $value;
            } else {
                // object without a _toString().
                $value = (string) json_encode($value, Util::JsonFlags);
            }
        } catch (Exception $e) {
            $this->log->exception($e);
            $value = '';
        }
        return $value;
    }
}
