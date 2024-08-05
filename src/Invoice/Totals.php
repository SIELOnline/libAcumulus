<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

/**
 * Totals holds metadata about the invoice totals of an order/refund.
 *
 * @todo: what if we have 1 amount (ex or inc) and a vat rate? how to handle that
 */
class Totals
{
    /**
     * Creator -> Completor: the total amount ex vat of the invoice.
     */
    public ?float $amountEx;
    /**
     * Creator -> Completor: the total amount inc vat of the invoice.
     */
    public ?float $amountInc;
    /**
     * Creator -> Completor: the total vat amount of the invoice.
     */
    public ?float $amountVat;
    /**
     * string[]
     *   Support: which of the above fields were calculated (as opposed to fetched
     *   from the webshop).
     *   Typically, a webshop stores 2 out of the 3 amounts, the 3rd to be
     *   calculated from the other 2;
     */
    public array $calculated;

    public function __construct(?float $amountInc, ?float $amountVat, ?float $amountEx = null)
    {
        if (!isset($amountEx)) {
            $amountEx = $amountInc - $amountVat;
            $calculated = 'amountEx';
        } elseif (!isset($amountInc)) {
            $amountInc = $amountEx + $amountVat;
            $calculated = 'amountInc';
        } elseif (!isset($amountVat)) {
            $amountVat = $amountInc - $amountEx;
            $calculated = 'amountVat';
        }
        $this->amountInc = $amountInc;
        $this->amountVat = $amountVat;
        $this->amountEx = $amountEx;
        $this->calculated = [$calculated] ?? [];
    }

    public function add(?float $amountInc, ?float $amountVat, ?float $amountEx = null):void
    {
        if (!isset($amountEx)) {
            $amountEx = $amountInc - $amountVat;
            $calculated = 'amountEx';
        } elseif (!isset($amountInc)) {
            $amountInc = $amountEx + $amountVat;
            $calculated = 'amountInc';
        } elseif (!isset($amountVat)) {
            $amountVat = $amountInc - $amountEx;
            $calculated = 'amountVat';
        }
        $this->amountInc += $amountInc;
        $this->amountVat += $amountVat;
        $this->amountEx += $amountEx;
        if (isset($calculated)) {
            $this->calculated[] = $calculated;
            $this->calculated = array_unique($this->calculated);
        }
    }
}
