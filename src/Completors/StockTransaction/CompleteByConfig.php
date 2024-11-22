<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors\StockTransaction;

use Siel\Acumulus\Api;
use Siel\Acumulus\Completors\BaseCompletorTask;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\PropertySet;
use Siel\Acumulus\Meta;

use function assert;

/**
 * CompleteByConfig adds configuration based values.
 */
class CompleteByConfig extends BaseCompletorTask
{
    /**
     * Adds some values based on configuration.
     *
     * The following fields are set based on their corresponding config value:
     * - Meta::
     *
     * @param \Siel\Acumulus\Data\StockTransaction $acumulusObject
     */
    public function complete(AcumulusObject $acumulusObject, ...$args): void
    {
        $acumulusObject->metadataSet(Meta::MatchFieldSpecification, $this->configGet('productMatchField'));
    }
}
