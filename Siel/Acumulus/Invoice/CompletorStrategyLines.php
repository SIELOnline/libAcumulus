<?php
namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\TranslatorInterface;

/**
 * The strategy lines completor class provides functionality to correct and
 * complete invoice lines before sending them to Acumulus.
 *
 * This class:
 * - Adds vat rates to lines that need a strategy to compute their vat rates.
 *
 * @package Siel\Acumulus
 */
class CompletorStrategyLines
{
    /** @var \Siel\Acumulus\Helpers\TranslatorInterface */
    protected $translator;

    /** @var array[] */
    protected $invoice;

    /** @var array[] */
    protected $invoiceLines;

    /** @var Source */
    protected $source;

    /**
     * @var int[]
     *   The list of possible vat types, initially filled with possible vat types
     *   based on client country, invoiceHasLineWithVat(), is_company(), and the
     *   digital services setting. But then reduced by VAT rates we find on the
     *   order lines.
     */
    protected $possibleVatTypes;

    /** @var array[] */
    protected $possibleVatRates;

    /**
     * Constructor.
     *
     * @param \Siel\Acumulus\Helpers\TranslatorInterface $translator
     */
    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Completes the invoice with default settings that do not depend on shop
     * specific data.
     *
     * @param array $invoice
     *   The invoice to complete.
     * @param Source $source
     *   The source object for which this invoice was created.
     * @param int[] $possibleVatTypes
     * @param array[] $possibleVatRates
     *
     * @return array
     *   The completed invoice.
     */
    public function complete(array $invoice, Source $source, array $possibleVatTypes, array $possibleVatRates)
    {
        $this->invoice = $invoice;
        $this->invoiceLines = &$this->invoice['customer']['invoice']['line'];
        $this->source = $source;
        $this->possibleVatTypes = $possibleVatTypes;
        $this->possibleVatRates = $possibleVatRates;

        $this->completeStrategyLines();
        return $this->invoice;
    }

    /**
     * Complete all lines that need a vat divide strategy to compute correct
     * values.
     */
    protected function completeStrategyLines()
    {
        if ($this->invoiceHasStrategyLine()) {
            $input = array_reduce($this->possibleVatRates,
                function ($carry, $item) {
                    return $carry . (empty($carry) ? '' : ',') . '[' . $item['vatrate'] . '%,' . $item['vattype'] . ']';
                },
                '');
            $this->invoice['customer']['invoice']['meta-completor-strategy-input'] = "strategy input: vat rates($input)";

            $isFirst = true;
            $strategies = $this->getStrategyClasses();
            foreach ($strategies as $strategyClass) {
                /** @var CompletorStrategyBase $strategy */
                $strategy = new $strategyClass($this->translator, $this->invoice, $this->possibleVatTypes, $this->possibleVatRates, $this->source);
                if ($isFirst) {
                    $this->invoice['customer']['invoice']['meta-completor-strategy-input'] .= ', vat2Divide: ' . $strategy->getVat2Divide();
                    $isFirst = false;
                }
                if ($strategy->apply()) {
                    $this->replaceLinesCompleted($strategy->getLinesCompleted(), $strategy->getCompletedLines(), $strategy->getName());
                    if (empty($this->invoice['customer']['invoice']['meta-completor-strategy-used'])) {
                        $this->invoice['customer']['invoice']['meta-completor-strategy-used'] = $strategy->getDescription();
                    } else {
                        $this->invoice['customer']['invoice']['meta-completor-strategy-used'] .= '; ' . $strategy->getDescription();
                    }
                    // Allow for partial solutions: a strategy may correct only some of
                    // the strategy lines and leave the rest up to other strategies.
                    if (!$this->invoiceHasStrategyLine()) {
                        break;
                    }
                }
            }
        }
    }

    /**
     * Returns whether the invoice has lines that are to be completed using a tax
     * divide strategy.
     *
     * @return bool
     */
    protected function invoiceHasStrategyLine()
    {
        $result = false;
        foreach ($this->invoiceLines as $line) {
            if ($line['meta-vatrate-source'] === Creator::VatRateSource_Strategy) {
                $result = true;
                break;
            }
        }
        return $result;
    }

    /**
     * Returns a list of strategy class names.
     *
     * @return string[]
     */
    protected function getStrategyClasses()
    {
        $result = array();

        // For now hardcoded, but this can be turned into a discovery.
        $namespace = '\Siel\Acumulus\Invoice\CompletorStrategy';
        $result[] = "$namespace\\SplitNonMatchingLine";
        $result[] = "$namespace\\SplitKnownDiscountLine";
        $result[] = "$namespace\\ApplySameVatRate";
        $result[] = "$namespace\\TryAllVatRatePermutations";
        $result[] = "$namespace\\SplitLine";
        $result[] = "$namespace\\Fail";

        return $result;
    }

    /**
     * Replaces all completed strategy lines with the given completed lines.
     *
     * @param int[] $linesCompleted
     * @param array[] $completedLines
     *   An array of completed invoice lines to replace the strategy lines with.
     * @param string $strategyName
     */
    protected function replaceLinesCompleted(array $linesCompleted, array $completedLines, $strategyName)
    {
        // Remove old strategy lines that are now completed.
        $lines = array();
        foreach ($this->invoice['customer']['invoice']['line'] as $key => $line) {
            if (!in_array($key, $linesCompleted)) {
                $lines[] = $line;
            }
        }

        // And merge in the new completed ones.
        foreach ($completedLines as &$completedLine) {
            if ($completedLine['meta-vatrate-source'] === Creator::VatRateSource_Strategy) {
                $completedLine['meta-vatrate-source'] = Completor::VatRateSource_Strategy_Completed;
                $completedLine['meta-strategy-used'] = $strategyName;
            }
        }
        $this->invoice['customer']['invoice']['line'] = array_merge($lines, $completedLines);
    }
}
