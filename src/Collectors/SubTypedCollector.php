<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use ArrayObject;
use Siel\Acumulus\Config\Mappings;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\FieldExpander;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Meta;

/**
 * SubTypedCollector adds a subType property to collectors for those data type that have
 * subtypes: Line, Address, and EmailAsPdf
 */
abstract class SubTypedCollector extends Collector
{
    /**
     * @var string
     *   The subtype of the data object to collect, one of the constants from
     *   {@see \Siel\Acumulus\Data\LineType}, {@see \Siel\Acumulus\Data\AddressType}, or
     *   {@see \Siel\Acumulus\Data\EmailAsPdfType}.
     */
    protected string $subType;

    public function __construct(
        string $subType,
        Mappings $mappings,
        FieldExpander $fieldExpander,
        Container $container,
        Translator $translator,
        Log $log
    )
    {
        $this->subType = $subType;
        parent::__construct($mappings, $fieldExpander, $container, $translator, $log);
    }

    /**
     * This override returns the subtype, a {@see \Siel\Acumulus\Data\LineType} constant
     * as mappings differ per subtype (address type, line type, emailAsPdf type).
     *
     * @noinspection PhpMissingParentCallCommonInspection Default base method.
     */
    protected function getMappingsGetForKey(): string
    {
        return $this->subType;
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection Empty base method.
     */
    protected function collectBefore(
        AcumulusObject $acumulusObject,
        PropertySources $propertySources,
        ArrayObject $fieldSpecifications
    ): void {
        $acumulusObject->metadataSet(Meta::SubType, $this->subType);
    }
}
