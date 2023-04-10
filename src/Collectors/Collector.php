<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\PropertySet;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\FieldExpander;

use Siel\Acumulus\Helpers\Log;

use function get_class;
use function is_string;
use function strlen;

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
    private FieldExpander $fieldExpander;
    private Container $container;
    protected Log $log;

    protected array $propertySources;

    public function __construct(FieldExpander $fieldExpander, Container $container, Log $log)
    {
        $this->fieldExpander = $fieldExpander;
        $this->container = $container;
        $this->log = $log;
    }

    /**
     * Returns the type of {@see \Siel\Acumulus\Data\AcumulusObject} that gets
     * collected.
     *
     * @return string
     *   The class name of the child clas of {@see AcumulusObject} this
     *   Collector is meant or operate on: 'Invoice', 'Customer', 'Address',
     *   'Line', or 'EmailAsPdf'.
     */
    protected function getAcumulusObjectType(): string
    {
        $fqClassName = static::class;
        $shortClass = substr($fqClassName, strrpos($fqClassName, '\\') + 1);
        return substr($shortClass, 0, -strlen('Collector'));
    }

    protected function getContainer(): Container
    {
        return $this->container;
    }

    protected function getFieldExpander(): FieldExpander
    {
        return $this->fieldExpander;
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
     * This base implementation divides the collector phase into 2 smaller
     * phases:
     * - Collecting based on simple field mappings.
     * - Collecting based on specialised logic that can use all the API methods
     *   and data models of the host environment to get the (missing) values for
     *   the fields of the target {@see \Siel\Acumulus\Data\AcumulusObject}.
     */
    public function collect(array $propertySources, array $fieldSpecifications): AcumulusObject
    {
        $this->propertySources = $propertySources;
        $acumulusObject = $this->createAcumulusObject();
        $this->collectMappedFields($acumulusObject, $fieldSpecifications);
        $this->collectLogicFields($acumulusObject);
        return $acumulusObject;
    }

    /**
     * Collects the fields that can be extracted using simple field mappings.
     *
     * @param (null|string|string[])[] $fieldSpecifications
     *   A set of field mapping specifications to fill properties of the
     *   $acumulusObject with.
     */
    protected function collectMappedFields(AcumulusObject $acumulusObject, array $fieldSpecifications): void
    {
        foreach ($fieldSpecifications as $field => $pattern) {
            if ($pattern !== null) {
                $this->expandAndSet($acumulusObject, $field, $pattern);
            }
        }
    }

    /**
     * Collects fields using logic more complex than a simple mapping.
     *
     * This base implementation does nothing as it cannot contain any logic
     * about the properties. Override if the actual data object does have
     * properties that cannot be set with a simple mapping, but do depend on
     * shop data (thus not configuration only).
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
    }

    /**
     * Sets a property of an Acumulus object to an expanded value.
     *
     * @param \Siel\Acumulus\Data\AcumulusObject $acumulusObject
     *   An object to set the property on.
     * @param string $field
     *   The name of the property or metadata key to set.
     * @param mixed $value
     *   The value to set the property to, or a pattern to expand and set the
     *   resulting value to the property.
     * @param int $mode
     *   The mode to use when setting the property. One of the
     *   {@see PropertySet} constants.
     *
     * @return bool
     *   Whether the value was set.
     */
    protected function expandAndSet(AcumulusObject $acumulusObject, string $field, $value, int $mode = PropertySet::Always): bool
    {
        if ($acumulusObject->isProperty($field)) {
            return $acumulusObject->set($field, $this->expandValue($value), $mode);
        } elseif (strncmp($field, 'meta', strlen('meta')) === 0) {
            $acumulusObject->metadataAdd($field, $this->expandValue($value));
            return true;
        } else {
            $this->log->notice(
                '%s: %s does not have a property %s, nor is it considered metadata',
                __METHOD__,
                get_class($acumulusObject),
                $field
            );
            return false;
        }
    }

    /**
     * Returns the expanded value.
     *
     * @param mixed $value
     *   The value to expand and return.
     *
     * @return mixed
     *   The expanded value, or the value itself it was not a field
     *   specification.
     */
    protected function expandValue($value)
    {
        if (is_string($value)) {
            $value = $this->expand($value);
        }
        return $value;
    }

    /**
     * Wrapper method around {@see FieldExpander::expand()}.
     *
     * @param string $fieldSpecification
     *  A field specification that may contain field mappings.
     *
     * @return mixed
     *   The expanded field specification, which may be empty if the properties
     *   referred to, do not exist or are empty themselves.
     */
    protected function expand(string $fieldSpecification)
    {
        return $this->getFieldExpander()->expand($fieldSpecification, $this->propertySources);
    }
}
