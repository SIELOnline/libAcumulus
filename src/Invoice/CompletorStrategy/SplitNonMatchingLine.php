<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice\CompletorStrategy;

use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Invoice\CompletorStrategyBase;

use function count;
use function sprintf;

/**
 * Class SplitNonMatchingLine implements a vat completor strategy by recognizing
 * that a discount line (or ultra-correct shipping line) that does not match an
 * allowed vat rate may have to be split over the vat rates that appear in the
 * invoice. This because the discount can have been divided over multiple
 * products that have different vat rates.
 *
 * Preconditions:
 * - Exactly 2 VAT rates must be used in other lines (*).
 * - lines2Complete may contain multiple lines that may be split: tag
 *   meta-strategy-split is set to true.
 *
 * Note:
 * - This strategy should be executed early as it is an almost sure win and is
 *   used as a partial solution for only some strategy lines.
 * - (*) In 1 case we had an order with 3 rates, 1 of them being 0 on a €0,01
 *   discount line. So, if the shop only sells VAT liable products, we actually
 *   look at the number of positive vat rates and ignore any 0 vat rate.
 *
 * Strategy:
 * Only lines that satisfy the following conditions are corrected:
 * - To prevent "correcting" errors that lead to this non-matching VAT rate,
 *   only lines that are marked with the value meta-strategy-split are
 *   corrected.
 * - Each line2Complete must have at least 2 of the values 'vatAmount',
 *   'unitPrice', or 'unitPriceInc', meaning that it can be divided on its own!
 * - They have gone through the correction phase, but no matching VAT rate was
 *   found, so the value 'meta-vatrate-matches' is set to 'none'.
 * These lines are split in such a way over the 2 allowed vat rates, that the
 * values in the completed lines add up to the values in the line to be split.
 *
 * Current (known) usages:
 * - PrestaShop (discount lines)
 *
 * @noinspection PhpUnused
 *   Instantiated via a variable containing the name.
 */
class SplitNonMatchingLine extends CompletorStrategyBase
{
    /**
     * Split strategies should be tried first, as they can deliver partial
     * solutions in a controlled way. Controlled in the sense that it will only
     * be applied to invoice lines where it can and should be applied. So no
     * chance of returning a false positive.
     */
    public static int $tryOrder = 20;

    protected array $minVatRate;
    protected array $maxVatRate;

    protected function checkPreconditions(): bool
    {
        $result = count($this->getVatBreakdown()) === 2;
        if (!$result) {
            $shopSettings = $this->config->getShopSettings();
            if ($shopSettings['vatFreeClass'] === Config::VatClass_NotApplicable) {
                // If there are only 2 positive vat rates that will do as well.
                // See note above in class doc.
                $positiveVatRates = 0;
                foreach ($this->getVatBreakdown() as $vatRate => $vatInfo) {
                    if ((float) $vatRate > 0) {
                        $positiveVatRates++;
                    }
                }
                $result = $positiveVatRates === 2;
            }
        }
        return $result;
    }

    protected function execute(): bool
    {
        $this->minVatRate = $this->getVatBreakDownMinRate();
        $this->maxVatRate = $this->getVatBreakDownMaxRate();
        $this->description = sprintf('"SplitNonMatchingLine(%f, %f)', $this->minVatRate[Fld::VatRate], $this->maxVatRate[Fld::VatRate]);
        $result = false;
        foreach ($this->lines2Complete as $index => $line2Complete) {
            // Line may be split and line does not have a matching vat rate.
            if ($line2Complete->metadataGet(Meta::StrategySplit)
                && $line2Complete->metadataExists(Meta::VatRateRangeMatches)
                && empty($line2Complete->metadataGet(Meta::VatRateRangeMatches))
                && $this->splitNonMatchingLine($index, $line2Complete)
            ) {
                $result = true;
            }
        }
        return $result;
    }

    protected function splitNonMatchingLine(int $index, Line $line): bool
    {
        [$lowAmount, $highAmount] = $this->splitAmountOver2VatRates(
            $line->metadataGet(Meta::LineAmount),
            $line->metadataGet(Meta::LineAmountInc) - $line->metadataGet(Meta::LineAmount),
            $this->minVatRate[Fld::VatRate],
            $this->maxVatRate[Fld::VatRate]
        );

        // Dividing was possible if both amounts have the same sign.
        if (($highAmount < -0.005 && $lowAmount < -0.005 && $line->metadataGet(Meta::LineAmount) < -0.005)
            || ($highAmount > 0.005 && $lowAmount > 0.005 && $line->metadataGet(Meta::LineAmount) > 0.005)
        ) {
            $splitLine = clone $line;
            $splitLine->product .= sprintf(' (%f%% %s)', $this->maxVatRate[Fld::VatRate], $this->t('vat'));
            $splitLine->unitPrice = $highAmount;
            $splitLine->metadataRemove(Meta::UnitPriceInc);
            $this->completeLine($index, $splitLine, $this->maxVatRate[Fld::VatRate]);

            $splitLine = clone $line;
            $splitLine->product .= sprintf(' (%f%% %s)', $this->minVatRate[Fld::VatRate], $this->t('vat'));
            $splitLine->unitPrice = $lowAmount;
            $splitLine->metadataRemove(Meta::UnitPriceInc);
            $this->completeLine($index, $splitLine, $this->minVatRate[Fld::VatRate]);
            return true;
        }
        return false;
    }
}
