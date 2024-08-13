<?php

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

use Exception;

use function array_key_exists;
use function call_user_func_array;
use function count;
use function is_array;
use function is_callable;
use function is_object;
use function strlen;

/**
 * Contains functionality to expand a string that may contain tokens.
 *
 * Tokens are strings that refer to a property or method of a variable.
 * Variables are typically the shop order object, a customer object, an address
 * object, etc.
 *
 * A token is recognised by enclosing the property name (or better property
 * specification) within [ and ].
 *
 * A property specification in its simplest form is just a property name, but to
 * cater for some special cases it can be made more complex. See the syntax
 * definition below:
 * - token = '[' property-specification ']'
 * - property-specification = property-alternative('|'property-alternative)?
 * - property-alternative = space-separated-property('+'space-separated-property)?
 * - space-separated-property = full-property-name('&'full-property-name)?
 * - full-property-name' = ('variable-name'::)?property-name|literal-text
 * - literal-text = "text"
 *
 * Alternatives are expanded left to right until a property alternative is found
 * that is not empty.
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
 *
 * Example 2:
 * <pre>
 *   $propertySpec1 = [first+middle+last];
 *   $propertySpec2 = ["For"+first+middle+last];
 *   $propertySpec3 = [first&middle&last];
 *   $propertySpec4 = [first] [middle] [last];
 *   first = 'John'; middle = ''; last = 'Doe';
 *   Result1: 'John Doe'
 *   Result2: 'For John Doe'
 *   Result3: 'JohnDoe'
 *   Result4: 'John  Doe'
 * </pre>
 *
 * A full property name may contain the variable name (followed by :: to
 * distinguish it from the property name itself) to allow specifying which
 * object/variable should be taken when the property appears in multiple
 * objects/variables.
 *
 * Example 3:
 * <pre>
 *   variables = [
 *     'order => Order(id = 3, date_created = 2016-02-03, ...),
 *     'customer' => Customer(id = 5, date_created = 2016-01-01, name = 'Doe', ...),
 *    ];
 *   pattern = '[id] [customer::date_created] [name]'
 *   result = '3 2016-01-01 Doe'
 * </pre>
 *
 * A property name should:
 * - Be the name of a (public) property,
 * - have a getter in the form getProperty() or get_property(),
 * - or be handled by the magic __get or__call method.
 *
 * A property name may also be:
 * - A method name,
 * - optionally followed by arguments between brackets, string arguments should
 *   not be quoted.
 *
 * A variable is:
 * - An array.
 * - An object.
 * - A Callable, in which case the callable is called with the property name
 *   passed as argument.
 * - The key or a variable may be used in a property definition to indicate
 *   that the result may only come from that variable.
 *
 * @deprecated Use {@see \Siel\Acumulus\Helpers\FieldExpander}
 */
class Token
{
    public const TypeLiteral = 1;
    public const TypeProperty = 2;

    protected array $variables;
    protected Log $log;

    public function __construct(Log $log)
    {
        $this->log = $log;
    }

    /**
     * Expands a string that can contain token patterns.
     * Tokens are found using a regular expression. Each token found is expanded
     * by searching the provided variables for a property with the token name.
     *
     * @param string $pattern
     *   The pattern to expand.
     * @param string[] $variables
     *   A keyed array of variables, the key indicates which variable this is,
     *   typically the class name (with a lower cased 1st character) or the
     *   variable name typically used in the shop software.
     *
     * @return string
     *   The pattern with tokens expanded with their actual value. The return
     *   value may be a scalar (numeric type) that can be converted to a string.
     */
    public function expand(string $pattern, array $variables): string
    {
        $this->variables = $variables;
        return preg_replace_callback('/\[([^]]+)]/', [$this, 'tokenMatch'], $pattern);
    }

    /**
     * Callback for preg_replace_callback in Creator::getTokenizedValue().
     * This callback tries to expand the token found in $matches[1].
     *
     * @param array $matches
     *   Array containing match information, $matches[0] contains the match
     *   including the [ and ]., $matches[1] contains only the token name.
     *
     * @return string
     *   The expanded value for this token. The return value may be a scalar
     *   (numeric type) that can be converted to a string.
     */
    protected function tokenMatch(array $matches): string
    {
        return $this->searchPropertySpec($matches[1]);
    }

    /**
     * Searches for a property spec in the variables in $propertySources.
     *
     * @param string $propertySpec
     *   The property specification to expand.
     *
     * @return string
     *   The value of the property, if found, the empty string otherwise. The
     *   return value may be a scalar (numeric type) that can be converted to
     *   a string.
     */
    protected function searchPropertySpec(string $propertySpec): string
    {
        $value = null;
        $propertyAlternatives = explode('|', $propertySpec);
        foreach ($propertyAlternatives as $propertyAlternative) {
            $spaceSeparatedProperties = explode('+', $propertyAlternative);
            $spaceSeparatedValues = [];
            foreach ($spaceSeparatedProperties as $spaceSeparatedProperty) {
                $nonSeparatedProperties = explode('&', $spaceSeparatedProperty);
                $nonSeparatedValues = [];
                foreach ($nonSeparatedProperties as $nonSeparatedProperty) {
                    if ($nonSeparatedProperty[0] === '"' && $nonSeparatedProperty[strlen($nonSeparatedProperty) - 1] === '"') {
                        $nonSeparatedValue = substr($nonSeparatedProperty, 1, -1);
                        $valueType = self::TypeLiteral;
                    } else {
                        $nonSeparatedValue = $this->searchProperty($nonSeparatedProperty);
                        $valueType = self::TypeProperty;
                    }
                    $nonSeparatedValues[] = ['type' => $valueType, 'value' => $nonSeparatedValue];
                }
                $spaceSeparatedValues[] = $this->implodeValues('', $nonSeparatedValues);
            }
            $value = $this->implodeValues(' ', $spaceSeparatedValues);
            // Stop as soon as an alternative resulted in a non-empty value.
            if (!empty($value['value'])) {
                $value = $value['value'];
                break;
            }  else {
                $value = null;
            }
        }

        if ($value === null) {
            $this->log->debug("Token::searchProperty('%s'): not found", $propertySpec);
        }

        return (string) $value;
    }

    /**
     * Searches for a single property in the "objects" in $propertySources.
     *
     * @return mixed
     *   The value of the property, or the empty string or null if the property
     *   was not found (or really equals null or the empty string).
     */
    protected function searchProperty(string $property)
    {
        $value = null;
        $fullPropertyName = explode('::', $property, 2);
        if (count($fullPropertyName) === 2) {
            [$variableName, $property] = $fullPropertyName;
        } else {
            $variableName = '';
        }

        foreach ($this->variables as $key => $variable) {
            if ($variable !== null && (empty($variableName) || $key === $variableName)) {
                $value = $this->getProperty($variable, $property);
                if ($value !== null && $value !== '') {
                    break;
                }
            }
        }
        return $value;
    }

    /**
     * Looks up a property in the web shop specific order object/array.
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
     * @param object|array $variable
     *   The object or array to extract the property from.
     * @param string $property
     *   The property to extract from the variable.
     *
     * @return mixed
     *   The value for the property of the given name, or null or the empty
     *   string if not available (or the property really equals null or the
     *   empty string).
     */
    protected function getProperty($variable, string $property)
    {
        $value = null;

        $args = [];
        if (preg_match('/(.+)\((.*)\)/', $property, $matches)) {
            $property = $matches[1];
            $args = explode(',', $matches[2]);
        }
        if (is_array($variable)) {
            if (is_callable($variable)) {
                array_unshift($args, $property);
                $value = call_user_func_array($variable, $args);
            } elseif (isset($variable[$property])) {
                $value = $variable[$property];
            }
        } elseif (is_object($variable)) {
            // It's an object: try to get the property.
            // Safest and fastest way is via the get_object_vars() function.
            $properties = get_object_vars($variable);
            if (array_key_exists($property, $properties)) {
                $value = $properties[$property];
            }
            // WooCommerce can have the property customer_id set to null, while
            // the data store does contain a non-null value: so if value is
            // still null, even if it is in the get_object_vars() result, we
            // try to get it the more difficult way.
            if ($value === null) {
                // Try some other ways.
                $value = $this->getObjectProperty($variable, $property, $args);
            }
        }

        // Some properties may be arrays or objects: try to convert to a string
        // by "imploding" and/or calling __toString().
        // Known usages: Magento2 street value: array of street lines.
        try {
            if (is_array($value)) {
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
            } elseif (is_object($value) && method_exists($value, '__toString')) {
                $value = (string) $value;
            }
        } catch (Exception $e) {
            // @todo: log something.
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
    protected function getObjectProperty(object $variable, string $property, array $args)
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
     * @param string $glue
     * @param array[] $values
     *   A list of type-value pairs.
     *
     * @return array
     *   Returns a type-value pair containing as value a string representation
     *   of all the values with the glue string between each value.
     */
    protected function implodeValues(string $glue, array $values): array
    {
        $result = '';
        $hasProperty = false;
        $previous = '';
        foreach ($values as $value) {
            if ($value['type'] === self::TypeLiteral) {
                // Literal value: set aside and only add if next property value
                // is not empty.
                if (!empty($previous)) {
                    // Multiple literals after each other: treat as 1 literal
                    // but do glue them together.
                    $previous .= $glue;
                }
                $previous .= $value['value'];
            } else { // $value['type'] === self::TypeProperty
                // Property value: if it is not empty, add any previous literal
                // and the property value itself. If it is empty, discard any
                // previous literal value.
                if (!empty($value['value'])) {
                    if (!empty($previous)) {
                        if (!empty($result)) {
                            $result .= $glue;
                        }
                        $result .= $previous;
                    }
                    if (!empty($result)) {
                        $result .= $glue;
                    }
                    $result .= $value['value'];
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

        return ['type' => $hasProperty ? self::TypeProperty : self::TypeLiteral ,'value' => $result];
    }
}
