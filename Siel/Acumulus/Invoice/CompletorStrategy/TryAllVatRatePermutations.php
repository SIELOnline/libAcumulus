<?php
namespace Siel\Acumulus\Invoice\CompletorStrategy;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\CompletorStrategyBase;

/**
 * Class TryAllTaxRatePermutations implements a vat completor strategy by trying
 * all possible permutations of the vat rates for all possible vat types.
 *
 * Current known usages:
 * - ???
 */
class TryAllVatRatePermutations extends CompletorStrategyBase
{
    /**
     * @var int
     *   This strategy should be tried before the split strategy as that one will
     *   easily succeed.
     */
    static public $tryOrder = 25;

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

        // Try without and with a 0 tax rate (prepaid vouchers have 0 vat rate, so
        // discount lines may have 0 vat rate).
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
            if ($vatRate['vattype'] === $vatType) {
                $this->vatRates[] = $vatRate['vatrate'];
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
            foreach ($this->vatRates as $taxRate => $amount) {
                $permutation[$permutationIndex] = $taxRate;
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
        $this->completedLines = array();
        $vatAmount = 0.0;
        $i = 0;
        foreach ($this->lines2Complete as $line2Complete) {
            $vatAmount += $this->completeLine($line2Complete, $permutation[$i]);
            $i++;
        }

        // The strategy worked if the vat totals equals the vat to divide.
        return Number::floatsAreEqual($vatAmount, $this->vat2Divide);
    }
}
