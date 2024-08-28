<?php
/**
 * @noinspection PhpPrivateFieldCanBeLocalVariableInspection  In the future,
 *   $line may be made a local variable, but probably we will need it as a property.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Completors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Helpers\MessageCollection;

/**
 *LineCompletor completes a {@see \Siel\Acumulus\Data\Line}.
 *
 * After a line has been collected, the shop specific part, it needs to be completed.
 * Think of things like:
 * - Adding config based values
 * - Computing vat rates
 * - Converting to euros
 * - ...
 */
class LineCompletor extends BaseCompletor
{
    private Line $line;

    /**
     * Completes a {@see \Siel\Acumulus\Data\Line}.
     *
     * This phase is executed after the collecting phase.
     *
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     * @param \Siel\Acumulus\Invoice\InvoiceAddResult $result
     */
    public function complete(AcumulusObject $acumulusObject, MessageCollection $result): void
    {
        $this->line = $acumulusObject;

        $this->getContainer()->getCompletorTask(DataType::Line, 'ByConfig')->complete($this->line);
    }
}
