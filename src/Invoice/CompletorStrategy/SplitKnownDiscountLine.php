<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice\CompletorStrategy;

use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Completor;
use Siel\Acumulus\Invoice\CompletorStrategyBase;
use Siel\Acumulus\Meta;

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
    /** @var array|Line */
    protected $splitLine;
    protected int $splitLineKey;
    protected int $splitLineCount;

    /**
     * @noinspection PhpMissingParentCallCommonInspection  no-op parent.
     */
    protected function init(): void
    {
        $this->splitLineCount = 0;
        foreach ($this->lines2Complete as $key => $line2Complete) {
            if (!empty($line2Complete[Meta::StrategySplit])) {
                $this->splitLine = $line2Complete;
                $this->splitLineKey = $key;
                $this->splitLineCount++;
            }
        }

        if ($this->splitLineCount === 1) {
            $this->discountsPerVatRate = [];
            $this->knownDiscountAmountInc = 0.0;
            $this->knownDiscountVatAmount = 0.0;
            foreach ($this->invoice[Fld::Customer][Fld::Invoice][Fld::Line] as $line) {
                if (isset($line[Meta::LineDiscountAmountInc]) && Completor::isCorrectVatRate($line[Meta::VatRateSource])) {
                    $this->knownDiscountAmountInc += $line[Meta::LineDiscountAmountInc];
                    $this->knownDiscountVatAmount += $line[Meta::LineDiscountAmountInc] / (100.0 + $line[Fld::VatRate]) * $line[Fld::VatRate];
                    $vatRate = sprintf('%.3f', $line[Fld::VatRate]);
                    if (isset($this->discountsPerVatRate[$vatRate])) {
                        $this->discountsPerVatRate[$vatRate] += $line[Meta::LineDiscountAmountInc];
                    } else {
                        $this->discountsPerVatRate[$vatRate] = $line[Meta::LineDiscountAmountInc];
                    }
                }
            }
        }
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection  no-op parent.
     */
    protected function checkPreconditions(): bool
    {
        $result = false;
        if ($this->splitLineCount === 1) {
            if ((isset($this->splitLine[Fld::UnitPrice]) && Number::floatsAreEqual($this->splitLine[Fld::UnitPrice], $this->knownDiscountAmountInc - $this->knownDiscountVatAmount))
                || (isset($this->splitLine[Meta::UnitPriceInc]) && Number::floatsAreEqual($this->splitLine[Meta::UnitPriceInc], $this->knownDiscountAmountInc))
            ) {
                $result = true;
            }
        }
        return $result;
    }

    protected function execute(): bool
    {
        $this->linesCompleted = [$this->splitLineKey];
        return $this->splitDiscountLine();
    }

    protected function splitDiscountLine(): bool
    {
        $this->description = "SplitKnownDiscountLine($this->knownDiscountAmountInc, $this->knownDiscountVatAmount)";
        $this->replacingLines = [];
        foreach ($this->discountsPerVatRate as $vatRate => $discountAmountInc) {
            $line = $this->splitLine;
            if (count($this->discountsPerVatRate) > 1) {
                $vatName = $this->t('vat');
                $vatRateStripped = rtrim($vatRate, '.0');
                $line[Fld::Product] = "{$line[Fld::Product]} ($vatRateStripped% $vatName)";
            }
            $line[Meta::UnitPriceInc] = $discountAmountInc;
            unset($line[Fld::UnitPrice]);
            $this->completeLine($line, (float) $vatRate);
        }
        return true;
    }
}
