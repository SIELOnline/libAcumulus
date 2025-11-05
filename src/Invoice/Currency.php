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
 *
 * @todo PHP8.2 readonly class
 */
class Currency
{
    /**
     * The currency code used with the order/refund: ISO4217, ISO 3166-1.
     */
    readonly public string $currency;
    /**
     * Conversion rate from the used currency to the shop's default currency:
     * amount in shop's default currency = rate * amount in order/refund currency
     */
    readonly public float $rate;
    /**
     * True if we should use the above info to convert amounts, false if the amounts are
     * already in the shop's default currency (which should be euro) and all this info is
     * thus purely informational.
     */
    readonly public bool $doConvert;

    public function __construct(string $currency = 'EUR', float $rate = 1.0, bool $doConvert = false)
    {
        $this->currency = $currency;
        $this->rate = $rate;
        $this->doConvert = $doConvert;
    }

    /**
     * Returns whether amounts in the invoice are not expressed in euros.
     */
    public function shouldConvert(): bool
    {
        return $this->currency !== 'EUR' && $this->doConvert && !Number::floatsAreEqual($this->rate, 1.0, 0.00001);
    }

    /**
     * Converts an amount to Euro.
     */
    public function convertAmount(float $amount): float
    {
        if ($this->currency === 'EUR') {
            return $amount / $this->rate;
        } else {
            return $amount * $this->rate;
        }
    }
}
