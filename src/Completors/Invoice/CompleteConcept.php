<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Completors\BaseCompletorTask;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\PropertySet;

use function assert;

/**
 * CompleteConcept completes the {@see \Siel\Acumulus\Data\Invoice::$concept}
 * property of an {@see \Siel\Acumulus\Data\Invoice}.
 */
class CompleteConcept extends BaseCompletorTask
{
    /**
     * Completes the {@see \Siel\Acumulus\Data\Invoice::$concept} property.
     *
     * @param \Siel\Acumulus\Data\Invoice $acumulusObject
     * @param int ...$args
     *   Additional parameters: none.
     */
    public function complete(AcumulusObject $acumulusObject, ...$args): void
    {
        assert($acumulusObject instanceof Invoice);
        // 0 is a valid value.
        $concept = $this->configGet('concept');
        if ($concept === Config::Concept_Plugin) {
            $concept = $acumulusObject->hasWarning();
        }
        if ($concept !== null) {
            $acumulusObject->setConcept($concept, PropertySet::NotOverwrite);
        }
    }
}
