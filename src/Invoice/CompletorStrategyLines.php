<?php
/**
 * Although we would like to use strict equality, i.e. including type equality,
 * unconditionally changing each comparison in this file will lead to problems
 * - API responses return each value as string, even if it is an int or float.
 * - The shop environment may be lax in its typing by, e.g. using strings for
 *   each value coming from the database.
 * - Our own config object is type aware, but, e.g, uses string for a vat class
 *   regardless the type for vat class ids as used by the shop itself.
 * So for now, we will ignore the warnings about non strictly typed comparisons
 * in this code, and we won't use strict_types=1.
 * @noinspection TypeUnsafeComparisonInspection
 * @noinspection PhpMissingStrictTypesDeclarationInspection
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

use function in_array;

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
    /** @var array[]|Invoice */
    protected $invoice;
    /** @var array[]|Line[] */
    protected array $invoiceLines;
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
     * @param array|Invoice $invoice
     *   The invoice to complete.
     * @param Source $source
     *   The source object for which this invoice was created.
     * @param int[] $possibleVatTypes
     * @param array[] $possibleVatRates
     *
     * @return array|Invoice
     *   The completed invoice.
     */
    public function complete($invoice, Source $source, array $possibleVatTypes, array $possibleVatRates)
    {
        $this->invoice = $invoice;
        $this->invoiceLines = &$this->invoice[Tag::Customer][Tag::Invoice][Tag::Line];
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
    protected function completeStrategyLines(): void
    {
        if ($this->invoiceHasStrategyLine()) {
            $this->invoice[Tag::Customer][Tag::Invoice][Meta::CompletorStrategyInput]['vat-rates'] = json_encode($this->possibleVatRates, Meta::JsonFlags);

            $isFirst = true;
            $strategies = $this->getStrategyClasses();
            foreach ($strategies as $strategyClass) {
                /** @var CompletorStrategyBase $strategy */
                $strategy = new $strategyClass($this->config, $this->translator, $this->invoice, $this->possibleVatTypes, $this->possibleVatRates, $this->source);
                if ($isFirst) {
                    $this->invoice[Tag::Customer][Tag::Invoice][Meta::CompletorStrategyInput]['vat-2-divide'] = $strategy->getVat2Divide();
                    $this->invoice[Tag::Customer][Tag::Invoice][Meta::CompletorStrategyInput]['vat-breakdown'] = json_encode($strategy->getVatBreakdown(), Meta::JsonFlags);
                    $isFirst = false;
                }
                if ($strategy->apply()) {
                    $this->replaceLinesCompleted($strategy->getLinesCompleted(), $strategy->getReplacingLines(), $strategy->getName());
                    if (empty($this->invoice[Tag::Customer][Tag::Invoice][Meta::CompletorStrategyUsed])) {
                        $this->invoice[Tag::Customer][Tag::Invoice][Meta::CompletorStrategyUsed] = $strategy->getDescription();
                    } else {
                        $this->invoice[Tag::Customer][Tag::Invoice][Meta::CompletorStrategyUsed] .= '; ' . $strategy->getDescription();
                    }
                    // Allow for partial solutions: a strategy may correct only
                    // some strategy lines and leave the rest up to other
                    // strategies.
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
     */
    public function invoiceHasStrategyLine(): bool
    {
        $result = false;
        foreach ($this->invoiceLines as $line) {
            if ($line[Meta::VatRateSource] === VatRateSource::Strategy) {
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

        usort($result, static function($class1, $class2) {
           return $class1::$tryOrder - $class2::$tryOrder;
        });

        return $result;
    }

    /**
     * Replaces all completed strategy lines with the given completed lines.
     *
     * @param int[] $linesCompleted
     * @param array[]|Line[] $completedLines
     *   An array of completed invoice lines to replace the strategy lines with.
     */
    protected function replaceLinesCompleted(array $linesCompleted, array $completedLines, string $strategyName): void
    {
        // Remove old strategy lines that are now completed.
        $lines = [];
        foreach ($this->invoice[Tag::Customer][Tag::Invoice][Tag::Line] as $key => $line) {
            if (!in_array($key, $linesCompleted, true)) {
                $lines[] = $line;
            }
        }

        // And merge in the new completed ones.
        foreach ($completedLines as &$completedLine) {
            if ($completedLine[Meta::VatRateSource] === VatRateSource::Strategy) {
                $completedLine[Meta::VatRateSource] = VatRateSource::Strategy_Completed;
                $completedLine[Meta::CompletorStrategyUsed] = $strategyName;
            }
        }
        $this->invoice[Tag::Customer][Tag::Invoice][Tag::Line] = array_merge($lines, $completedLines);
    }
}
