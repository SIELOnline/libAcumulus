<?php

namespace Siel\Acumulus\Invoice;

use DateTime;
use DomainException;
use UnexpectedValueException;
use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Number;

class AcumulusProperty
{
    /** @var string[] */
    protected static array $allowedTypes = ['string', 'int', 'float', 'date', 'id'];

    protected string $name;
    protected bool $required;
    protected string $type;
    protected array $allowedValues;
    /** @var mixed|null */
    protected $value;

    /**
     * @param array $propertyDefinition
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
     *
     * @param mixed $value
     *   The value to assign to this property
     *
     * @throws \DomainException|\UnexpectedValueException
     */
    public function setValue($value)
    {
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
                    $value = $date;
                    break;
                default:
                    throw new UnexpectedValueException("$this->name: not a valid type: $this->type");
            }
            if (count($this->allowedValues) > 0 && !in_array($value, $this->allowedValues)) {
                throw new DomainException("$this->name: not an allowed value:" . var_export($value, true));
            }
        }
        $this->value = $value;
    }
}
