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
 * CompleteIssueDate completes the {@see \Siel\Acumulus\Data\Invoice::$issueDate}
 * property of an {@see \Siel\Acumulus\Data\Invoice}.
 */
class CompleteIssueDate extends BaseCompletor
{
    protected function check(Invoice $invoice, ...$args): void
    {
        assert(count($args) >= 1);
        assert(
            in_array(
                $args[0],
                [Config::IssueDateSource_InvoiceCreate, Config::IssueDateSource_OrderCreate, Config::IssueDateSource_Transfer],
                true
            )
        );
    }

    /**
     * Completes the {@see \Siel\Acumulus\Data\Invoice::$issueDate} property.
     *
     * Additional parameters:
     * - 0: The source for the issue date: one of the
     *   Config::IssueDateSource_... constants. comes from a setting.
     *     - Config::IssueDateSource_Transfer: Use the transfer date (today).
     *     - Config::IssueDateSource_OrderCreate: Use the order create date.
     *     - Config::IssueDateSource_InvoiceCreate: Use the shop invoice date
     *       (with fallback to the order date if no invoice exists).
     */
    protected function do(Invoice $invoice, ...$args): void
    {
        /** @noinspection DuplicatedCode */
        $this->check($invoice, ...$args);
        $dateToUse = $args[0];
        switch ($dateToUse) {
            case Config::IssueDateSource_InvoiceCreate:
                $date = $invoice->metadataGet(Meta::ShopInvoiceDate) ?? $invoice->metadataGet(Meta::Date);
                break;
            case Config::IssueDateSource_OrderCreate:
                $date = $invoice->metadataGet(Meta::Date);
                break;
            case Config::IssueDateSource_Transfer:
                $date = null;
                break;
        }
        if (isset($date)) {
            $invoice->issueDate = $date;
        }
    }
}
