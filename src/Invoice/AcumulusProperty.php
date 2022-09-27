<?php

namespace Siel\Acumulus\Invoice;

use DateTime;
use DomainException;
use UnexpectedValueException;
use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Number;

/**
 * AcumulusProperty represents a scalar value that is sent as part of an API
 * call.
 *
 * Value sent with Acumulus API messages can be of type 'string', 'int',
 * 'float', or 'date'. Note that the AcumulusAPI does not use booleans,
 * properties that represent a "yes/no" value are mostly represented by an int
 * with allowed values 0 and 1.
 *
 * Additional restrictions may hold, and the basic and most used restrictions
 * are supported by this class:
 * - Required: even required values are initialized as null, only when trying to
 *   use it to construct a message will a required but empty value throw an
 *   error.
 * - Positive integer: modeled with a separate type 'id'.
 * - Enumerations: modeled with the allowedValues property of this class.
 *
 * Other restrictions, e.g. a string that should contain an e-mail address, are
 * not (yet) supported by this class and can(/should) be checked ona higher
 * level.
 */
class AcumulusProperty
{
    public const Set_Always = 0;
    public const Set_NotOverwrite = 1;
    public const Set_NotEmpty = 2;
    public const Set_NotOverwriteNotEmpty = self::Set_NotOverwrite | self::Set_NotEmpty;

    /** @var string[] */
    protected static array $allowedTypes = ['string', 'int', 'float', 'date', 'id'];

    protected string $name;
    protected bool $required;
    protected string $type;
    protected array $allowedValues;
    /** @var mixed|null */
    protected $value;

    /**
     * Creates a property based on the passed-in definition.
     *
     * @param array $propertyDefinition
     *   A property definition defines the:
     *   - 'name': (string, required) the name of the property, may contain
     *     upper case characters but when added to an Acumulus API message, it
     *     will be added in all lower case.
     *   - 'type': (string ,required) 1 of the allowed types.
     *   - 'required': (bool, optional, default = false) whether the property
     *     must be present in the Acumulus API message.
     *   - 'allowedValues': (array, optional, default = no restrictions) the set
     *     of allowed values for this property, each allowed value must be of
     *     the given type, typically an int, but string enumerations also appear
     *     in the Acumulus API.
     */
    public function __construct(array $propertyDefinition)
    {
        if (!isset($propertyDefinition['name'])) {
            throw new DomainException('Property name must be defined');
        }
        if (!is_string($propertyDefinition['name']) || empty($propertyDefinition['name'])) {
            throw new DomainException("Property name must be a string: {$propertyDefinition['name']}");
        }
        $this->name = $propertyDefinition['name'];

        if (!isset($propertyDefinition['type'])) {
            throw new DomainException('Property type must be defined');
        }
        if (!in_array($propertyDefinition['type'], static::$allowedTypes)) {
            throw new DomainException("Property type not allowed: {$propertyDefinition['type']}");
        }
        $this->type = $propertyDefinition['type'];

        if (isset($propertyDefinition['required']) && !is_bool($propertyDefinition['required'])) {
            throw new DomainException('Property required must be a bool');
        }
        $this->required = $propertyDefinition['required'] ?? false;

        if (isset($propertyDefinition['allowedValues']) && !is_array($propertyDefinition['allowedValues'])) {
            throw new DomainException('Property allowedValues must be an array');
        }
        $this->allowedValues = $propertyDefinition['allowedValues'] ?? [];

        $this->value = null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * @return mixed
     *   The value of this property, or null if not set.
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Assigns a value to the property.
     *
     * @param string|int|float|\DateTime|null $value
     *   The value to assign to this property, null and 'null' are valid values
     *   and will "unset" this property (it will not appear in the Acumulus API
     *   message).
     * @param int $mode
     *   1 of the AcumulusProperty::Set_... constants to prevent setting an
     *   empty value and/or overwriting an already set value. Default is to
     *   unconditionally set the value.
     *
     * @return bool
     *   true if the value was actually set, false otherwise.
     */
    public function setValue($value, int $mode = AcumulusProperty::Set_Always): bool
    {
        if ($value === 'null') {
            $value = null;
        }
        if ($value !== null) {
            switch ($this->type) {
                case 'string':
                    $value = (string) $value;
                    break;
                case 'int':
                case 'id':
                    if (!is_numeric($value)) {
                        throw new DomainException("$this->name: not a valid $this->type: " . var_export($value, true));
                    }
                    $iResult = (int) round($value);
                    if (!Number::floatsAreEqual($iResult, $value, 0.0002) || ($this->type === 'id' && $iResult <= 0)) {
                        throw new DomainException("$this->name: not a valid $this->type value: " . var_export($value, true));
                    }
                    $value = $iResult;
                    break;
                case 'float':
                    if (!is_numeric($value)) {
                        throw new DomainException("$this->name: not a valid $this->type:" . var_export($value, true));
                    }
                    $value = (float) $value;
                    break;
                case 'date':
                    $date = false;
                    if (is_string($value)) {
                        $date = DateTime::createFromFormat(Api::DateFormat_Iso, substr($value, 0, strlen('2000-01-01')));
                    } elseif (is_int($value)) {
                        $date = DateTime::createFromFormat('U', $value);
                    } elseif (is_float($value)) {
                        $date = DateTime::createFromFormat('U.u', $value);
                    } elseif ($value instanceof DateTime) {
                        $date = $value;
                    }
                    if ($date === false) {
                        throw new DomainException("$this->name: not a valid $this->type value: " . var_export($value, true));
                    }
                    $date->setTime(0, 0, 0, 0);
                    $value = $date;
                    break;
                default:
                    throw new UnexpectedValueException("$this->name: not a valid type: $this->type");
            }
            if (count($this->allowedValues) > 0 && !in_array($value, $this->allowedValues)) {
                throw new DomainException("$this->name: not an allowed value:" . var_export($value, true));
            }
        }
        if (($mode & AcumulusProperty::Set_NotOverwrite) !== 0 && $this->value !== null) {
            return false;
        }
        if (($mode & AcumulusProperty::Set_NotEmpty) !== 0 && empty($value)) {
            return false;
        }
        $this->value = $value;
        return true;
    }
}
