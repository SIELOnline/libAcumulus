<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors;

use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\PropertySet;
use Siel\Acumulus\Meta;

use function assert;
use function count;
use function in_array;

/**
 * CompleteConcept completes the {@see \Siel\Acumulus\Data\Invoice::$concept}
 * property of an {@see \Siel\Acumulus\Data\Invoice}.
 */
class CompleteConcept extends BaseCompletor
{
    protected function check(Invoice $invoice, ...$args): void
    {
        assert(count($args) >= 1);
        assert(
            in_array(
                $args[0],
                [Config::Concept_Plugin, Api::Concept_No, Api::Concept_Yes],
                true
            )
        );
    }

    /**
     * Completes the {@see \Siel\Acumulus\Data\Invoice::$issueDate} property.
     *
     * Additional parameters:
     * - 0: Send as concept: one of the following constants. comes from a
     *      setting.
     *     - Api::Concept_No: Always send as final invoice.
     *     - Api::Concept_Yes: Always send as concept.
     *     - Config::Concept_Plugin: Send as concept if warnings were found.
     */
    protected function do(Invoice $invoice, ...$args): void
    {
        $this->check($invoice, ...$args);
        $concept = $args[0];
        if ($concept === Config::Concept_Plugin) {
            $concept = $invoice->hasWarning();
        }
        if (isset($concept)) {
            $invoice->setConcept($concept, PropertySet::NotOverwrite);
        }
    }
}
