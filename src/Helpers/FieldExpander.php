<?php
/**
 * @noinspection PhpMissingStrictTypesDeclarationInspection  We cannot enforce
 *   strict typing as we may have to call functions/methods with literal number
 *   constants extracted from a string and thus passed as a string instead of a
 *   number.
 */

namespace Siel\Acumulus\Helpers;

use ArrayAccess;
use DateTimeInterface;
use Exception;

use ReflectionProperty;
use Siel\Acumulus\Api;
use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Meta;

use Stringable;
use Throwable;

use function array_key_exists;
use function count;
use function get_object_vars;
use function is_array;
use function is_bool;
use function is_callable;
use function is_object;
use function is_scalar;
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
 *     address, or 'full_name' is the concatenation of 'first_name' and
 *      'last_name' from the customer object.
 *    - Complex logic: often involving navigating relations in a database, with
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
 *   specification:
 *     - Can be signed by placing sign* directly after the '[' opening bracket.
 *     - Is returned in the type of the property, but as soon as properties get
 *       concatenated they are converted to a string:
 *         - bool: to the string 'true' or 'false'.
 *         - null: empty string.
 *         - number: string representation of the number. (@todo: precision?)
 *         - array: imploded with glue = ' '.
 *         - object: if the _toString() exists it will be called, otherwise
 *           {@see json_encode()} will be used.
 *
 * The syntax specification below formalizes the description above:
 * - field-expansion-specification = (free-text|'['expansion-specification']')*
 * - free-text = text
 * - expansion-specification = property-alternative('|'property-alternative)*
 * - property-alternative = space-concatenated-property('+'space-concatenated-property)*
 * - space-concatenated-property = property('&'property)*
 * - property = property-in-named-object|single-property-name|literal-text|constant
 * - property-in-named-object = (object-name'::')+property-name
 * - object-name = text
 * - single-property-name = text
 * - literal-text = "text"
 * - constant = 'true'|'false'|'null'  @todo: numeric constants?
 *
 * Notes:
 * - This syntax is quite simple. The following features are not possible:
 *     - Grouping, e.g. by using brackets, to override operator precedence.
 *     - Translation of literal strings. Use methods like {@see Source::getTypeLabel()}
 *       to allow to get translated texts.
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
 *     'order = {id = 3, date_created = 2016-02-03, ...},
 *     'customer' = {
 *       id = 5,
 *       date_created = 2016-01-01,
 *       name = 'Doe',
 *       address = {street = 'Kalverstraat', number = '7', city = 'Amsterdam', ...},
 *       ...
 *     },
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
 * A property name should be:
 * - The name of a (public) property,
 * - Handled by the magic method __get(),
 * - The name of (public) method (when it ends with (...)).
 * - Handled by the magic method __call() (when it ends with (...)).
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
 * - An object that implements ArrayAccess in which case both ways to retrieve the value
 *   are tried.
 *
 * FieldExpander will not throw on non-existing properties or methods but return null,
 * and will try to prevent non-fatal error or warning messages from being logged.
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
     * FieldExpander will not throw on non-existing properties or methods but return null,
     * and will try to prevent non-fatal error or warning messages from being logged.
     *
     * @param string $fieldSpecification
     *   The field expansion specification.
     * @param PropertySources $propertySources
     *   The "objects" to search for the properties that are referenced in the
     *   variable field parts. The key indicates the name of the "object",
     *   typically the class name (with a lower cased 1st character) or the
     *   variable name typically used in the shop software.
     *
     * @return mixed
     *   The expanded field expansion specification, which may be empty if the properties
     *   or methods referred to do not exist or return an empty value themselves.
     *
     *   The type of the return value is either:
     *   - If $fieldSpecification contains exactly 1 field specification (i.e. it begins
     *     with a '[' and the first and only ']' is at the end): the (return) type of the
     *     property or method specified in $field.
     *   - Otherwise: string
     */
    public function expand(string $fieldSpecification, PropertySources $propertySources): mixed
    {
        $this->objects = $propertySources->toArray();
        // If the specification contains exactly 1 field expansion specification
        // we return the direct result of {@see extractField()} so that the type
        // of that property is retained.
        if (str_starts_with($fieldSpecification, '[') && strpos($fieldSpecification, ']') === strlen($fieldSpecification) - 1) {
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
        if (!is_string($expandedValue)) {
            $expandedValue = $this->valueToString($expandedValue);
        }
        return $expandedValue;
    }

    /**
     * Expands a single "expansion-specification".
     *
     * - expansion-specification = property-alternative("|"property-alternative)*
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
    protected function expandSpecification(string $expansionSpecification): mixed
    {
        $value = null;
        if (str_starts_with($expansionSpecification, 'sign*')) {
            $sign = $this->objects['source']?->getSign() ?? 1;
            $expansionSpecification = substr($expansionSpecification, strlen('sign*'));
        } else {
            $sign = null;
        }

        $propertyAlternatives = explode('|', $expansionSpecification);
        foreach ($propertyAlternatives as $propertyAlternative) {
            $value = $this->expandAlternative($propertyAlternative);
            // Stop as soon as an alternative resulted in a non-empty value.
            if (!empty($value)) {
                break;
            }
        }

        if (empty($value)) {
            $this->log->debug("Field::expandSpecification('%s'): not found", $expansionSpecification);
        }

        if ($sign !== null && !empty($value)) {
            $value = $sign * $value;
        }
        return $value;
    }

    /**
     * Expands a Property alternative.
     *
     * - property-alternative = space-concatenated-property("+"space-concatenated-property)*
     */
    protected function expandAlternative(string $propertyAlternative): mixed
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
     * - space-concatenated-property = concatenated-property('&'concatenated-property)*
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
        $concatenatedProperties = explode('&', $spaceConcatenatedProperty);
        $concatenatedPropertyValues = [];
        foreach ($concatenatedProperties as $concatenatedProperty) {
            $concatenatedPropertyValues[] = $this->expandProperty($concatenatedProperty);
        }
        return $this->implodeValues('', $concatenatedPropertyValues);
    }

    /**
     * Expands a single property specification.
     *
     * - single-property = property-in-named-object|property-name|literal|constant
     * - property-in-named-object = (object-name::)+property-name
     * - object-name = text
     * - property-name = text
     * - literal = "text"
     * - constant = 'true'|'false'|'null'
     *
     * @return array
     *   Returns an array with 2 keys:
     *   - 'type' = self::TypeLiteral or self::TypeProperty
     *   - 'value': the value of a single-property.
     *     The type of this value is that of the single property.
     */
    protected function expandProperty(string $property): array
    {
        if ($this->isLiteral($property)) {
            $type = self::TypeLiteral;
            $value = $this->getLiteral($property);
        } elseif ($this->isConstant($property)) {
            $type = self::TypeLiteral;
            $value = $this->getConstant($property);
        } elseif (str_contains($property, '::')) {
            $type = self::TypeProperty;
            $value = $this->expandPropertyInObject($property);
        } else {
            $type = self::TypeProperty;
            $value = $this->expandSinglePropertyName($property);
        }
        return compact('type', 'value');
    }

    protected function isLiteral(string $singleProperty): bool
    {
        return str_starts_with($singleProperty, '"') && str_ends_with($singleProperty, '"');
    }

    /**
     * Gets a literal string property.
     *
     * - literal = "text"
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
     * - constant = 'true'|'false'|'null'
     *
     * @return mixed
     *   The value 'implied' by the constant, for now a bool or null.
     */
    protected function getConstant(string $singleProperty): mixed
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
    protected function expandPropertyInObject(string $propertyInObject): mixed
    {
        // Start searching in the "super object".
        $object = $this->objects;
        $propertyParts = explode('::', $propertyInObject);
        while (count($propertyParts) > 0 && $object !== null) {
            $propertyName = array_shift($propertyParts);
            $object = $this->getValue($propertyName, $object);
        }
        return $object;
    }

    /**
     * Expands a single named property.
     *
     * - single-property-name = text
     *
     * A single named property does not need its parent object. The property is searched
     * for in ths "super object" itself and in all objects in that super object
     * ($this->objects). These objects are not recursively searched (to prevent endless
     * recursion).
     *
     * @param string $propertyName
     *   The name of a property to search for, or the name of an object in which case the
     *   "object" is returned
     *
     * @return mixed
     *   the value of the property, or null if the property was not found.
     */
    protected function expandSinglePropertyName(string $propertyName): mixed
    {
        // Look in the super object itself.
        if (array_key_exists($propertyName, $this->objects)) {
            return $this->objects[$propertyName];
        }
        // Search in the objects of the super object, so 1 level deep only.
        foreach ($this->objects as $object) {
            $property = $this->getValue($propertyName, $object);
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
     * 1. If $property ends with '([args])':
     *    - Return the value of the call to method $object->$property(...$args)
     *    - [we could accept string functions if $object is a string, or array functions if
     *      $object is an array, but that is too risky: e.g. unlink()]
     * 2. If the passed variable is an array or implements ArrayAccess:
     *    - Return the value of $object[$property] if it exists.
     * 3. If the passed variable is an object:
     *    - Return the value of $object->$property.
     *
     * Override if the property name or getter method is constructed differently.
     *
     * FieldExpander will not throw on non-existing properties or methods but return null.
     * but it will try to prevent error or warning messages as well by building in some
     * checks before just calling a method, accessing an array, or getting a property.
     *
     * @param string $property
     *   The name of the property to extract from the "object".
     * @param array|object $object
     *   The "object" to extract the property from.
     *
     * @return mixed
     *   The value for the property of the given name on the given object, or null or the
     *   empty string if property does not exist or is not accessible, not even via
     *   __call() or __get().
     */
    protected function getValue(string $property, array|object $object): mixed
    {
        $value = null;

        if (preg_match('/^(.+)\((.*)\)$/', $property, $matches)) {
            // Case 1: method
            $property = $matches[1];
            $args = explode(',', $matches[2]);
            $value = $this->getValueFromMethod($object, $property, $args);
        } else {
            //
            if (is_array($object) || $object instanceof ArrayAccess) {
                $value = $this->getValueFromArray($object, $property);
            }
            // If the object implements ArrayAccess, the value might already be retrieved.
            // If not, continue the "object way".
            if ($value === null && is_object($object)) {
                $value = $this->getValueFromProperty($object, $property);
            }
        }

        return Number::castNumericValue($value);
    }

    /**
     * Retrieves a value by calling $method on $object.
     *
     * The method can be a real existing and accessible method (@see is_callable()} or be
     * handled by the magic
     * {@link https://www.php.net/manual/en/language.oop5.overloading.php#language.oop5.overloading.methods __call()}
     * function.
     *
     * @noinspection PhpUsageOfSilenceOperatorInspection
     */
    protected function getValueFromMethod(object $object, string $method, array $args): mixed
    {
        try {
            return is_callable([$object, $method])
                ? @$object->$method(...$args)
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Retrieves a value by retrieving an array index.
     *
     * @param array|ArrayAccess $array
     *    The key value data set to search in.
     * @param int|string $index
     *    The name of the property to search for.
     *
     * @return mixed
     *    The value for the entry at the given index, or null if the index does not exist.
     */
    protected function getValueFromArray(array|ArrayAccess $array, mixed $index): mixed
    {
        try {
            return $array[$index] ?? null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Retrieves a value by retrieving a property value.
     */
    protected function getValueFromProperty(object $object, string $property): mixed
    {
        $value = null;
        try {
            // Try to get the property. The safest way is via {@see get_object_vars()}.
            $properties = get_object_vars($object);
            $value = $properties[$property] ?? null;
            // However, WooCommerce can have the property customer_id set to null, while
            // the data store does contain a non-null value: so if value is still null,
            // even if it is in the get_object_vars() result, we try to get it the more
            // difficult way.
            if ($value === null &&
                ((property_exists($object, $property) && (new ReflectionProperty($object, $property))->isPublic())
                    || method_exists($object, '__get'))
            ) {
                /**
                 * @noinspection PhpVariableVariableInspection
                 * @noinspection PhpUsageOfSilenceOperatorInspection  There are still ways
                 *   that the statement below can fail, especially via the __get() variant.
                 */
                $value = @$object->$property;
            }
        } catch (Throwable) {
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
    protected function valueToString(mixed $value): string
    {
        try {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_scalar($value)) {
                $value = (string) $value;
            } elseif (is_array($value)) {
                $result = '';
                foreach ($value as $item) {
                    if (!is_object($item) || $item instanceof Stringable) {
                        if ($result !== '') {
                            $result .= ' ';
                        }
                        $result .= $item;
                    }
                }
                $value = $result !== '' ? $result : null;
            } elseif ($value instanceof DateTimeInterface) {
                // Date(Time).
                if ($value->format('H:i:s') === '00:00:00') {
                    $value = $value->format(Api::DateFormat_Iso);
                } else {
                    $value = $value->format(Api::Format_TimeStamp);
                }
            } elseif (!is_object($value) || $value instanceof Stringable) {
                // object with a _toString() method, null, or a resource.
                $value = (string) $value;
            } else {
                // object without a _toString().
                /** @noinspection JsonEncodingApiUsageInspection false positive */
                $value = (string) json_encode($value, Meta::JsonFlagsLooseType);
            }
        } catch (Exception $e) {
            $this->log->exception($e);
            $value = '';
        }
        return $value;
    }
}
