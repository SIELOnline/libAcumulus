<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\Number;

/**
 * Currency holds metadata about the currency of an order/refund.
 *
 * Individual orders/refunds can be paid in a currency different from the shop's default
 * currency (which should be Euro). If so, all amounts should be converted to euro before
 * sending them to Acumulus. This object contains the necessary information to do so.
 */
readonly class Currency
{
    /**
     * @param string $currency
     *   The currency code used with the order/refund: ISO4217, ISO 3166-1.
     * @param float $rate
     *   Conversion rate from the used currency to the shop's default currency:
     *   amount in shop's default currency = rate * amount in order/refund currency
     * @param bool $doConvert
     *   True if we should use the above info to convert amounts, false if the amounts are
     *   already in the shop's default currency (which should be euro) and all this info
     *   is thus purely informational.
     */
    public function __construct(public string $currency = 'EUR', public float $rate = 1.0, public bool $doConvert = false)
    {
    }

    /**
     * Returns whether amounts in the invoice are not expressed in euros.
     */
    public function shouldConvert(): bool
    {
        return $this->doConvert && !Number::floatsAreEqual($this->rate, 1.0, 0.00001);
    }

    /**
     * Converts an amount if it should be converted.
     */
    public function convertAmount(?float $amount): ?float
    {
        if ($this->doConvert && $amount !== null) {
            $amount = $this->currency === 'EUR' ? $amount / $this->rate : $amount * $this->rate;
        }
        return $amount;
    }
}
