<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\PropertySet;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\FieldExpander;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Invoice\InvoiceAddResult;
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
    private FieldExpander $fieldExpander;
    private Container $container;
    private Translator $translator;
    private Log $log;
    /**
     * @var (array|object)[]
     *   String keyed array of "objects" that can provide properties to the
     *   {@see FieldExpander}.
     */
    private array $propertySources;

    public function __construct(FieldExpander $fieldExpander, Container $container, Translator $translator, Log $log)
    {
        $this->fieldExpander = $fieldExpander;
        $this->container = $container;
        $this->translator = $translator;
        $this->log = $log;
    }

    /**
     * Returns the type of {@see \Siel\Acumulus\Data\AcumulusObject} that gets
     * collected.
     *
     * @return string
     *   The class name of the child clas of {@see AcumulusObject} this
     *   Collector is meant to operate on: a {@see \Siel\Acumulus\Data\DataType}
     *   constant.
     */
    protected function getAcumulusObjectType(): string
    {
        $fqClassName = static::class;
        $shortClass = substr($fqClassName, strrpos($fqClassName, '\\') + 1);
        return substr($shortClass, 0, -strlen('Collector'));
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

    protected function getFieldExpander(): FieldExpander
    {
        return $this->fieldExpander;
    }

    /**
     * Will throw an {@see \ErrorException} if no InvoiceAddResult is set as property
     * source under the name 'localResult'.
     */
    public function getLocalResult(): InvoiceAddResult
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection  might indeed throw. */
        return $this->getPropertySource('localResult');
    }


    /**
     * returns the property source with the given name.
     *
     * @param string $property
     *
     * @return array|object|null
     *   The property source with the given name, or null if not existing.
     */
    protected function getPropertySource(string $property): array|object|null
    {
        return $this->propertySources[$property] ?? null;
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
            $this->expandAndSet($acumulusObject, $field, $pattern);
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
    protected function expandAndSet(AcumulusObject $acumulusObject, string $field, mixed $value, int $mode = PropertySet::Always): bool
    {
        if ($acumulusObject->isProperty($field)) {
            return $value !== null && $acumulusObject->set($field, $this->expandValue($value), $mode);
        } elseif ($this->isMetadata($field)) {
            $acumulusObject->metadataSet($field, $this->expandValue($value));
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
    protected function expandValue(mixed $value): mixed
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
    protected function expand(string $fieldSpecification): mixed
    {
        return $this->getFieldExpander()->expand($fieldSpecification, $this->propertySources);
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
        return strncmp($field, 'meta', strlen('meta')) === 0
            || in_array($field, [Meta::UnitPriceInc, Meta::VatAmount], true);
    }

    /**
     * Helper method to add a warning to an InvoiceAddResult.
     *
     * Warnings are placed in the $array under the key Meta::Warning. If no
     * warning is set, $warning is added as a string, otherwise it becomes an
     * array of warnings to which this $warning is added.
     *
     * @todo: deprecate and inline.
     */
    protected function addWarning(AcumulusObject $acumulusObject, string $warning, string $severity = Meta::Warning): void
    {
        $acumulusObject->metadataAdd($severity, $warning, false);
    }
}
