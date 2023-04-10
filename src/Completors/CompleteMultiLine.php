<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors;

use Siel\Acumulus\Data\Invoice;

/**
 * CompleteMultiLineInfo completes the
 * {@see \Siel\Acumulus\Data\Invoice::$descriptionText} and
 * {@see \Siel\Acumulus\Data\Invoice::$invoiceNotes} properties of an
 * {@see \Siel\Acumulus\Data\Invoice}.
 */
class CompleteMultiLine extends BaseCompletor
{
    protected function check(Invoice $invoice, ...$args): void
    {
        // No arguments: no checks.
    }

    /**
     * Completes the
     * {@see \Siel\Acumulus\Data\Invoice::$descriptionText} and
     * {@see \Siel\Acumulus\Data\Invoice::$invoiceNotes} properties of an
     * {@see \Siel\Acumulus\Data\Invoice}.
     *
     * Additional parameters: none
     *
     * Change any form of a newline to the literal \n, tabs are not supported
     * (and will get lost in the xml message).
     */
    protected function do(Invoice $invoice, ...$args): void
    {
        $invoice->descriptionText = str_replace(["\r\n", "\r", "\n"], '\n', $invoice->descriptionText);
        $invoice->invoiceNotes = str_replace(["\r\n", "\r", "\n"], '\n', $invoice->invoiceNotes);
    }
}
