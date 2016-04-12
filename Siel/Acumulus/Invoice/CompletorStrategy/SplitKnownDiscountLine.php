<?php
namespace Siel\Acumulus\Invoice\CompletorStrategy;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Completor;
use Siel\Acumulus\Invoice\CompletorStrategyBase;

/**
 * Class SplitKnownDiscountLine implements a vat completor strategy by using the
 * 'meta-line-discount-amountinc' tags to split a discount line over several
 * lines with different vat rates as it may be considered as the total discount
 * over multiple products that may have different vat rates.
 *
 * Preconditions:
 * - lines2Complete contains 1 line that may be split.
 * - There should be other lines that have a 'meta-line-discount-amountinc' tag
 *   and an exact vat rate, and these amounts must add up to the amount of the
 *   line that is to be split.
 * - This strategy should be executed early as it is a sure and controlled win
 *   and can even be used as a partial solution.
 *
 * Strategy:
 * The amounts in the lines that have a 'meta-line-discount-amountinc' tag are
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
 */
class SplitKnownDiscountLine extends CompletorStrategyBase
{
    /**
     * This strategy should be tried first as it is a controlled but possibly
     * partial solution. Controlled in the sense that it will only be applied to
     * lines where it can and should be applied. So no chance of returning a
     * false positive.
     *
     * It should come before the SplitNonMatchingLine as this one depends on
     * more specific information being available and thus is more controlled
     * than that other split strategy.
     *
     * @var int
     */
    static public $tryOrder = 10;

    /** @var float */
    protected $knownDiscountAmountInc;

    /** @var float */
    protected $knownDiscountVatAmount;

    /** @var float[] */
    protected $discountsPerVatRate;

    /** @var array */
    protected $splitLine;

    /** @var int */
    protected $splitLineKey;

    /** @var int */
    protected $splitLineCount;

    /**
     * {@inheritdoc}
     */
    protected function init()
    {
        $this->splitLineCount = 0;
        foreach ($this->lines2Complete as $key => $line2Complete) {
            if (!empty($line2Complete['meta-strategy-split'])) {
                $this->splitLine = $line2Complete;
                $this->splitLineKey = $key;
                $this->splitLineCount++;
            }
        }

        if ($this->splitLineCount === 1) {
            $this->discountsPerVatRate = array();
            $this->knownDiscountAmountInc = 0.0;
            $this->knownDiscountVatAmount = 0.0;
            foreach ($this->invoice['customer']['invoice']['line'] as $line) {
                if (isset($line['meta-line-discount-amountinc']) && in_array($line['meta-vatrate-source'],
                    Completor::$CorrectVatRateSources)
                ) {
                    $this->knownDiscountAmountInc += $line['meta-line-discount-amountinc'];
                    $this->knownDiscountVatAmount += $line['meta-line-discount-amountinc'] / (100.0 + $line['vatrate']) * $line['vatrate'];
                    $vatRate = sprintf('%.3f', $line['vatrate']);
                    if (isset($this->discountsPerVatRate[$vatRate])) {
                        $this->discountsPerVatRate[$vatRate] += $line['meta-line-discount-amountinc'];
                    } else {
                        $this->discountsPerVatRate[$vatRate] = $line['meta-line-discount-amountinc'];
                    }
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function checkPreconditions()
    {
        $result = false;
        if ($this->splitLineCount === 1) {
            if ((isset($this->splitLine['unitprice']) && Number::floatsAreEqual($this->splitLine['unitprice'], $this->knownDiscountAmountInc - $this->knownDiscountVatAmount))
                || (isset($this->splitLine['unitpriceinc']) && Number::floatsAreEqual($this->splitLine['unitpriceinc'], $this->knownDiscountAmountInc))
            ) {
                $result = true;
            }
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $this->linesCompleted = array($this->splitLineKey);
        return $this->splitDiscountLine();
    }

    /**
     * @return bool
     */
    protected function splitDiscountLine()
    {
        $this->description = "SplitKnownDiscountLine({$this->knownDiscountAmountInc}, {$this->knownDiscountVatAmount})";
        $this->replacingLines = array();
        foreach ($this->discountsPerVatRate as $vatRate => $discountAmountInc) {
            $line = $this->splitLine;
            $line['product'] = "{$line['product']} ($vatRate%)";
            $line['unitpriceinc'] = $discountAmountInc;
            unset($line['unitprice']);
            $this->completeLine($line, $vatRate);
        }
        return true;
    }
}
