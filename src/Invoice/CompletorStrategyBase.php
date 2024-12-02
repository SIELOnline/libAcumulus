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
 *
 * @noinspection TypeUnsafeComparisonInspection
 * @noinspection PhpMissingStrictTypesDeclarationInspection
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Meta;

use function get_class;
use function sprintf;

/**
 * CompletorStrategyBase is the base class for all strategies that might
 * be able to complete invoice lines by applying a strategy to divide a
 * remaining vat amount over lines without a vat rate yet.
 */
abstract class CompletorStrategyBase
{
    protected Config $config;
    protected Translator $translator;
    /**
     * Indication of the order of execution of the strategy.
     */
    public static int $tryOrder;
    protected Invoice $invoice;
    /** @var array[] */
    protected array $possibleVatTypes;
    /** @var array[] */
    protected array $possibleVatRates;
    /**
     * @var Line[]
     *   The lines that are to be completed by the strategy.
     */
    protected array $lines2Complete;
    /**
     * @var Line[][]
     *   The lines that replace the $linesCompleted.
     *
     *   The replacing lines are grouped by the original index of the line they are
     *   replacing so that we know which lines to replace and with which lines to replace
     *   them (so we can keep the same order for the lines).
     */
    private array $replacingLines;
    private float $vat2Divide;
    protected float $invoiceAmount;
    protected string $description = 'Not yet set';
    /**
     * @var array[]
     *   An overview of the vat broken down into separate vat rates.
     *
     *   Each entry is keyed by íts vat rate (%.3f formatted to get correct
     *   comparisons on equality) and contains an array with the following
     *   information:
     *   - 'vatrate' => the vat rate,
     *   - 'vatamount' => vat amount on all lines having this vat rate.
     *   - 'amount' => amount (ex vat) of all lines having this vat rate,
     *   - 'count' => number of lines having this vat rate.
     */
    private array $vatBreakdown;
    protected Source $source;

    public function __construct(
        Config $config,
        Translator $translator,
        Invoice $invoice,
        array $possibleVatTypes,
        array $possibleVatRates,
        Source $source
    ) {
        $this->config = $config;
        $this->translator = $translator;
        $this->invoice = $invoice;
        $this->possibleVatTypes = $possibleVatTypes;
        $this->possibleVatRates = $possibleVatRates;
        $this->source = $source;
        $this->initAmounts();
        $this->initLines2Complete();
        $this->initVatBreakdown();
    }

    /**
     * Helper method to translate strings.
     *
     * @param string $key
     *  The key to get a translation for.
     *
     * @return string
     *   The translation for the given key or the key itself if no translation
     *   could be found.
     */
    protected function t(string $key): string
    {
        return $this->translator->get($key);
    }

    /**
     * Returns the non namespaced name of the current strategy.
     */
    public function getName(): string
    {
        $nsClass = get_class($this);
        return substr($nsClass, strrpos($nsClass, '\\') + 1);
    }

    /**
     * Returns the (parameterised) description of the latest tried strategy.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Returns the amount of vat to divide over the lines
     */
    public function getVat2Divide(): float
    {
        return $this->vat2Divide;
    }

    /**
     * Returns a breakdown of the vat per vat rate.
     *
     * @return array[]
     */
    public function getVatBreakdown(): array
    {
        return $this->vatBreakdown;
    }

    /**
     * Returns the lines that should replace the lines completed.
     *
     * Should only be called after success.
     *
     * @return Line[]
     */
    public function getReplacingLines(): array
    {
        return $this->replacingLines;
    }

    protected function clearReplacingLines(): void
    {
        $this->replacingLines = [];
    }

    protected function addReplacingLine(int $indexToReplace, Line $line): void
    {
        if (!isset($this->replacingLines[$indexToReplace])) {
            $this->replacingLines[$indexToReplace] = [];
        }
        $this->replacingLines[$indexToReplace][] = $line;
    }

    /**
     * Initializes the amount properties.
     *
     * to be able to calculate the amounts, at least 2 of the 3 meta amounts
     * 'meta-invoice-vatamount', 'meta-invoice-amountinc', or
     * 'meta-invoice-amount' must be known.
     */
    protected function initAmounts(): void
    {
        // The vat amount to divide over the non completed lines is the total vat
        // amount of the invoice minus all known vat amounts per line.
        /** @var \Siel\Acumulus\Invoice\Totals $totals */
        $totals = $this->invoice->metadataGet(Meta::Totals);
        $this->invoiceAmount = $totals->amountEx;
        $this->vat2Divide = $totals->amountVat;

        foreach ($this->invoice->getLines() as $line) {
            if ($line->metadataGet(Meta::VatRateSource) !== VatRateSource::Strategy) {
                // Deduct the vat amount from this line: if set, deduct it directly,
                // otherwise calculate the vat amount using the vat rate and unit price.
                if ($line->metadataExists(Meta::VatAmount)) {
                    $this->vat2Divide -= $line->metadataGet(Meta::VatAmount) * $line->quantity;
                } else {
                    $this->vat2Divide -= $this->isNoVat($line->vatRate)
                        ? 0.0
                        : ($line->vatRate / 100.0) * $line->unitPrice * $line->quantity;
                }
            }
        }
    }

    /**
     * Initializes $this->lines2Complete with all strategy lines.
     */
    protected function initLines2Complete(): void
    {
        $this->lines2Complete = [];
        foreach ($this->invoice->getLines() as $index => $line) {
            if ($line->metadataGet(Meta::VatRateSource) === VatRateSource::Strategy) {
                $this->lines2Complete[$index] = $line;
            }
        }
    }

    /**
     * Initializes $this->vatBreakdown with a breakdown of vat rates and amounts
     * occurring on the invoice (and not being strategy lines).
     */
    protected function initVatBreakdown(): void
    {
        $this->vatBreakdown = [];

        // Initialize by adding all possible vat rates.
        foreach ($this->possibleVatRates as $possibleVatRate) {
            $vatRate = sprintf('%.3f', $possibleVatRate[Fld::VatRate]);
            if (!isset($this->vatBreakdown[$vatRate])) {
                $this->vatBreakdown[$vatRate] = [
                    Fld::VatRate => (float) $vatRate,
                    Meta::VatAmount => 0.0,
                    'amount' => 0.0,
                    'count' => 0,
                ];
            }
        }

        // Add amounts and count for appearing vat rates.
        foreach ($this->invoice->getLines() as $line) {
            if ($line->metadataGet(Meta::VatRateSource) !== VatRateSource::Strategy && isset($line->vatRate)) {
                $amount = $line->unitPrice * $line->quantity;
                $vatAmount = $this->isNoVat($line->vatRate)
                    ? 0.0
                    : $line->vatRate / 100.0 * $amount;
                $vatRate = sprintf('%.3f', $line->vatRate);
                // Add amount to existing vat rate line or create a new line.
                if (isset($this->vatBreakdown[$vatRate])) {
                    $this->vatBreakdown[$vatRate][Meta::VatAmount] += $vatAmount;
                    $this->vatBreakdown[$vatRate]['amount'] += $amount;
                    $this->vatBreakdown[$vatRate]['count']++;
                }
            }
        }

        // Sort high to low.
        // @todo: filter non used vat rates (count === 0)?
        usort($this->vatBreakdown, static function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });
    }

    /**
     * Returns the minimum vat rate on the invoice.
     *
     * @return array
     *   A vat rate overview: array with keys 'vatrate', 'vatamount', 'amount',
     *   and 'count'.
     */
    protected function getVatBreakDownMinRate(): array
    {
        $result = [Fld::VatRate => 100.0];
        foreach ($this->getVatBreakdown() as $breakDown) {
            if ($breakDown[Fld::VatRate] < $result[Fld::VatRate]) {
                $result = $breakDown;
            }
        }
        return $result;
    }

    /**
     * Returns the maximum vat rate on the invoice.
     *
     * @return array
     *   A vat rate overview: array with keys 'vatrate', 'vatamount', 'amount',
     *   and 'count'.
     */
    protected function getVatBreakDownMaxRate(): array
    {
        $result = [Fld::VatRate => -1.0];
        foreach ($this->getVatBreakdown() as $breakDown) {
            if ($breakDown[Fld::VatRate] > $result[Fld::VatRate]) {
                $result = $breakDown;
            }
        }
        return $result;
    }

    /**
     * Returns the key component vat rate on the invoice (NL: hoofdbestanddeel).
     *
     * @return array
     *   A vat rate overview: array with keys 'vatrate', 'vatamount', 'amount',
     *   and 'count'.
     */
    protected function getVatBreakDownMaxAmount(): array
    {
        $result = ['amount' => -PHP_INT_MAX];
        foreach ($this->getVatBreakdown() as $breakDown) {
            if ($breakDown['amount'] > $result['amount']) {
                $result = $breakDown;
            }
        }
        return $result;
    }

    /**
     * Applies the strategy to see if it results in a valid solution.
     *
     * @return bool
     *   Success.
     */
    public function apply(): bool
    {
        $this->init();
        if ($this->checkPreconditions()) {
            return $this->execute();
        } else {
            $this->invoice->metadataAdd(Meta::CompletorStrategyPreconditionFailed, $this->getName(), true);
            return false;
        }
    }

    /**
     * Strategy initialization.
     */
    protected function init(): void
    {
        $this->clearReplacingLines();
    }

    /**
     * Some strategies can only be tried when some conditions are met, e.g. if
     * there's only 1 line to complete.
     *
     * These checks are extracted from execute() and should be placed in this
     * method instead.
     *
     * @return bool
     *   True if this strategy might be used, false otherwise.
     */
    protected function checkPreconditions(): bool
    {
        return true;
    }

    /**
     * Tries to apply the strategy.
     *
     * A strategy might be parameterised, most likely by 1 or more vat rates, and
     * thus involve multiple tries. The implementations of execute() should run
     * all possible permutations and return true as soon as 1 permutation is
     * successful.
     *
     * If the result is true, $this->$completedLines must contain the completed
     * lines that will replace the lines to complete.
     *
     * @return bool
     *   true the strategy has been applied successfully, false otherwise.
     */
    abstract protected function execute(): bool;

    /**
     * Completes a line by filling in the given vat rate and calculating other
     * possibly missing fields ('vatamount', 'unitprice').
     *
     * @param int $index
     *   The index of the original line2Complete in the set of lines for the invoice being
     *   completed.
     * @param Line $line2Complete
     *   (A clone of) The invoice line to complete. After it has been completed it is
     *   added to {@see $replacingLines}
     * @param float $vatRate
     *   The vat rate to add to the line.
     *
     * @return float
     *   The vat amount for the completed line.
     */
    protected function completeLine(int $index, Line $line2Complete, float $vatRate): float
    {
        if (!isset($line2Complete->quantity)) {
            $line2Complete->quantity = 1;
        }
        $line2Complete->vatRate = $vatRate;
        if (isset($line2Complete->unitPrice)) {
            $line2Complete->metadataSet(
                Meta::VatAmount,
                $this->isNoVat($line2Complete->vatRate)
                    ? 0.0
                    : ($line2Complete->vatRate / 100.0) * $line2Complete->unitPrice
            );
        } else { // $line2Complete->metadataExists(Meta::UnitPriceInc)
            $line2Complete->metadataSet(
                Meta::VatAmount,
                $this->isNoVat($line2Complete->vatRate)
                    ? 0.0
                    : ($line2Complete->vatRate / (100.0 + $line2Complete->vatRate)) * $line2Complete->metadataGet(Meta::UnitPriceInc)
            );
            $line2Complete->unitPrice = $line2Complete->metadataGet(Meta::UnitPriceInc) - $line2Complete->metadataGet(Meta::VatAmount);
        }
        $this->addReplacingLine($index, $line2Complete);
        return $line2Complete->metadataGet(Meta::VatAmount) * $line2Complete->quantity;
    }

    /**
     * Splits $amount (ex VAT) in 2 amounts, such that if the first amount is
     * taxed with the $lowVatRate and the 2nd amount is taxed with the
     * $highVatRate, the sum of the 2 vat amounts add up to the given vat amount.
     *
     * @param float $lowVatRate
     *   Percentage as a value between 0 and 100.
     * @param float $highVatRate
     *   Percentage as a value between 0 and 100.
     *
     * @return float[]
     *   A numerically indexed array with the amount for the low vat rate and
     *   the amount for the high vat rate.
     */
    protected function splitAmountOver2VatRates(
        float $amount,
        float $vatAmount,
        float $lowVatRate,
        float $highVatRate
    ): array {
        $lowVatRate /= 100;
        $highVatRate /= 100;
        $highAmount = ($vatAmount - $amount * $lowVatRate) / ($highVatRate - $lowVatRate);
        $lowAmount = $amount - $highAmount;
        return [$lowAmount, $highAmount];
    }

    protected function isNoVat(float $vatRate): bool
    {
        return Number::isZero($vatRate) || Number::floatsAreEqual($vatRate, Api::VatFree);
    }
}
