<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Meta;

/**
 * The strategy lines completor class provides functionality to correct and
 * complete invoice lines before sending them to Acumulus.
 *
 * This class:
 * - Adds vat rates to invoice lines that need a strategy to compute their vat
 *   rates.
 */
class CompletorStrategyLines
{
    protected Config $config;
    protected Translator $translator;
    protected Invoice $invoice;
    protected Source $source;
    /**
     * @var int[]
     *   The list of possible vat types, initially filled with possible vat
     *   types based on client country, invoiceHasLineWithVat(), is_company(),
     *   and the EU vat setting.
     */
    protected array $possibleVatTypes;
    /** @var array[] */
    protected array $possibleVatRates;

    public function __construct(Config $config, Translator $translator)
    {
        $this->config = $config;
        $this->translator = $translator;
    }

    /**
     * Completes the invoice with default settings that do not depend on shop
     * specific data.
     *
     * @param Invoice $invoice
     *   The invoice to complete.
     * @param Source $source
     *   The source object for which this invoice was created.
     * @param int[] $possibleVatTypes
     * @param array[] $possibleVatRates
     */
    public function complete(Invoice $invoice, Source $source, array $possibleVatTypes, array $possibleVatRates): void
    {
        $this->invoice = $invoice;
        $this->source = $source;
        $this->possibleVatTypes = $possibleVatTypes;
        $this->possibleVatRates = $possibleVatRates;

        $this->completeStrategyLines();
    }

    /**
     * Complete all lines that need a vat divide strategy to compute correct
     * values.
     */
    protected function completeStrategyLines(): void
    {
        if ($this->invoiceHasStrategyLine()) {
            $this->invoice->metadataSet(Meta::CompletorStrategyInput . 'vat-rates', $this->possibleVatRates);

            $isFirst = true;
            $strategies = $this->getStrategyClasses();
            foreach ($strategies as $strategyClass) {
                /** @var CompletorStrategyBase $strategy */
                $strategy = new $strategyClass(
                    $this->config,
                    $this->translator,
                    $this->invoice,
                    $this->possibleVatTypes,
                    $this->possibleVatRates,
                    $this->source
                );
                if ($isFirst) {
                    $this->invoice->metadataSet(Meta::CompletorStrategyInput . 'vat-2-divide', $strategy->getVat2Divide());
                    $this->invoice->metadataSet(Meta::CompletorStrategyInput . 'vat-breakdown', $strategy->getVatBreakdown());
                    $isFirst = false;
                }
                if ($strategy->apply()) {
                    $this->replaceLinesCompleted($strategy->getReplacingLines(), $strategy->getName());
                    $this->invoice->metadataAdd(Meta::CompletorStrategyUsed, $strategy->getDescription(), true);
                    // Allow for partial solutions: a strategy may correct only some
                    // strategy lines and leave the rest up to other strategies.
                    if (!$this->invoiceHasStrategyLine()) {
                        break;
                    }
                }
            }
        }
    }

    /**
     * Returns whether the invoice has lines that are to be completed using a vat
     * divide strategy.
     */
    public function invoiceHasStrategyLine(): bool
    {
        foreach ($this->invoice->getLines() as $line) {
            if ($line->metadataGet(Meta::VatRateSource) === VatRateSource::Strategy) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns a list of strategy class names.
     *
     * @return string[]
     */
    protected function getStrategyClasses(): array
    {
        $result = [];

        // For now hardcoded, but this can be turned into a discovery.
        $namespace = '\Siel\Acumulus\Invoice\CompletorStrategy';
        $result[] = "$namespace\\ApplySameVatRate";
        $result[] = "$namespace\\SplitKnownDiscountLine";
        $result[] = "$namespace\\SplitLine";
        $result[] = "$namespace\\SplitNonMatchingLine";
        $result[] = "$namespace\\TryAllVatRatePermutations";

        usort($result, static function ($class1, $class2) {
            return $class1::$tryOrder - $class2::$tryOrder;
        });

        return $result;
    }

    /**
     * Replaces all completed strategy lines with the given completed lines.
     *
     * @param Line[][] $replacingLines
     *   An array of lines to replace the completed strategy lines with. The lines are
     *   grouped by the index of the line they are intended to replace.
     */
    protected function replaceLinesCompleted(array $replacingLines, string $strategyName): void
    {
        $lines = [];
        foreach ($this->invoice->getLines() as $index => $line) {
            if (!isset($replacingLines[$index])) {
                // Lines is not to be replaced: add the line itself.
                $lines[] = $line;
            } else {
                // Replace line with replacing lines.
                foreach ($replacingLines[$index] as $replacingLine) {
                    if ($replacingLine->metadataGet(Meta::VatRateSource) !== VatRateSource::Strategy) {
                        $replacingLine->metadataAdd(Meta::Warning, 'Vat rate source was not VatRateSource::Strategy');
                    }
                    $replacingLine->metadataSet(Meta::VatRateSource, VatRateSource::Strategy_Completed);
                    $replacingLine->metadataSet(Meta::CompletorStrategyUsed, $strategyName);
                    $lines[] = $replacingLine;
                }
            }
        }
        $this->invoice->replaceLines($lines);
    }
}
