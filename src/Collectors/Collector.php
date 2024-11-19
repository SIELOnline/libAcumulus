<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Config\Mappings;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\PropertySet;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\FieldExpander;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Meta;

use function get_class;
use function in_array;
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
    /**
     * @var \Siel\Acumulus\Config\Mappings
     *   Description.
     */
    private Mappings $mappings;
    private FieldExpander $fieldExpander;
    private Container $container;
    private Translator $translator;
    private Log $log;

    public function __construct(Mappings $mappings, FieldExpander $fieldExpander, Container $container, Translator $translator, Log $log)
    {
        $this->mappings = $mappings;
        $this->fieldExpander = $fieldExpander;
        $this->container = $container;
        $this->translator = $translator;
        $this->log = $log;
    }

    /**
     * Returns the type of the {@see \Siel\Acumulus\Data\AcumulusObject} to be collected.
     *
     * @return string
     *   A {@see \Siel\Acumulus\Data\DataType} constant.
     */
    protected function getAcumulusObjectType(): string
    {
        $fqClassName = static::class;
        $shortClass = substr($fqClassName, strrpos($fqClassName, '\\') + 1);
        return substr($shortClass, 0, -strlen('Collector'));
    }

    /**
     * Returns which set of mappings should be used.
     *
     * @return string
     *   The key for the set of mappings to be used, (the $$forType parameter to
     *   {@see \Siel\Acumulus\Config\Mappings::getFor()}.
     */
    protected function getMappingsGetForKey(): string
    {
        return $this->getAcumulusObjectType();
    }

    /**
     * Helper method to translate strings.
     *
     * @param string $key
     *  The key to get a translation for.
     *
     * @return string
     *   The translation for the given key or the key itself if no translation
     *   could be found.
     */
    protected function t(string $key): string
    {
        return $this->translator->get($key);
    }

    protected function getLog(): Log
    {
        return $this->log;
    }

    protected function getContainer(): Container
    {
        return $this->container;
    }

    public function getMappings(): Mappings
    {
        return $this->mappings;
    }

    protected function getFieldExpander(): FieldExpander
    {
        return $this->fieldExpander;
    }

    /**
     * Returns the field specifications to use.
     *
     * @return array
     *   See return value of {@see \Siel\Acumulus\Config\Mappings::getFor()}.
     */
    protected function getFieldSpecifications(?array $fieldSpecifications): array
    {
        if ($fieldSpecifications === null) {
            $fieldSpecifications = $this->getMappings()->getFor($this->getMappingsGetForKey());
        }
        return $fieldSpecifications;
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
     * This base implementation divides the collect action into 3 smaller phases:
     * - Creation of target {@see AcumulusObject}.
     * - Collecting based on simple field mappings (parameter $$fieldSpecifications).
     * - Collecting based on specialised logic that can use all the API methods and data
     *   models of the host environment (accessible via the property sources in parameter
     *   $$propertySources) to get the (missing) values for the fields of the
     *   target {@see \Siel\Acumulus\Data\AcumulusObject}.
     *
     * Between these phases child classes can inject their own behavior:
     * - method {@see collectBefore()}: called after the creation of the target object,
     *   but before the collecting starts.
     * - method {@see collectAfter()}: called after the 2 collecting phases, just before
     *   returning the resulting target object.
     *
     * @param \Siel\Acumulus\Collectors\PropertySources $propertySources
     *   String keyed set of "objects" that can provide properties to the
     *   {@see FieldExpander} for use in {@see collectMappedFields()} or just pass
     *   information for use in {@see collectLogicFields()}.
     * @param string[]|null $fieldSpecifications
     *   A set of field specifications keyed by the target field name (property or
     *   metadata field in the target {@see AcumulusObject}.
     *
     * @return \Siel\Acumulus\Data\AcumulusObject
     */
    public function collect(PropertySources $propertySources, ?array $fieldSpecifications): AcumulusObject
    {
        $fieldSpecifications = $this->getFieldSpecifications($fieldSpecifications);
        $acumulusObject = $this->createAcumulusObject();
        $this->collectBefore($acumulusObject, $propertySources, $fieldSpecifications);
        $this->collectMappedFields($acumulusObject, $propertySources, $fieldSpecifications);
        $this->collectLogicFields($acumulusObject, $propertySources);
        $this->collectAfter($acumulusObject, $propertySources);
        return $acumulusObject;
    }

    /**
     * Allows for subclasses to inject specific behaviour just after the new object to
     * collect has been constructed, but before the real collecting starts.
     *
     * This base implementation does nothing it is meant for subclasses.
     *
     * @param \Siel\Acumulus\Data\AcumulusObject $acumulusObject
     *   The newly constructed object to collect values for.
     * @param \Siel\Acumulus\Collectors\PropertySources $propertySources
     *   The set of öbjects"to collect the values from.
     * @param array $fieldSpecifications
     *   The set of mappings that will be used for the "automatic" part of the collection
     *   phase. @todo: for now it is an array: difficult to modify when passed to events.
     */
    protected function collectBefore(AcumulusObject $acumulusObject, PropertySources $propertySources, array &$fieldSpecifications): void
    {
    }

    /**
     * Allows for subclasses to inject specific behaviour just after the new object has
     * been collected.
     *
     * This base implementation does nothing it is meant for subclasses.
     *
     * @param \Siel\Acumulus\Data\AcumulusObject $acumulusObject
     *   The object on which the collected values have been set.
     * @param \Siel\Acumulus\Collectors\PropertySources $propertySources
     *   The set of öbjects"to collect the values came from.
     */
    protected function collectAfter(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
    }

    /**
     * Collects the fields that can be extracted using simple field mappings.
     *
     * @param (null|string|string[])[] $fieldSpecifications
     *   A set of field mapping specifications to fill properties of the
     *   $acumulusObject with.
     */
    protected function collectMappedFields(
        AcumulusObject $acumulusObject,
        PropertySources $propertySources,
        array $fieldSpecifications
    ): void {
        foreach ($fieldSpecifications as $field => $pattern) {
            $this->collectMappedField($acumulusObject, $propertySources, $field, $pattern);
        }
    }

    /**
     * Collects fields using logic more complex than a simple mapping.
     *
     * This base implementation does nothing as it cannot contain any (shop specific)
     * logic about the properties. Override if the actual data object does have properties
     * that cannot be set with a simple mapping and depend on shop data (thus not
     * configuration only).
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
    }

    /**
     * Sets a property of an Acumulus object to an expanded value.
     *
     * @param \Siel\Acumulus\Data\AcumulusObject $acumulusObject
     *   An object to set the property on.
     * @param \Siel\Acumulus\Collectors\PropertySources $propertySources
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
    protected function collectMappedField(
        AcumulusObject $acumulusObject,
        PropertySources $propertySources,
        string $field,
        mixed $value,
        int $mode = PropertySet::Always
    ): bool {
        if ($acumulusObject->isProperty($field)) {
            return $value !== null && $acumulusObject->set($field, $this->expandValue($value, $propertySources), $mode);
        } elseif ($this->isMetadata($field)) {
            $acumulusObject->metadataSet($field, $this->expandValue($value, $propertySources));
            return true;
        } else {
            $this->getLog()->notice(
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
    protected function expandValue(mixed $value, PropertySources $propertySources): mixed
    {
        if (is_string($value)) {
            $value = $this->expand($value, $propertySources);
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
    protected function expand(string $fieldSpecification, PropertySources $propertySources): mixed
    {
        return $this->getFieldExpander()->expand($fieldSpecification, $propertySources);
    }

    /**
     * Returns whether $field indicates a metadata name.
     *
     * @param string $field
     *
     * @return bool
     *   True if $field indicates a metadata name, false otherwise.
     */
    public function isMetadata(string $field): bool
    {
        return str_starts_with($field, 'meta')
            || in_array($field, [Meta::UnitPriceInc, Meta::VatAmount], true);
    }

    /**
     * Helper method to add a message to an InvoiceAddResult.
     *
     * The \$message is placed under the meta key passed as \$severity. If no message is
     * set yet, \$message is added as a string, otherwise it becomes an array of messages
     * to which \$message is added.
     */
    protected function addMessage(AcumulusObject $acumulusObject, string $message, string $severity = Meta::Warning): void
    {
        $acumulusObject->metadataAdd($severity, $message, false);
    }
}
