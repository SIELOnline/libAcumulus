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
 * property-alternative = full-property-name('+'full-property-name)?
 * full-property-name = (variable-name'::)?property-name
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
 * @example:
 *   $propertySpec1 = first+middle+last;
 *   $propertySpec2 = [first] [middle] [last];
 *   first = 'John'; middle = ''; last = 'Doe';
 *   Result1: 'John Doe'
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
    /** @var array */
    protected $variables;

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
     *   The pattern with tokens expanded with their actual value.
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
     *   The expanded value for this token.
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
     *   The value of the property, if found, the empty string otherwise.
     */
    protected function searchPropertySpec($propertySpec)
    {
        $value = null;
        $propertyAlternatives = explode('|', $propertySpec);
        foreach ($propertyAlternatives as $propertyAlternative) {
            $propertyConcatenation = explode('+', $propertyAlternative);
            foreach ($propertyConcatenation as $property) {
                $propertyValue = $this->searchProperty($property);
                if (!empty($propertyValue)) {
                    if ($value === null) {
                        $value = '';
                    }
                    if (!empty($value)) {
                        $value .= ' ';
                    }
                    $value .= $propertyValue;
                }
            }
            if ($value !== null && $value !== '') {
                break;
            }
        }

        if ($value === null) {
            Log::getInstance()->info("Token::searchProperty('%s'): not found", $propertySpec);
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
     *   property was not found (or really equals null or the empty string).
     */
    protected function searchProperty($property) {
        $value = null;
        $fullPropertyName = explode('::', $property);
        if (count($fullPropertyName) > 1) {
            $variableName = $fullPropertyName[0];
            $property = $fullPropertyName[1];
        }
        else {
            $variableName = '';
        }
        foreach ($this->variables as $key => $variable) {
            if (empty($variableName) || $key === $variableName) {
                $value = $this->getProperty($property, $variable);
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
     * - Calling the {property}() method (as existing method or via __call).
     *
     * Override if the property name or getter method is constructed differently.
     *
     * @param string $property
     * @param object|array $variable
     *
     * @return null|string
     *   The value for the property of the given name, or null or the empty
     *   string if not available (or the property really equals null or he empty
     *   string).
     */
    protected function getProperty($property, $variable)
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
            }
            elseif (isset($variable[$property])) {
                $value = $variable[$property];
            }
        } else {
            if (isset($variable->$property)) {
                $value = $variable->$property;
            } elseif (method_exists($variable, $property)) {
                $value = call_user_func_array(array($variable, $property), $args);
            } else {
                $method = 'get' . ucfirst($property);
                if (method_exists($variable, $method)) {
                    $value = call_user_func_array(array($variable, $method), $args);
                } elseif (method_exists($variable, '__get')) {
                    @$value = $variable->$property;
                } elseif (method_exists($variable, '__call')) {
                    try {
                        $value = @call_user_func_array(array($variable, $property), $args);
                    } catch (Exception $e) {
                    }
                    if ($value !== null && $value !== '') {
                        try {
                            $value = call_user_func_array(array($variable, $method), $args);
                        } catch (Exception $e) {
                        }
                    }
                }
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
}
