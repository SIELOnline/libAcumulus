<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

use function assert;
use function in_array;

/**
 * Totals holds metadata about the invoice totals of an order/refund.
 *
 * @todo: this class now represents a more generalised "amount" (broken up into its parts)
 *   and thus can probably be used more widely.
 * @todo: add precision?
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
     * @var string[]
     *   Support: which of the above fields were calculated (as opposed to fetched from
     *   the webshop).
     *   Typically, a webshop stores 2 out of the 3 amounts, the 3rd to be calculated from
     *   the other 2. The $amountVat may be passed as a $vatRate, which should be passed
     *   as a fraction, i.e. value between 0 and 1.
     */
    public array $calculated;

    /**
     * Constructor for an amount.
     *
     * Typically, 2 out of the 4 parameters are passed and the others are then calculated.
     * This simplifies extracting amounts from webshop data stores: just pass what is
     * directly available, do not try to calculate yourself. This also allows to factor in
     * some precision as this class keeps track of which fields were calculated.
     *
     */
    public function __construct(?float $amountInc, ?float $amountVat, ?float $amountEx = null, ?float $vatRate = null)
    {
        $calculated = $this->completeParameters($amountInc, $amountVat, $amountEx, $vatRate);

        $this->amountInc = $amountInc;
        $this->amountVat = $amountVat;
        $this->amountEx = $amountEx;
        $this->calculated = $calculated;
    }

    public function add(?float $amountInc, ?float $amountVat, ?float $amountEx = null, ?float $vatRate = null): static
    {
        $calculated = $this->completeParameters($amountInc, $amountVat, $amountEx, $vatRate);

        $this->amountEx += $amountEx;
        $this->amountInc += $amountInc;
        $this->amountVat += $amountVat;
        $this->calculated += array_unique(array_merge($this->calculated, $calculated));
        return $this;
    }

    /**
     * Calculates all parameters that were passed as null.
     *
     * @return string[]
     *   The list of parameter names that were null and are now calculated.
     */
    private function completeParameters(?float &$amountInc, ?float &$amountVat, ?float &$amountEx, ?float $vatRate): array
    {
        assert($vatRate === null || ($vatRate >= 0.0 && $vatRate < 1.0));
        $calculated = [];
        // Check if amount ex is set, otherwise calculate it.
        if ($amountEx === null) {
            if ($amountInc === null) {
                assert($amountVat !== null && $vatRate !== null);
                // Quite a special situation: only vat amount and rate are known. We can
                // calculate the amount ex (and thus inc), but the results can have a very
                // low precision. But we (have to) continue anyway.
                $amountEx = $amountVat / $vatRate;
            } elseif ($amountVat === null) {
                assert($vatRate !== null);
                // Amount inc and vat rate known: as consumers we see this all the time:
                // calculate the amount ex.
                $amountEx = $amountInc / (1 + $vatRate);
            } else {
                // Amount inc and vat amount known: easy to calculate the amount ex.
                $amountEx = $amountInc - $amountVat;
            }
            $calculated[] = 'amountEx';
        }

        // Check if amount inc is set, otherwise calculate it.
        if ($amountInc === null) {
            if ($amountVat === null) {
                assert($vatRate !== null);
                $amountInc = (1 + $vatRate) * $amountEx;
            } else {
                $amountInc = $amountEx + $amountVat;
            }
            $calculated[] = 'amountInc';
        }

        // Check if AmountVat is set, otherwise calculate it.
        if ($amountVat === null) {
            $amountVat = $amountInc - $amountEx;
            $calculated[] = 'amountVat';
        }

        return $calculated;
    }

    public function getVatRate(): float
    {
        return $this->amountVat / $this->amountEx;
    }

    public function isAmountIncCalculated(): bool
    {
        return in_array('amountInc', $this->calculated, true);
    }

    public function isAmountExCalculated(): bool
    {
        return in_array('amountEx', $this->calculated, true);
    }

    public function isAmountVatCalculated(): bool
    {
        return in_array('amountVat', $this->calculated, true);
    }
}
