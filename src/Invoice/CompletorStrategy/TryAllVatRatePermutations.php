<?php
namespace Siel\Acumulus\Invoice\CompletorStrategy;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\CompletorStrategyBase;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

/**
 * Class TryAllTaxRatePermutations implements a vat completor strategy by trying
 * all possible permutations of the possible vat rates on the lines to complete.
 *
 * Current known usages:
 * - ???
 */
class TryAllVatRatePermutations extends CompletorStrategyBase
{
    /**
     * This strategy should be tried last after lines that may be split and
     * could be split, have been split.
     *
     * @var int
     */
    static public $tryOrder = 50;

    /** @var float[] */
    protected $vatRates;

    /** @var int */
    protected $countLines;

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $this->countLines = count($this->lines2Complete);

        // Try without and with a 0 tax rate:
        // - Prepaid vouchers have 0 vat rate, so discount lines may have 0 vat rate.
        // - Shops can be configured to incorrectly not calculate tax over some costs.
        foreach (array(false, true) as $include0) {
            foreach ($this->possibleVatTypes as $vatType) {
                $this->setVatRates($vatType, $include0);
                if ($this->tryAllPermutations(array())) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Initializes the array of vat rates to use for this permutation.
     *
     * @param float $vatType
     * @param bool $include0
     */
    protected function setVatRates($vatType, $include0)
    {
        $this->vatRates = array();
        foreach ($this->possibleVatRates as $vatRate) {
            if ($vatRate[Tag::VatType] === $vatType) {
                $this->vatRates[] = $vatRate[Tag::VatRate];
            }
        }
        if ($include0) {
            $this->vatRates[] = 0.0;
        }
    }

    /**
     * @param array $permutation
     *
     * @return bool
     */
    protected function tryAllPermutations(array $permutation)
    {
        if (count($permutation) === $this->countLines) {
            // Try this (complete) permutation.
            return $this->try1Permutation($permutation);
        } else {
            // Complete this permutation recursively before we can try it.
            $permutationIndex = count($permutation);
            // Try all tax rates for the current line.
            foreach ($this->vatRates as $vatRate) {
                $permutation[$permutationIndex] = $vatRate;
                if ($this->tryAllPermutations($permutation)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param array $permutation
     *
     * @return bool
     */
    protected function try1Permutation(array $permutation)
    {
        $this->description = 'TryAllVatRatePermutations(' . implode(', ', $permutation) . ')';
        $this->replacingLines = array();
        $vatAmount = 0.0;
        $i = 0;
        foreach ($this->lines2Complete as $line2Complete) {
            $vatAmount += $this->completeLine($line2Complete, $permutation[$i]);
            $i++;
        }

        $this->invoice[Tag::Customer][Tag::Invoice][Meta::CompletorStrategy . $this->getName()] = sprintf("try1Permutation([%s]): %f", implode(', ', $permutation), $vatAmount);
        // The strategy worked if the vat totals equals the vat to divide.
        return Number::floatsAreEqual($vatAmount, $this->vat2Divide);
    }
}
