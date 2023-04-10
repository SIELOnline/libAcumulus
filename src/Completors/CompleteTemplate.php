<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors;

use Siel\Acumulus\Api;
use Siel\Acumulus\Data\Invoice;

use function assert;
use function count;
use function is_int;

/**
 * CompleteTemplate completes the {@see \Siel\Acumulus\Data\Invoice::$template}
 * property of an {@see \Siel\Acumulus\Data\Invoice}.
 */
class CompleteTemplate extends BaseCompletor
{
    protected function check(Invoice $invoice, ...$args): void
    {
        assert(count($args) >= 2);
        assert(is_int($args[0]));
        assert(is_int($args[1]));
    }

    /**
     * Completes the {@see \Siel\Acumulus\Data\Invoice::$template} property.
     *
     * Additional parameters:
     * - 0: int: default template for invoices
     * - 1: int: default template for paid invoices: 0 => use default template
     */
    protected function do(Invoice $invoice, ...$args): void
    {
        $this->check($invoice, ...$args);

        // Acumulus invoice template to use.
        $paymentStatus = $invoice->paymentStatus;
        [$defaultInvoiceTemplate, $defaultInvoicePaidTemplate] = $args;
        if ($paymentStatus === Api::PaymentStatus_Due || $defaultInvoicePaidTemplate === 0) {
            $template = $defaultInvoiceTemplate;
        } else {
            $template = $defaultInvoicePaidTemplate;
        }
        $invoice->template = $template;
    }
}
