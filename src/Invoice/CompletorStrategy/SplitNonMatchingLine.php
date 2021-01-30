<?php
namespace Siel\Acumulus\Invoice\CompletorStrategy;

use Siel\Acumulus\Meta;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Invoice\CompletorStrategyBase;
use Siel\Acumulus\Tag;

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
 *   used as a partial solution for only some of the strategy lines.
 * - (*) In 1 case we had an order with 3 rates, 1 of them being 0 on a €0,01
 *   discount line. So, if the shop only sells VAT liable products, we actually
 *   look at the number of positive vat rates and ignore any 0 vat rate.
 *
 * Strategy:
 * Only lines that satisfy the following conditions are corrected:
 * - To prevent "correcting" errors that lead to this non matching VAT rate,
 *   only lines that are marked with the value meta-strategy-split are
 *   corrected.
 * - Each line2complete must have at least 2 of the values vatamount, unitprice,
 *   or unitpriceinc, meaning that it can be divided on its own!
 * - They have gone through the correction phase, but no matching VAT rate was
 *   found, so the value meta-vatrate-matches is set to 'none'.
 * These lines are split in such a way over the 2 allowed vat rates, that the
 * values in the completed lines add up to the values in the line to be split.
 *
 * Current (known) usages:
 * - PrestaShop (discount lines)
 *
 * @noinspection PhpUnused
 */
class SplitNonMatchingLine extends CompletorStrategyBase
{
    /**
     * Split strategies should be tried first, as they can deliver partial
     * solutions in a controlled way. Controlled in the sense that it will only
     * be applied to lines where it can and should be applied. So no chance of
     * returning a false positive.
     *
     * @var int
     */
    static public $tryOrder = 20;

    /** @var array */
    protected $minVatRate;

    /** @var array */
    protected $maxVatRate;

    /**
     * {@inheritdoc}
     */
    protected function init()
    {
        $this->linesCompleted = array();
    }

    /**
     * {@inheritdoc}
     */
    protected function checkPreconditions()
    {
        $result = count($this->vatBreakdown) === 2;
        if (!$result) {
            $shopSettings = $this->config->getShopSettings();
            if ($shopSettings['vatFreeProducts'] === Config::VatFreeProducts_No) {
                // If there are only 2 positive vat rates that will do as well.
                // See note above in class doc.
                $positiveVatRates = 0;
                foreach ($this->vatBreakdown as $vatRate => $vatInfo) {
                    if ((float)$vatRate > 0) {
                        $positiveVatRates++;
                    }
                }
                $result = $positiveVatRates === 2;
            }
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $this->minVatRate = $this->getVatBreakDownMinRate();
        $this->maxVatRate = $this->getVatBreakDownMaxRate();
        $this->description = sprintf('"SplitNonMatchingLine(%f, %f)', $this->minVatRate[Tag::VatRate], $this->maxVatRate[Tag::VatRate]);
        $result = false;
        foreach ($this->lines2Complete as $key => $line2Complete) {
            if (!empty($line2Complete[Meta::StrategySplit]) && isset($line2Complete[Meta::VatRateRangeMatches]) && empty($line2Complete[Meta::VatRateRangeMatches])) {
                // Line may be split and line does not have a matching vat rate.
                if ($this->splitNonMatchingLine($line2Complete)) {
                    $result = true;
                    $this->linesCompleted[] = $key;
                }
            }
        }
        return $result;
    }

    /**
     * @param array $line
     *
     * @return bool
     */
    protected function splitNonMatchingLine(array $line)
    {
        list($lowAmount, $highAmount) = $this->splitAmountOver2VatRates($line[Meta::LineAmount],
            $line[Meta::LineAmountInc] - $line[Meta::LineAmount],
            $this->minVatRate[Tag::VatRate],
            $this->maxVatRate[Tag::VatRate]);

        // Dividing was possible if both amounts have the same sign.
        if (($highAmount < -0.005 && $lowAmount < -0.005 && $line[Meta::LineAmount] < -0.005)
            || ($highAmount > 0.005 && $lowAmount > 0.005 && $line[Meta::LineAmount] > 0.005)
        ) {
            $splitLine = $line;
            $splitLine[Tag::Product] .= sprintf(' (%f%% %s)', $this->maxVatRate[Tag::VatRate], $this->t('vat'));
            $splitLine[Tag::UnitPrice] = $highAmount;
            unset($splitLine[Meta::UnitPriceInc]);
            $this->completeLine($splitLine, $this->maxVatRate[Tag::VatRate]);

            $splitLine = $line;
            $splitLine[Tag::Product] .= sprintf(' (%f%% %s)', $this->minVatRate[Tag::VatRate], $this->t('vat'));
            $splitLine[Tag::UnitPrice] = $lowAmount;
            unset($splitLine[Meta::UnitPriceInc]);
            $this->completeLine($splitLine, $this->minVatRate[Tag::VatRate]);
            return true;
        }
        return false;
    }
}
