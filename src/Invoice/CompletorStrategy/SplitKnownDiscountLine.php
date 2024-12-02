<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice\CompletorStrategy;

use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Completor;
use Siel\Acumulus\Invoice\CompletorStrategyBase;
use Siel\Acumulus\Meta;

use function count;
use function sprintf;

/**
 * Class SplitKnownDiscountLine implements a vat completor strategy by using the
 * Meta::LineDiscountAmountInc tags to split a discount line over several
 * lines with different vat rates as it may be considered as the total discount
 * over multiple products that may have different vat rates.
 *
 * Preconditions:
 * - lines2Complete contains 1 line that may be split.
 * - There should be other (already completed) lines that have a
 *   Meta::LineDiscountAmountInc tag and an exact vat rate, and these amounts
 *   must add up to the amount of the line that is to be split.
 * - This strategy should be executed early as it is a sure and controlled win
 *   and can even be used as a partial solution.
 *
 * Strategy:
 * The amounts in the lines that have a Meta::LineDiscountAmountInc tag are
 * summed by their vat rates and these "discount amounts per vat rate" are used
 * to create the lines that replace the single discount line.
 *
 * Current usages:
 * - Magento
 * - PrestaShop but only if:
 *   - getOrderDetailTaxes() works correctly and thus if table order_detail_tax
 *     does have (valid) content.
 *   - if no discount on shipping and other fees as these do not end up in table
 *     order_detail_tax.
 *
 * @noinspection PhpUnused
 *   Instantiated via a variable containing the name.
 */
class SplitKnownDiscountLine extends CompletorStrategyBase
{
    /**
     * This strategy should be tried first as it is a controlled but possibly
     * partial solution. Controlled in the sense that it will only be applied to
     * invoice lines where it can and should be applied. So no chance of
     * returning a false positive.
     *
     * It should come before the SplitNonMatchingLine as this one depends on
     * more specific information being available and thus is more controlled
     * than that other split strategy.
     */
    public static int $tryOrder = 10;

    protected float $knownDiscountAmountInc;
    protected float $knownDiscountVatAmount;
    /** @var float[] */
    protected array $discountsPerVatRate;
    protected Line $splitLine;
    protected int $splitLineIndex;
    protected int $splitLineCount;

    protected function init(): void
    {
        parent::init();
        $this->splitLineCount = 0;
        foreach ($this->lines2Complete as $index => $line2Complete) {
            if ($line2Complete->metadataGet(Meta::StrategySplit)) {
                $this->splitLine = $line2Complete;
                $this->splitLineIndex = $index;
                $this->splitLineCount++;
            }
        }

        if ($this->splitLineCount === 1) {
            $this->discountsPerVatRate = [];
            $this->knownDiscountAmountInc = 0.0;
            $this->knownDiscountVatAmount = 0.0;
            foreach ($this->invoice->getLines() as $line) {
                if ($line->metadataExists(Meta::LineDiscountAmountInc)
                    && Completor::isCorrectVatRate($line->metadataGet(Meta::VatRateSource))
                ) {
                    $this->knownDiscountAmountInc += $line->metadataGet(Meta::LineDiscountAmountInc);
                    $this->knownDiscountVatAmount += $line->metadataGet(Meta::LineDiscountAmountInc)
                        / (100.0 + $line->vatRate) * $line->vatRate;
                    $vatRate = rtrim(rtrim(sprintf('%.3f', $line->vatRate), '0'), '.');
                    if (!isset($this->discountsPerVatRate[$vatRate])) {
                        $this->discountsPerVatRate[$vatRate] = 0.0;
                    }
                    $this->discountsPerVatRate[$vatRate] += $line->metadataGet(Meta::LineDiscountAmountInc);
                }
            }
        }
    }

    protected function checkPreconditions(): bool
    {
        return $this->splitLineCount === 1
            && ((isset($this->splitLine->unitPrice)
                    && Number::floatsAreEqual($this->splitLine->unitPrice, $this->knownDiscountAmountInc - $this->knownDiscountVatAmount))
                || ($this->splitLine->metadataExists(Meta::UnitPriceInc)
                    && Number::floatsAreEqual($this->splitLine->metadataGet(Meta::UnitPriceInc), $this->knownDiscountAmountInc))
            );
    }

    protected function execute(): bool
    {
        return $this->splitDiscountLine();
    }

    protected function splitDiscountLine(): bool
    {
        $this->description = "SplitKnownDiscountLine($this->knownDiscountAmountInc, $this->knownDiscountVatAmount)";
        foreach ($this->discountsPerVatRate as $vatRate => $discountAmountInc) {
            $line = clone $this->splitLine;
            if (count($this->discountsPerVatRate) > 1) {
                $line->product .= sprintf('(%s%% %s)', $vatRate, $this->t('vat'));
            }
            $line->metadataSet(Meta::UnitPriceInc, $discountAmountInc);
            unset($line->unitPrice);
            $this->completeLine($this->splitLineIndex, $line, (float) $vatRate);
        }
        return true;
    }
}
