<?php

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\Token;

abstract class Collect
{
    protected Token $token;
    protected array $propertySources;
    protected array $fieldMappings;
    protected AcumulusObject $acumulusObject;

    public function __construct(Token $token)
    {
        $this->token = $token;
    }

    /**
     * Returns an {@see \Siel\Acumulus\Invoice\AcumulusObject}
     *
     * Creates an {@see \Siel\Acumulus\Invoice\AcumulusObject} by collecting
     * values for all its fields and metadata that might be added to the object.
     *
     * Values for fields may come from:
     * - A field pattern. In most cases, a field pattern will come from the
     *   module configuration. Field patterns may be as simple as a literal
     *   string; the value of 1 property from one of the property sources; to a
     *   (complex) combination of these. The {@see \Siel\Acumulus\Helpers\Token}
     *   class is used to compute a value given a field pattern. As this option
     *   gives a lot of flexibility to the user to override default behavior via
     *   simple configuration, this way should, if possible, be preferred over
     *   the next one.
     * - Internal logic. If getting a value based on a field pattern may not
     *   suffice, normally when database lookups or multiple calls to the
     *   internal webshop API are required, getting the value for a field will
     *   be hardcoded in a - webshop specific - child class of this class.
     *   Think of things like looking up an ISO country code based on an
     *   internal country id, or getting a tax rate based on tax class id of the
     *   product and address data from the customer.
     *
     * @param array $propertySources
     *   The objects to use with field mappings (token expansion).
     * @param array $fieldMappings
     *   The patterns for these fields that can be retrieved via a field
     *   mapping.
     *
     * @return \Siel\Acumulus\Invoice\AcumulusObject
     *   The AcumulusObject with its fields filled based on the $propertySources
     *   and the $fieldMappings, or the logic of, a more specialised child of,
     *   this class.
     */
    public function collect(array $propertySources, array $fieldMappings): AcumulusObject
    {
        $this->propertySources = $propertySources;
        $this->fieldMappings = $fieldMappings;
        $this->acumulusObject = $this->createAcumulusObject();
        $this->collectMappedFields($this->acumulusObject);
        $this->collectLogicFields($this->acumulusObject);
        return $this->acumulusObject;
    }

    /**
     * Creates a new {@see \Siel\Acumulus\Invoice\AcumulusObject} that will
     * contain the collected values.
     *
     * @return \Siel\Acumulus\Invoice\AcumulusObject
     */
    abstract protected function createAcumulusObject(): AcumulusObject;

    /**
     * @param \Siel\Acumulus\Invoice\AcumulusObject $acumulusObject
     */
    protected function collectMappedFields(AcumulusObject $acumulusObject)
    {
        foreach ($this->fieldMappings as $field => $pattern) {
            $this->expandAndSet($acumulusObject, $field, $pattern);
        }
    }

    /**
     * @param \Siel\Acumulus\Invoice\AcumulusObject $acumulusObject
     */
    abstract protected function collectLogicFields(AcumulusObject $acumulusObject);

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
    protected function expandAndSet(AcumulusObject $object, string $property, $value, int $mode = AcumulusProperty::Set_Always): bool
    {
        if ($value !== null && $value !== 'null') {
            return $object->set($property, $this->expand($value), $mode);
        }
        return false;
    }

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
}
