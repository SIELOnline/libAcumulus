<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\AcumulusProperty;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Field;

use function is_string;

/**
 * Collector is the abstract base class for a collector.
 *
 * It defines a strategy for collecting the requested data by dividing it into
 * 2 smaller phases:
 * - Collecting based on field mappings.
 * - Collecting based on specialised logic that considers the host environment
 *   API and data models and the fields of the target
 *   {@see \Siel\Acumulus\Data\AcumulusObject}.
 *
 * Child classes should typically do the following:
 * - Pass the type of the {@see \Siel\Acumulus\Data\AcumulusObject} to be
 *   collected and returned to the parent constructor.
 * - Define the logic based phase by implementing {@see collectLogicFields()}.
 */
abstract class Collector implements CollectorInterface
{
    protected Field $field;
    protected Container $container;
    protected array $propertySources;

    public function __construct(Field $field, Container $container)
    {
        $this->field = $field;
        $this->container = $container;
    }

    /**
     * Returns the type of {@see \Siel\Acumulus\Data\AcumulusObject} that gets
     * collected.
     *
     * @return string
     *   The non-qualified name of the child classes of {@see AcumulusObject:
     *   'Invoice', 'Customer', 'Address', 'Line', or 'EmailAsPdf'.
     */
    abstract protected function getAcumulusObjectType(): string;

    /**
     * @return \Siel\Acumulus\Helpers\Container
     */
    protected function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Returns a new child class of {@see \Siel\Acumulus\Data\AcumulusObject}
     * that will contain the collected values.
     */
    protected function createAcumulusObject(): AcumulusObject
    {
        return $this->getContainer()->createAcumulusObject($this->getAcumulusObjectType());
    }

    /**
     * {@inheritDoc}
     *
     * This base class divides the collector phase into 2 smaller phases:
     * - Collecting based on simple field mappings.
     * - Collecting based on specialised logic that can use all the API methods
     *   and data models of the host environment to get the (missing) values for
     *   the fields of the target {@see \Siel\Acumulus\Data\AcumulusObject}.
     */
    public function collect(array $propertySources, array $fieldDefinitions): AcumulusObject
    {
        $this->propertySources = $propertySources;
        $acumulusObject = $this->createAcumulusObject();
        $this
            ->collectMappedFields($acumulusObject, $fieldDefinitions)
            ->collectLogicFields($acumulusObject);
        return $acumulusObject;
    }

    /**
     * Collects the fields that can be extracted using simple field mappings.
     *
     * @param string[] $fieldMappings
     *   A set of field mapping definitions to fill properties of the
     *   $acumulusObject with.
     *
     * @return $this
     */
    protected function collectMappedFields(AcumulusObject $acumulusObject, array $fieldMappings): self
    {
        foreach ($fieldMappings as $field => $pattern) {
            $this->expandAndSet($acumulusObject, $field, $pattern);
        }
        return $this;
    }

    /**
     * @param \Siel\Acumulus\Data\AcumulusObject $acumulusObject
     */
    abstract protected function collectLogicFields(AcumulusObject $acumulusObject): void;

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
     * @param \Siel\Acumulus\Data\AcumulusObject $object
     *   An object to set the property on.
     * @param string $property
     *   The name of the property to set.
     * @param mixed $value
     *   The value to set the property to, or a pattern that may contain
     *   property or method names that are to be extracted from the
     *   {@see $propertySources}.
     *
     * @return bool
     *   Whether the value was set.
     */
    protected function expandAndSet(AcumulusObject $object, string $property, $value, int $mode = AcumulusProperty::Set_Always): bool
    {
        if ($value !== null && $value !== 'null') {
            if (is_string($value)) {
                $value = $this->expand($value);
            }
            // The set() method will take care of casting the value to the
            // correct type.
            return $object->set($property, $value, $mode);
        }
        return false;
    }

    /**
     * Wrapper method around Token::expand().
     *
     * The values of variables in $pattern are taken from one of the property
     * sources known to this collector.
     *
     * @param string $pattern
     *  The value that may contain field references.
     *
     * @return string
     *   The pattern with variables expanded with their actual value. Note that
     *   this will always be a string, even if the pattern refers to a single
     *   property which is not a string.
     */
    protected function expand(string $pattern): string
    {
        return $this->field->expand($pattern, $this->propertySources);
    }
}
