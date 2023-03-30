<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\Number;

/**
 * Currency holds metadata about the currency of an order/refund.
 *
 * @todo: PHP8.1: readonly properties. We made all properties public so we don't
 *   need additional code to convert it into a json string. However, please note
 *   that this object should be treated as immutable.
 */
class Currency
{
    /**
     * The currency code used with the order/refund: ISO4217, ISO 3166-1.
     */
    public string $currency;
    /**
     * Conversion rate from the used currency to the shop's default currency:
     * amount in shop currency = rate * amount in other currency
     */
    public float $rate;
    /**
     * true if we should use the above info to convert amounts, false if the
     * amounts are already in the shop's default currency (which should be euro)
     * and all this info is thus purely informational.
     */
    public bool $doConvert;

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
        return $this->doConvert && !Number::floatsAreEqual($this->rate, 1.0, 0.0001);
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
