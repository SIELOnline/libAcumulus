<?php
namespace Siel\Acumulus\Invoice\CompletorStrategy;

use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\CompletorStrategyBase;

/**
 * Class ApplySameVatRate implements a vat completor strategy by applying the
 * same vat rate to each line to complete.
 *
 * It also tries a vat rate of 0%. If that works, the system might be
 * misconfigured or we have prepaid vouchers, but as we have to follow the
 * system anyway, we will return it as is.
 *
 * Current known usages:
 * - ???
 */
class ApplySameVatRate extends CompletorStrategyBase
{
    /**
     * This strategy should be tried first after the split strategies.
     *
     * @var int
     */
    static public $tryOrder = 30;

    /**
     * {@inheritdoc}
     */
    protected function execute()
    {
        // Try all vat rates.
        foreach ($this->possibleVatRates as $vatRate) {
            $vatRate = $vatRate['vatrate'];
            if ($this->tryVatRate($vatRate)) {
                return true;
            }
        }

        // Try with a 0 tax rate as prepaid vouchers have 0 vat rate this might be a
        // valid situation if the only lines to complete are voucher lines.
        return $this->tryVatRate(0.0);
    }

    /**
     * Tries 1 of the possible vat rates.
     *
     * @param float $vatRate
     *
     * @return bool
     */
    protected function tryVatRate($vatRate)
    {
        $this->description = "ApplySameVatRate($vatRate)";
        $this->replacingLines = array();
        $vatAmount = 0.0;
        foreach ($this->lines2Complete as $line2Complete) {
            $vatAmount += $this->completeLine($line2Complete, $vatRate);
        }

        Log::getInstance()->notice("ApplySameVatRate::tryVatRate($vatRate) results in %f", $vatAmount);
        // If the vat totals are equal, the strategy worked.
        // We allow for a reasonable margin, as rounding errors may add up.
        return Number::floatsAreEqual($vatAmount, $this->vat2Divide, 0.04);
    }
}
