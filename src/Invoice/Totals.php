<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

/**
 * Totals holds metadata about the invoice totals of an order/refund.
 *
 * @todo: PHP8.1: readonly properties. We made all properties public so we don't
 *   need additional code to convert it into a json string. However, please note
 *   that this object should be treated as immutable.
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
     * Specifies the tax distribution which may be useful for strategies
     * to complete. Currently, only used by OC
     *
     * @todo: separate from this Totals class as we want to use this class in
     *   more places to compare 3 amounts that make up an amount with tax.
     * @todo: at least, a __toString() method should be defined that does not
     *   render empty properties.
     */
    public ?array $vatBreakdown;
    /**
     * Support: which of the above fields were calculated (as opposed to fetched
     * from the webshop).
     *
     * Typically, a webshop stores 2 out of the 3 amounts, the 3rd to be
     * calculated from the other 2;
     */
    public ?string $calculated;

    public function __construct(?float $amountInc, ?float $amountVat, ?float $amountEx = null, ?array $vatBreakdown = null)
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
        $this->vatBreakdown = $vatBreakdown;
        $this->calculated = $calculated ?? null;
    }
}
