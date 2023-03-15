<?php
/**
 * @noinspection PhpMissingStrictTypesDeclarationInspection  We cannot enforce
 *   strict typing as we may have to call functions/methods with literal number
 *   constants extracted from a string and thus passed as a string instead of a
 *   number.
 */

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
 * FieldExpander expands a field definition that can contain field mappings.
 *
 * When creating an Acumulus API message, the values that go in that message
 * can come from various sources:
 * - Settings: e.g. always define a customer as active.
 * - Logic: further split into "simple" mapping logic or "complex" computational
 *   logic:
 *   - Mappings: e.g. 'city' comes from the field 'city' from the customer's
 *     address, or 'fullname' is the concatenation of 'first_name' and
 *     'last_name' from the customer object.
 *   - Complex logic: often involving navigating relations in a database, with
 *     edge case handling, fallback values, and such.
 * - Combination from settings and logic: e.g. based on the setting "invoice
 *   number source", the invoice number is either defined by Acumulus, or comes
 *   from the invoice for a given order with fallback to the order number itself
 *   if no invoice is available.
 *
 * FieldExpander expands values based on a "field expansion specification":
 * - A "field expansion specification" is a string that specifies how to
 *   assemble the resulting value based on a mix of (possibly multiple) free
 *   text parts and "field extraction specifications".
 * - A "field extraction definition" is enclosed by square brackets, i.e. a '['
 *   and a ']' and can refer to multiple properties, either as alternative
 *   (fallback) or for concatenation.
 * - A property specification is a specification that specifies where a value
 *   should come from. Typically, it refers to a "property" of an "object".
 * - "Objects" are "data structures", in our domain typically the shop order, an
 *   order line, the customer, or an address. Depending on the webshop these
 *   "objects" may actually be (keyed) arrays.
 * - "Properties" are the values of these "objects", all elements that have or
 *   can return a value can be used: properties on real objects, key names on
 *   arrays, or (getter) methods on real objects. Even methods with parameters
 *   can be used.
 * - A "field expansion specification" that consists of a single property
 *   specification is returned in the type of the property, but as soon as
 *   properties get concatenated they are converted to a string:
 *     - bool: to the string 'true' or 'false'.
 *     - null: empty string.
 *     - number: string representation of the number. (@todo: precision?)
 *     - array: imploded with glue = ' '.
 *     - object: if the _toString() exists it will be called, otherwise
 *       {@see json_encode()} will be used.
 *
 * The syntax specification below formalizes the description above:
 * - field-expansion-specification = (free-text|'['expansion-specification']')*
 * - free-text = text
 * - expansion-specification = property-alternative('|'property-alternative)*
 * - property-alternative = space-concatenated-property('+'space-concatenated-property)*
 * - space-concatenated-property = single-property('&'single-property)*
 * - single-property = property-in-named-object|property-name|literal-text|constant
 * - property-in-named-object = (object-name'::')+property-name
 * - object-name = text
 * - property-name = text
 * - literal-text = "text"
 * - constant = 'true'|'false'|'null'  @todo: numeric constants?
 *
 * Notes:
 * - This syntax is quite simple. The following features are not possible:
 *     - Grouping, e.g. by using brackets, to override operator precedence.
 *     - Translation of literal strings.
 *       @todo: can we add translations, e.g. via a ::_t_ at the end or
 *         something like that. But if we go that way, maybe we should switch to
 *         twig (which has a sandbox and works with Symphony translations, and
 *         then use Symphony for logging, http, config, etc, as well).
 *     - Lookup based on a value of a property.
 * - The parsing is quite simple: the special symbols - ], |, &, and " - cannot
 *   appear otherwise:
 *     - Not as part of object or property names. This is not restricting as
 *       this is not normal for PHP object or property names, or array keys.
 *     - Not as part of literal strings. This is not considered restricting, as
 *       they will hardly be used given where this class will be used. Moreover,
 *       in most cases these characters can be placed outside variable field
 *       definitions with (mostly) the same results.
 * - Alternatives are expanded left to right until a property alternative is
 *   found that is not empty.
 * - Properties that are joined with a '+', are all expanded, where the '+' gets
 *   replaced with a space if and only if the property directly following it,
 *   is not empty (and we already have a non-empty intermediate result).
 * - Properties that are joined with a '&', are all expanded and concatenated
 *   directly, thus not with a space between them like with a '+'.
 * - Literal text that is joined with "real" properties using '&' or '+' only
 *   gets returned when at least 1 of the "real" properties have a non-empty
 *   value. (Otherwise, you could just place it outside the variable-field
 *   definition.)
 *
 * Example 1: Alternatives:
 * <pre>
 *   $propertySpec = sku|ean|isbn; sku = ''; ean = 'Hello'; isbn = 'World';
 *   Result: 'Hello'
 * </pre>
 *
 * Example 2: Concatenation, with ot without space:
 * <pre>
 *   first = 'John'; middle = ''; last = 'Doe';
 *   $propertySpec1 = [first] [middle] [last];
 *   $propertySpec2 = [first&middle&last];
 *   $propertySpec3 = [first+middle+last];
 *   $propertySpec4 = For [middle];
 *   $propertySpec5 = ["For"+middle];
 *   $propertySpec6 = ["For"+middle+last];
 *   Result1: 'John  Doe'
 *   Result2: 'JohnDoe'
 *   Result3: 'John Doe'
 *   Result4: 'For '
 *   Result5: ''
 *   Result6: 'For Doe'
 * </pre>
 *
 * A full property name may contain the "object" name followed by '::' to
 * distinguish it from the "property" name itself. This allows specifying which
 * object the property should be taken from. This is useful when multiple
 * "objects" have some equally named "properties" (e.g. 'id'). This also allows
 * to travers deeper into related objects, in which case this syntax is a
 * necessity, as plain properties are only searched for in the "top level"
 * "objects".
 *
 * Example 3:
 * <pre>
 *   objects = [
 *     'order => (id = 3, date_created = 2016-02-03, ...),
 *     'customer' => (id = 5, date_created = 2016-01-01, name = 'Doe',
 *       address => (street = 'Kalverstraat', number = '7', city = 'Amsterdam', ...),
 *       ...
 *     ),
 *    ];
 *   $pattern1 = '[id] [date_created] [name]'
 *   $pattern2 = '[customer::id] [customer::date_created] [name]'
 *   $pattern3 = '[customer::address::street+customer::address::number]'
 *   $pattern4 = '[street+number]'
 *
 *   Result1: '3 2016-02-03 Doe'
 *   Result2: '5 2016-01-01 Doe'
 *   Result3: 'Kalverstraat 7'
 *   Result4: ''
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
 * - Any method name that does not have required parameters.
 * - Or a name of a method that accepts scalar parameters, in which case literal
 *   arguments may be added between brackets, string arguments should not be
 *   quoted.
 *
 * An "object" is:
 * - An array.
 * - An object.
 * - A {@see is_callable() callable}, in which case the callable is called with
 *   the property name passed as argument. No known usages anymore.
 */
class FieldExpander
{
    protected const TypeLiteral = 1;
    protected const TypeProperty = 2;
    protected const Constants = [
        'true' => true,
        'false' => false,
        'null' => null,
    ];

    protected Log $log;

    /**
     * @var array
     *   A keyed array of "objects". The key indicates the name of the "object",
     *   typically the class name (with a lower cased 1st character) or the
     *   variable name typically used in the shop software. The "objects" are
     *   structures that contain information related to an order or associated
     *   objects like customer, shipping address, order line, credit note, ...,
     *   "Objects" can be objects or arrays.
     *   Internally, we see this list of "objects" as a super "object"
     *   containing all "objects" as (named) properties. In this sense it
     *   facilitates the recursive search algorithm when searching for a mapping
     *   like object1::object2::property.
     */
    protected array $objects;

    public function __construct(Log $log)
    {
        $this->log = $log;
    }

    /**
     * Extracts a value based on the field mapping.
     *
     * Mapping definitions are found using a regular expression. Each mapping
     * is expanded by searching the given "objects" for the referenced property
     * or properties.
     *
     * @param string $fieldSpecification
     *   The field expansion specification.
     * @param array $objects
     *   The "objects" to search for the properties that are referenced in the
     *   variable field parts. The key indicates the name of the "object",
     *   typically the class name (with a lower cased 1st character) or the
     *   variable name typically used in the shop software.
     *
     * @return mixed
     *   The expanded field expansion specification, which may be empty if the
     *   properties referred to do not exist or are empty themselves.
     *
     *   The type of the return value is either:
     *   - The type of the property requested if $fieldSpecification contains
     *     exactly 1 variable field specification, i.e. it begins with a '[' and
     *     the first and only ']' is at the end.
     *   - string otherwise.
     */
    public function expand(string $fieldSpecification, array $objects)
    {
        $this->objects = $objects;
        // If the specification contains exactly 1 field expansion specification
        // we return the direct result of {@see extractField()} so that the type
        // of that property is retained.
        if (strncmp($fieldSpecification, '[', 1) === 0
            && strpos($fieldSpecification, ']') === strlen($fieldSpecification) - 1
        ) {
            return $this->expandSpecification(substr($fieldSpecification, 1, -1));
        } else {
            return preg_replace_callback('/\[([^]]+)]/', [$this, 'expansionSpecificationMatch'], $fieldSpecification);
        }
    }

    /**
     * Expands a single variable field definition.
     *
     * This is the callback for preg_replace_callback() in {@see expand()}.
     * This callback expands the expansion specification found in $matches[1].
     *
     * @param array $matches
     *   Array containing match information, $matches[0] contains the match
     *   including the [ and ]., $matches[1] contains the part between the [
     *   and ].
     *
     * @return string
     *   The expanded value (converted to a string if necessary).
     */
    protected function expansionSpecificationMatch(array $matches): string
    {
        $expandedValue = $this->expandSpecification($matches[1]);
        if(!is_string($expandedValue)) {
            $expandedValue = $this->valueToString($expandedValue);
        }
        return $expandedValue;
    }

    /**
     * Expands a single "expansion-specification".
     *
     * - expansion-specification = property-alternative('|'property-alternative)*
     *
     * The first alternative resulting in a non-empty value is returned.
     *
     * @param string $expansionSpecification
     *   The specification to expand (without [ and ]).
     *
     * @return mixed
     *   The expanded value of the specification. This may result in null or the
     *   empty string if the referenced property(ies) is (are all) empty.
     */
    protected function expandSpecification(string $expansionSpecification)
    {
        $value = null;
        $propertyAlternatives = explode('|', $expansionSpecification);
        foreach ($propertyAlternatives as $propertyAlternative) {
            $value = $this->expandAlternative($propertyAlternative);
            // Stop as soon as an alternative resulted in a non-empty value.
            if ($value !== null && $value !== '') {
                break;
            }
        }

        if ($value === null || $value === '') {
            $this->log->debug("Field::expandSpecification('%s'): not found", $expansionSpecification);
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
     *
     * @return array
     *   Returns an array with 2 keys:
     *   - 'type' = self:: TypeLiteral or self::TypeProperty
     *   - 'value': the value of the single space-concatenated-property.
     *     If this space-concatenated-property contains exactly 1 single
     *     property, the type of this value is that of the single property.
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
     * - single-property = property-in-named-object|property-name|literal-text
     * - property-in-named-object = (object-name::)+property-name
     * - object-name = text
     * - property-name = text
     * - literal-text = "text"
     *
     * @return array
     *   Returns an array with 2 keys:
     *   - 'type' = self::TypeLiteral or self::TypeProperty
     *   - 'value': the value of a single-property.
     *     The type of this value is that of the single property.
     */
    protected function expandSingleProperty(string $singleProperty): array
    {
        if ($this->isLiteral($singleProperty)) {
            $type = self::TypeLiteral;
            $value = $this->getLiteral($singleProperty);
        } elseif ($this->isConstant($singleProperty)) {
            $type = self::TypeLiteral;
            $value = $this->getConstant($singleProperty);
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
     * Gets a literal string property.
     *
     * - literal-text = "text"
     *
     * @return string
     *   The text between the quotes
     */
    protected function getLiteral(string $singleProperty): string
    {
        return substr($singleProperty, 1, -1);
    }

    protected function isConstant(string $singleProperty): bool
    {
        return array_key_exists($singleProperty, static::Constants);
    }

    /**
     * Gets a constant value.
     *
     * - constant-text = 'true'|'false'|'null'
     *
     * @return mixed
     *   The value 'implied' by the constant, for now a bool or null.
     */
    protected function getConstant(string $singleProperty)
    {
        return static::Constants[$singleProperty];
    }

    /**
     * Expands a property-in-named-object.
     *
     * - property-in-named-object = (object-name::)+property-name
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
     * - single-property = property-in-named-object|property-name|literal-text
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
            if ($matches[2] !== '') {
                $args = explode(',', $matches[2]);
            }
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
     *   Returns an array with 2 keys:
     *   - 'type' = self:: TypeLiteral or self::TypeProperty
     *   - 'value': the concatenation of all the values with the glue string
     *     between each value. If $values contains exactly 1 value, that value
     *     is returned unaltered. So the type of this value is not necessarily a
     *     string.
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
