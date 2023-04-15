<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors\Invoice;

use Siel\Acumulus\Completors\BaseCompletorTask;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Invoice;

use function assert;

/**
 * CompleteMultiLineInfo completes the
 * {@see \Siel\Acumulus\Data\Invoice::$descriptionText} and
 * {@see \Siel\Acumulus\Data\Invoice::$invoiceNotes} properties of an
 * {@see \Siel\Acumulus\Data\Invoice}.
 */
class CompleteMultiLineProperties extends BaseCompletorTask
{
    /**
     * Completes the
     * {@see \Siel\Acumulus\Data\Invoice::$descriptionText} and
     * {@see \Siel\Acumulus\Data\Invoice::$invoiceNotes} properties.
     *
     * Changes any form of a newline to the literal \n, and tabs to \t.
     *
     * @param \Siel\Acumulus\Data\Invoice $acumulusObject
     * @param int ...$args
     *    Additional parameters: none
     */
    public function complete(AcumulusObject $acumulusObject, ...$args): void
    {
        assert($acumulusObject instanceof Invoice);
        $acumulusObject->descriptionText = str_replace(["\r\n", "\r", "\n", "\t"], ['\n', '\n', '\n', '\t'], $acumulusObject->descriptionText);
        $acumulusObject->invoiceNotes = str_replace(["\r\n", "\r", "\n", "\t"], ['\n', '\n', '\n', '\t'], $acumulusObject->invoiceNotes);
    }
}
