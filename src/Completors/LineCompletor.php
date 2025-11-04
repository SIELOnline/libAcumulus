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
use Siel\Acumulus\Invoice\InvoiceAddResult;

use function assert;

/**
 * LineCompletor completes a {@see Line}.
 *
 * After a line has been collected, the shop-specific part, it needs to be completed.
 * Think of things like:
 * - Adding config-based values
 * - Computing vat rates
 * - Converting to euros
 * - ...
 */
class LineCompletor extends BaseCompletor
{
    /**
     * Completes a {@see Line}.
     *
     * This phase is executed after the collecting phase.
     *
     * @param Line $acumulusObject
     * @param InvoiceAddResult $result
     */
    public function complete(AcumulusObject $acumulusObject, MessageCollection $result): void
    {
        assert($acumulusObject instanceof Line);
        assert($result instanceof InvoiceAddResult);

        $this->getContainer()->getCompletorTask(DataType::Line, 'ByConfig')->complete($acumulusObject);
        $this->getContainer()->getCompletorTask(DataType::Line, 'MarginProducts')->complete($acumulusObject);
        $this->getContainer()->getCompletorTask(DataType::Line, 'VatRange')->complete($acumulusObject);
    }
}
