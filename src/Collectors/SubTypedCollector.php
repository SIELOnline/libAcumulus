<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\FieldExpander;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Meta;

/**
 * TypedCollector does foo.
 */
abstract class SubTypedCollector extends Collector
{
    protected string $subType;

    public function __construct(string $subType, FieldExpander $fieldExpander, Container $container, Log $log)
    {
        $this->subType = $subType;
        parent::__construct($fieldExpander, $container, $log);
    }

    public function collect(array $propertySources, array $fieldSpecifications): AcumulusObject
    {
        $acumulusObject = parent::collect($propertySources, $fieldSpecifications);
        $acumulusObject->metadataSet(Meta::SubType, $this->subType);
        return $acumulusObject;
    }

}
