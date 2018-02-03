<?php
namespace Siel\Acumulus\Helpers;

use Exception;

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
 *
 * token = '[' property-specification ']'
 * property-specification = property-alternative('|'property-alternative)?
 * property-alternative = space-separated-property('+'space-separated-property)?
 * space-separated-property = full-property-name('&'full-property-name)?
 * full-property-name = (variable-name'::)?property-name|literal-text
 * literal-text = "text"
 *
 * Alternatives are expanded left to right until a property alternative is found
 * that is not empty.
 *
 * @example:
 *   $propertySpec = sku|ean|isbn; sku = empty; ean = 'Hello'; isbn = 'World';
 *   Result: 'Hello"
 *
 * Properties that are joined with a + in between them, are all expanded, where
 * the + gets replaced with a space if and only if the property directly
 * following it, is not empty.
 *
 * Properties that are joined with a & in between them, are all expanded and
 * concatenated directly, thus not with a space between them like with a +.
 *
 * Literal text that is joined with "real" properties using & or + only gets
 * returned when at least 1 of the "real" properties has a non-empty value.
 *
 * @example:
 *   $propertySpec1 = [first+middle+last];
 *   $propertySpec2 = [first&"."&last"&"@myshop.com"];
 *   $propertySpec2 = [first] [middle] [last];
 *   first = 'John'; middle = ''; last = 'Doe';
 *   Result1: 'John Doe'
 *   Result2: 'John.Doe@myshop.com'
 *   Result2: 'John  Doe'
 *
 * A full property name may contain the variable name (followed by :: to
 * distinguish it from the property name itself) to allow to specify which
 * object/variable should be taken when the property appears in multiple
 * objects/variables.
 *
 * @example:
 *   variables = array(
 *     'order => Order(id = 3, date_created = 2016-02-03, ...),
 *     'customer' => Customer(id = 5, , date_created = 2016-01-01, name = 'Doe', ...),
 *    );
 *   pattern = '[id] [customer::date_created] [name]'
 *   result = '3 2016-01-01 Doe'
 *
 * A property name should be:
 * - the name of a (public) property,
 * - have a getter in the from getProperty()
 * - be handled by the magic __get or__call method.
 *
 */
class Token {
    const TypeLiteral = 1;
    const TypeProperty = 2;

    /** @var array */
    protected $variables;

    /** @var \Siel\Acumulus\Helpers\Log */
    protected $log;

    /**
     * Constructor
     *
     * @param \Siel\Acumulus\Helpers\Log $log
     */
    public function __construct(Log $log)
    {
        $this->log = $log;
    }

    /**
     * Expands a string that can contain token patterns.
     *
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
    public function expand($pattern, array $variables)
    {
        $this->variables = $variables;
        return preg_replace_callback('/\[([^]]+)]/', array($this, 'tokenMatch'), $pattern);
    }

    /**
     * Callback for preg_replace_callback in Creator::getTokenizedValue().
     *
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
    protected function tokenMatch($matches)
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
    protected function searchPropertySpec($propertySpec)
    {
        $value = null;
        $propertyAlternatives = explode('|', $propertySpec);
        foreach ($propertyAlternatives as $propertyAlternative) {
            $spaceSeparatedProperties = explode('+', $propertyAlternative);
            $spaceSeparatedValues = array();
            foreach ($spaceSeparatedProperties as $spaceSeparatedProperty) {
                $nonSeparatedProperties = explode('&', $spaceSeparatedProperty);
                $nonSeparatedValues = array();
                foreach ($nonSeparatedProperties as $nonSeparatedProperty) {
                    if (substr($nonSeparatedProperty, 0, 1) === '"' && substr($nonSeparatedProperty, -1, 1) === '"') {
                        $nonSeparatedValue = substr($nonSeparatedProperty, 1, -1);
                        $valueType = self::TypeLiteral;
                    } else {
                        $nonSeparatedValue = $this->searchProperty($nonSeparatedProperty);
                        $valueType = self::TypeProperty;
                    }
                    $nonSeparatedValues[] = array('type' => $valueType, 'value' => $nonSeparatedValue);
                }
                $spaceSeparatedValues[] = $this->implodeValues('', $nonSeparatedValues);
            }
            $value = $this->implodeValues(' ', $spaceSeparatedValues);
            // Stop as soon as an alternative resulted in a non-empty value.
            if (!empty($value['value'])) {
                $value = $value['value'];
                break;
            } else {
                $value = null;
            }
        }

        if ($value === null) {
            $this->log->info("Token::searchProperty('%s'): not found", $propertySpec);
        }

        return $value !== null ? $value : '';
    }

    /**
     * Searches for a single property in the variables in $propertySources.
     *
     * @param string $property
     *
     * @return null|string
     *   The value of the property, may be the empty string or null if the
     *   property was not found (or really equals null or the empty string). The
     *   return value may be a scalar (numeric type) that can be converted to a
     *   string.
     */
    protected function searchProperty($property) {
        $value = null;
        $fullPropertyName = explode('::', $property);
        if (count($fullPropertyName) > 1) {
            $variableName = $fullPropertyName[0];
            $property = $fullPropertyName[1];
        } else {
            $variableName = '';
        }
        foreach ($this->variables as $key => $variable) {
            if (empty($variableName) || $key === $variableName) {
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
     * @return null|string
     *   The value for the property of the given name, or null or the empty
     *   string if not available (or the property really equals null or the
     *   empty string). The return value may be a scalar (numeric type) that can
     *   be converted to a string.
     */
    protected function getProperty($variable, $property)
    {
        $value = null;

        $args = array();
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
        } else {
            // It's an object: try to get the property.
            // Safest way is via the get_object_vars() function.
            $properties = get_object_vars($variable);
            if (!empty($properties) && array_key_exists($property, $properties)) {
                $value = $properties[$property];
            } else {
                // Try some other ways.
                $value = $this->getObjectProperty($variable, $property, $args);
            }
        }

        // Some web shops can return an array of values: try to convert to a
        // string by imploding it (hoping the values are all scalar).
        // Known uses: Magento2 street value: array of street lines.
        if (is_array($value)) {
            $value = implode(" ", $value);
        }

        return $value;
    }

    /**
     * Looks up a property in a web shop specific object.
     *
     * This part is extracted into as separate method so it can be overridden
     * with webshop specific ways to access properties. The base implementation
     * will probably get the property anyway,so override mainly to prevent
     * notices or warnings.
     *
     * @param object $variable
     *   The variable to search for the property.
     * @param string $property
     *   The property or function to get its value.
     * @param array $args
     *   Optional arguments to pass if it is a function.
     *
     * @return null|string
     *   The value for the property of the given name, or null or the empty
     *   string if not available (or the property really equals null or the
     *   empty string). The return value may be a scalar (numeric type) that can
     *   be converted to a string.
     */
    protected function getObjectProperty($variable, $property, array $args)
    {
        $value = null;
        $method1 = $property;
        $method2 = 'get' . ucfirst($property);
        $method3 = 'get_' . $property;
        if (method_exists($variable, $method1)) {
            $value = call_user_func_array(array($variable, $method1), $args);
        } elseif (method_exists($variable, $method2)) {
            $value = call_user_func_array(array($variable, $method2), $args);
        } elseif (method_exists($variable, $method3)) {
            $value = call_user_func_array(array($variable, $method3), $args);
        } elseif (method_exists($variable, '__get')) {
            @$value = $variable->$property;
        } elseif (method_exists($variable, '__call')) {
            try {
                $value = @call_user_func_array(array($variable, $property), $args);
            } catch (Exception $e) {
            }
            if ($value === null || $value === '') {
                try {
                    $value = call_user_func_array(array($variable, $method1), $args);
                } catch (Exception $e) {
                }
            }
            if ($value === null || $value === '') {
                try {
                    $value = call_user_func_array(array($variable, $method2), $args);
                } catch (Exception $e) {
                }
            }
            if ($value === null || $value === '') {
                try {
                    $value = call_user_func_array(array($variable, $method3), $args);
                } catch (Exception $e) {
                }
            }
        }
        return $value;
    }

    /**
     * Concatenates a list of values using a glue between them.
     *
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
    protected function implodeValues($glue, $values)
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

        return array('type' => $hasProperty ? self::TypeProperty : self::TypeLiteral ,'value' => $result);
    }
}
