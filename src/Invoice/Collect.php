<?php

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Helpers\Token;

abstract class Collect
{
    protected Config $config;
    protected Token $token;
    protected array $propertySources;

    public function __construct(Token $token, Config $config)
    {
        $this->token = $token;
        $this->config = $config;
    }

    abstract public function collect(array $propertySources): AcumulusObject;

    /**
     * Wrapper method around Token::expand().
     *
     * The values of variables in $pattern are taken from 1 the property sources
     * known to this collector.
     *
     * @param string $pattern
     *  The value that may contain dynamic variables.
     *
     * @return string
     *   The pattern with variables expanded with their actual value.
     */
    protected function expand(string $pattern): string
    {
        return $this->token->expand($pattern, $this->propertySources);
    }

    /**
     * Expands and sets a possibly dynamic value to an Acumulus object.
     *
     * This method will:
     * - Overwrite already set properties.
     * - If the non-expanded value equals 'null', the property will not be set,
     *   but also not be unset.
     * - If the expanded value is empty the property will be set (with that
     *   empty value).
     *
     * @param \Siel\Acumulus\Invoice\AcumulusObject $object
     *   An object to set the property on.
     * @param string $property
     *   The name of the property to set.
     * @param mixed $value
     *   The value to set the property to that may contain variable fields.
     *
     * @return bool
     *   Whether the value was set.
     */
    protected function expandAndSet(AcumulusObject $object, string $property, $value): bool
    {
        if ($value !== null && $value !== 'null') {
            $object->$property = $this->expand($value);
            return true;
        }
        return false;
    }
}
