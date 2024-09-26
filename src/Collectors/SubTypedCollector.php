<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\FieldExpander;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Meta;

/**
 * TypedCollector does foo.
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

    public function __construct(string $subType, FieldExpander $fieldExpander, Container $container, Translator $translator, Log $log)
    {
        $this->subType = $subType;
        parent::__construct($fieldExpander, $container, $translator, $log);
    }

    protected function collectBefore(AcumulusObject $acumulusObject, PropertySources $propertySources, array &$fieldSpecifications): void
    {
        $acumulusObject->metadataSet(Meta::SubType, $this->subType);
    }
}
