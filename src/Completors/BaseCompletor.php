<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors;

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Meta;

use function assert;
use function count;
use function in_array;

/**
 * CompleteInvoiceNumber completes the {@see \Siel\Acumulus\Data\Invoice::$number}
 * property of an {@see \Siel\Acumulus\Data\Invoice}.
 */
abstract class BaseCompletor implements CompletorInterface
{
    /**
     * Checks the preconditions and throws on errors.
     */
    abstract protected function check(Invoice $invoice, ...$args): void;

    /**
     * Executes the completion task after the preconditions have been checked.
     */
    abstract protected function do(Invoice $invoice, ...$args): void;

    public function complete(Invoice $invoice, ...$args): void
    {
        $this->check($invoice, ...$args);
        $this->do($invoice, ...$args);
    }
}
