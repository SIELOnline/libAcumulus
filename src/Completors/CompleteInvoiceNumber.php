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
class CompleteInvoiceNumber extends BaseCompletor
{
    protected function check(Invoice $invoice, ...$args): void
    {
        assert(count($args) >= 1);
        assert(
            in_array(
                $args[0],
                [Config::InvoiceNrSource_ShopInvoice, Config::InvoiceNrSource_ShopOrder, Config::InvoiceNrSource_Acumulus],
                true
            )
        );
    }

    /**
     * Completes the {@see \Siel\Acumulus\Data\Invoice::$number} property.
     *
     * Additional parameters:
     * - 0: The source for the invoice number: one of the
     *   Config::InvoiceNrSource_... constants, comes from a setting.
     *     - Config::InvoiceNrSource_Acumulus: Let Acumulus decide (recommended).
     *     - Config::InvoiceNrSource_ShopOrder: Use the order number.
     *     - Config::InvoiceNrSource_ShopInvoice: Use the shop invoice number
     *       (with fallback to the order number if no invoice exists).
     */
    protected function do(Invoice $invoice, ...$args): void
    {
        /** @noinspection DuplicatedCode */
        $this->check($invoice, ...$args);
        $sourceToUse = $args[0];
        switch ($sourceToUse) {
            case Config::InvoiceNrSource_ShopInvoice:
                $number = $invoice->metadataGet(Meta::ShopInvoiceReference)
                    ?? $invoice->metadataGet(Meta::Reference);
                break;
            case Config::InvoiceNrSource_ShopOrder:
                $number = $invoice->metadataGet(Meta::Reference);
                break;
            case Config::InvoiceNrSource_Acumulus:
                $number = null;
                break;
        }
        if (isset($number)) {
            $invoice->number = preg_replace('/\D/', '', $number);
        }
    }
}
