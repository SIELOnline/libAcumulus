<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice\CompletorStrategy;

use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\CompletorStrategyBase;
use Siel\Acumulus\Meta;

/**
 * Class ApplySameVatRate implements a vat completor strategy by applying the
 * same vat rate to each line to complete.
 *
 * It will try all vat rates in property $possibleVatRates, including a vat rate
 * of 0%. If that works, the system might be misconfigured OR we have prepaid
 * vouchers, but as we have to follow the system anyway, we will return it as
 * is.
 *
 * The order in which vat rates are tried is based on the number of times the
 * vat rate appears in the other lines, thereby preventing introducing
 * non-appearing vat rates on zero amount lines (where every vat rate tried will
 * succeed).
 *
 * Current known usages:
 * - Magento free shipping lines.
 *
 * @noinspection PhpUnused
 *   Instantiated via a variable containing the name.
 */
class ApplySameVatRate extends CompletorStrategyBase
{
    /**
     * This strategy should be tried first after the split strategies.
     *
     * @var int
     */
    public static int $tryOrder = 30;

    protected function execute(): bool
    {
        // Try all possible vat rates.
        foreach ($this->getVatBreakdown() as $vatRateInfo) {
            $vatRate = $vatRateInfo[Fld::VatRate];
            if ($this->tryVatRate($vatRate)) {
                return true;
            }
        }

        // Try with a 0 vat rate. As prepaid vouchers have 0 vat rate this might
        // be a valid situation if the only lines to complete are voucher lines.
        return $this->tryVatRate(0.0);
    }

    /**
     * Tries 1 of the possible vat rates.
     */
    protected function tryVatRate(float $vatRate): bool
    {
        $this->description = "ApplySameVatRate($vatRate)";
        $this->replacingLines = [];
        $vatAmount = 0.0;
        foreach ($this->lines2Complete as $line2Complete) {
            $vatAmount += $this->completeLine($line2Complete, $vatRate);
        }

        $this->invoice[Fld::Customer][Fld::Invoice][Meta::CompletorStrategy . $this->getName()] = "tryVatRate($vatRate): $vatAmount";
        // If the vat totals are equal, the strategy worked.
        // We allow for a reasonable margin, as rounding errors may add up.
        return Number::floatsAreEqual($vatAmount, $this->vat2Divide, 0.04);
    }
}
