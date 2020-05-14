<?php
namespace Siel\Acumulus\Invoice\CompletorStrategy;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\CompletorStrategyBase;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

/**
 * Class ApplySameVatRate implements a vat completor strategy by applying the
 * same vat rate to each line to complete.
 *
 * It also tries a vat rate of 0%. If that works, the system might be
 * misconfigured or we have prepaid vouchers, but as we have to follow the
 * system anyway, we will return it as is.
 *
 * Current known usages:
 * - Magento free shipping lines
 *
 * @todo: try this first with vat rate that actually do appear in the invoice.
 *   Doing so, we can prevent adding 6% to a free shipping line on an all 21%
 *   invoice.
 *
 * @noinspection PhpUnused
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
            $vatRate = $vatRate[Tag::VatRate];
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

        $this->invoice[Tag::Customer][Tag::Invoice][Meta::CompletorStrategy . $this->getName()] = "tryVatRate($vatRate): $vatAmount";
        // If the vat totals are equal, the strategy worked.
        // We allow for a reasonable margin, as rounding errors may add up.
        return Number::floatsAreEqual($vatAmount, $this->vat2Divide, 0.04);
    }
}
