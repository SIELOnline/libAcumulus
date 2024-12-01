<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use DateTimeImmutable;
use DateTimeInterface;
use DomainException;
use UnexpectedValueException;
use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Number;

use function count;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function strlen;

/**
 * AcumulusProperty represents a scalar value that is sent as part of an API
 * call.
 *
 * Value sent with Acumulus API messages can be of type 'string', 'int',
 * 'float', 'date', or 'bool'. Note that the Acumulus API does not use booleans.
 * Properties that represent a "yes/no" value are mostly represented by an int
 * with allowed values 0 and 1. However, internally we do work with booleans for
 * these values.
 *
 * Additional restrictions may hold, and the basic and most used restrictions
 * are supported by this class:
 * - Required: even required values are initialized as null, only when trying to
 *   use it to construct a message will a required but empty value throw an
 *   error.
 * - Positive integer: modeled with a separate type 'id'.
 * - Enumerations: modeled with the allowedValues property of this class. For
 *   properties of type bool the allowedValues property of this class is used to
 *   map the 2 boolean values to their value on the Acumulus API.
 *
 * Other restrictions, e.g. a string that should contain an e-mail address, are
 * not (yet) supported by this class and should be checked on a higher level.
 *
 * In some cases, more specifically the customer data with an invoice_add call,
 * the API distinguishes between an absent tag and an empty tag, the latter
 * indicating to clear a value if "overwriteIfExists" is set to yes.
 * @todo: This is not yet handled correctly by this class.
 */
class AcumulusProperty
{
    /** @var string[] */
    protected static array $allowedTypes = ['string', 'int', 'float', 'date', 'id', 'bool'];

    protected string $name;
    protected bool $required;
    protected string $type;
    protected array $allowedValues;
    /** @var mixed|null */
    protected mixed $value;


    /**
     * Completes the shallow clone that PHP automatically performs.
     *
     * This implementation (deep) clones any object values. For now this can only be dates
     * represented with a {@see DateTimeInterface}.
     */
    public function __clone(): void
    {
        if (is_object($this->value))
        {
            $this->value = clone $this->value;
        }
    }

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
        if (!in_array($propertyDefinition['type'], static::$allowedTypes, true)) {
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
        if ($this->type === 'bool' && (!isset($propertyDefinition['allowedValues']) || count($propertyDefinition['allowedValues']) !== 2)) {
            throw new DomainException('Property allowedValues must define an array of 2 values for type bool');
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

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return mixed
     *   The value of this property, or null if not set.
     *
     * @legacy: the return by reference is to make the ArrayAccess working.
     */
    public function &getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Assigns a value to the property.
     *
     * @param int|float|string|bool|\DateTimeInterface|null $value
     *   The value to assign to this property, null and 'null' are valid values
     *   and will "unset" this property (it will not appear in the Acumulus API
     *   message).
     * @param int $mode
     *   1 of the PropertySet::... constants to prevent setting an
     *   empty value and/or overwriting an already set value. Default is to
     *   unconditionally set the value.
     *
     * @return bool
     *   true if the value was actually set, false otherwise.
     *
     * @noinspection PhpFunctionCyclomaticComplexityInspection
     * @noinspection CallableParameterUseCaseInTypeContextInspection yes, we are juggling
     *   with types.
     */
    public function setValue(mixed $value, int $mode = PropertySet::Always): bool
    {
        if (in_array($value, [null, 'null', ''], true)) {
            $value = null;
        } else {
            switch ($this->type) {
                case 'string':
                    $value = (string) $value;
                    break;
                case 'int':
                case 'id':
                    if (!is_numeric($value)) {
                        throw new DomainException("$this->name: not a valid $this->type: " . var_export($value, true));
                    }
                    $iResult = (int) round((float) $value);
                    if (($this->type === 'id' && $iResult <= 0) || !Number::floatsAreEqual($iResult, (float) $value, 0.0001)) {
                        throw new DomainException("$this->name: not a valid $this->type value: " . var_export($value, true));
                    }
                    $value = $iResult;
                    break;
                case 'float':
                    if (!is_numeric($value)) {
                        throw new DomainException("$this->name: not a valid $this->type value: " . var_export($value, true));
                    }
                    $value = (float) $value;
                    break;
                case 'date':
                    $date = false;
                    if (is_string($value)) {
                        $date = DateTimeImmutable::createFromFormat(Api::DateFormat_Iso, substr($value, 0, strlen('2000-01-01')));
                    } elseif (is_int($value)) {
                        $date = DateTimeImmutable::createFromFormat('U', (string) $value);
                    } elseif (is_float($value)) {
                        $date = DateTimeImmutable::createFromFormat('U.u', (string) $value);
                    } elseif ($value instanceof DateTimeInterface) {
                        $date = $value;
                    }
                    if ($date === false) {
                        throw new DomainException("$this->name: not a valid $this->type value: " . var_export($value, true));
                    }
                    $value = $date->setTime(0, 0, 0);
                    break;
                case 'bool':
                    if (!is_bool($value)) {
                        if (!in_array($value, $this->allowedValues, true)) {
                            throw new DomainException("$this->name: not a valid allowed bool value: " . var_export($value, true));
                        }
                        $value = $value === $this->allowedValues[1];
                    }
                    break;
                default:
                    throw new UnexpectedValueException("$this->name: not a valid type: $this->type");
            }
            if ($this->type !== 'bool' && count($this->allowedValues) > 0 && !in_array($value, $this->allowedValues, true)) {
                throw new DomainException("$this->name: not an allowed value: " . var_export($value, true));
            }
        }
        if (($mode & PropertySet::NotOverwrite) !== 0 && $this->value !== null) {
            return false;
        }
        if (($mode & PropertySet::NotEmpty) !== 0 && empty($value)) {
            return false;
        }
        $this->value = $value;
        return true;
    }

    /**
     * Returns the representation of the property as it will be in the API message.
     *
     * @return string|int|float
     *   The  representation of the property in a message. A bool is converted to one
     *   of its allowed values. A date is converted to its ISO representation
     *   (yyyy-mm-dd). Any other type is returned as is.
     */
    public function getApiValue(): float|int|string
    {
        $result = '';
        if ($this->value !== null) {
            switch ($this->type) {
                case 'string':
                case 'int':
                case 'id':
                case 'float':
                    $result = $this->value;
                    break;
                case 'date':
                    /** @var DateTimeInterface $date */
                    $date = $this->value;
                    $result = $date->format(Api::DateFormat_Iso);
                    break;
                case 'bool':
                    $result = $this->value ? $this->allowedValues[1] : $this->allowedValues[0];
                    break;
                default:
                    throw new UnexpectedValueException("$this->name: not a valid type: $this->type");
            }
        }
        return $result;
    }
}
