<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\PropertySet;
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
    private Container $container;
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
     * This base implementation divides the collector phase into 3 smaller
     * phases:
     * - Collecting based on simple field mappings.
     * - Collecting based on specialised logic that can use all the API methods
     *   and data models of the host environment to get the (missing) values for
     *   the fields of the target {@see \Siel\Acumulus\Data\AcumulusObject}.
     * - Collecting any child objects.
     */
    public function collect(array $propertySources, array $fieldDefinitions): AcumulusObject
    {
        $this->propertySources = $propertySources;
        $acumulusObject = $this->createAcumulusObject();
        $this->collectMappedFields($acumulusObject, $fieldDefinitions)
            ->collectLogicFields($acumulusObject)
            ->collectChildObjects($acumulusObject, $fieldDefinitions);
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
     * Collects fields using logic more complex than a simple mapping.
     *
     * This base implementation just returns $this as it cannot contain any
     * logic about the properties. Override if the actual data object does have
     * properties that cannot be set with a simple mapping, but do depend on
     * shop data (thus not configuration only).
     *
     * @return $this
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): self
    {
        return $this;
    }

    /**
     * Collects any child objects.
     *
     * @param string[] $fieldMappings
     *   A set of field mapping definitions to fill properties of the
     *   $acumulusObject with.
     *
     * @return $this
     */
    protected function collectChildObjects(AcumulusObject $acumulusObject, array $fieldMappings): self
    {
        return $this;
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
    protected function expandAndSet(AcumulusObject $object, string $property, $value, int $mode = PropertySet::Always): bool
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
     * @return mixed
     *   The pattern with variable field definitions expanded with their actual
     *   value, which may be empty, if the properties referred to do not exist
     *   or are empty themselves.
     */
    protected function expand(string $pattern)
    {
        return $this->field->expand($pattern, $this->propertySources);
    }
}
