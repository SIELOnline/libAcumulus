<?php
namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

/**
 * CompletorStrategyBase is the base class for all strategies that might
 * be able to complete invoice lines by applying a strategy to divide a
 * remaining vat amount over lines without a vat rate yet.
 */
abstract class CompletorStrategyBase
{
    /** @var \Siel\Acumulus\Config\Config */
    protected $config;

    /** @var \Siel\Acumulus\Helpers\Translator */
    protected $translator;

    /** @var int Indication of the order of execution of the strategy. */
    public static $tryOrder = 50;

    /**
     * The invoice according to the Acumulus API definition.
     *
     * @var array[]
     */
    protected $invoice;

    /** @var array[] */
    protected $possibleVatTypes;

    /** @var array[] */
    protected $possibleVatRates;

    /**
     * The indices of the completed lines. As a line2complete may be split over
     * multiple new lines we must store the indices separately.
     *
     * @var int[]
     */
    protected $linesCompleted;

    /**
     * The lines that are to be completed by the strategy.
     *
     * @var array[]
     */
    protected $lines2Complete;

    /**
     * The lines that replace (some of) the $lines2Complete.
     *
     * $linesCompleted indicates which lines in $lines2Complete are completed
     * and are to be replaced by the $replacingLines.
     *
     * @var array[]
     */
    protected $replacingLines;

    /** @var float */
    protected $vat2Divide;

    /** @var float */
    protected $vatAmount;

    /** @var float */
    protected $invoiceAmount;

    /** @var string */
    protected $description = 'Not yet set';

    /**
     * An overview of the vat broken down into separate vat rates.
     *
     * Each entry is keyed by íts vat rate (%.3f formatted to get correct
     * comparisons on equality) and contains an array with the following
     * information:
     * - 'vatrate' => the vat rate,
     * - 'vatamount' => vat amount on all lines having this vat rate.
     * - 'amount' => amount (ex vat) of all lines having this vat rate,
     * - 'count' => number of lines having this vat rate.
     *
     * @var array[]
     */
    protected $vatBreakdown;

    /** @var \Siel\Acumulus\Invoice\Source */
    protected $source;

    public function __construct(
        Config $config,
        Translator $translator,
        array $invoice,
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
     * returns the amount of vat to divide over the lines
     */
    public function getVat2Divide(): float
    {
        return $this->vat2Divide;
    }

    /**
     * Returns a breakdown of the vat per vat rate.
     *
     * @returns array[]
     */
    public function getVatBreakdown(): array
    {
        return $this->vatBreakdown;
    }

    /**
     * Returns the keys of the lines that are completed (and thus should be
     * replaced by the replacing lines).
     *
     * Should only be called after success.
     *
     * @return int[]
     */
    public function getLinesCompleted(): array
    {
        return $this->linesCompleted;
    }

    /**
     * Returns the lines that should replace the lines completed.
     *
     * Should only be called after success.
     *
     * @return array[]
     */
    public function getReplacingLines(): array
    {
        return $this->replacingLines;
    }

    /**
     * Initializes the amount properties.
     *
     * to be able to calculate the amounts, at least 2 of the 3 meta amounts
     * 'meta-invoice-vatamount', 'meta-invoice-amountinc', or
     * 'meta-invoice-amount' must be known.
     */
    protected function initAmounts()
    {
        $invoicePart = &$this->invoice[Tag::Customer][Tag::Invoice];
        $this->vatAmount = $invoicePart[Meta::InvoiceVatAmount] ?? $invoicePart[Meta::InvoiceAmountInc] - $invoicePart[Meta::InvoiceAmount];
        $this->invoiceAmount = $invoicePart[Meta::InvoiceAmount] ?? $invoicePart[Meta::InvoiceAmountInc] - $invoicePart[Meta::InvoiceVatAmount];

        // The vat amount to divide over the non completed lines is the total vat
        // amount of the invoice minus all known vat amounts per line.
        $this->vat2Divide = (float) $this->vatAmount;
        foreach ($invoicePart[Tag::Line] as $line) {
            if ($line[Meta::VatRateSource] !== Creator::VatRateSource_Strategy) {
                // Deduct the vat amount from this line: if set, deduct it directly,
                // otherwise calculate the vat amount using the vat rate and unit price.
                if (isset($line[Meta::VatAmount])) {
                    $this->vat2Divide -= $line[Meta::VatAmount] * $line[Tag::Quantity];
                } else {
                    $this->vat2Divide -= $this->isNoVat($line[Tag::VatRate])
                        ? 0.0
                        : ($line[Tag::VatRate] / 100.0) * $line[Tag::UnitPrice] * $line[Tag::Quantity];
                }
            }
        }
    }

    /**
     * Initializes $this->lines2Complete with all strategy lines.
     */
    protected function initLines2Complete()
    {
        $this->linesCompleted = [];
        $this->lines2Complete = [];
        foreach ($this->invoice[Tag::Customer][Tag::Invoice][Tag::Line] as $key => $line) {
            if ($line[Meta::VatRateSource] === Creator::VatRateSource_Strategy) {
                $this->linesCompleted[] = $key;
                $this->lines2Complete[$key] = $line;
            }
        }
    }

    /**
     * Initializes $this->vatBreakdown with a breakdown of vat rates and amounts
     * occurring on the invoice (and not being strategy lines).
     */
    protected function initVatBreakdown()
    {
        $this->vatBreakdown = [];
        foreach ($this->invoice[Tag::Customer][Tag::Invoice][Tag::Line] as $line) {
            if ($line[Meta::VatRateSource] !== Creator::VatRateSource_Strategy && isset($line[Tag::VatRate])) {
                $amount = $line[Tag::UnitPrice] * $line[Tag::Quantity];
                $vatAmount = $this->isNoVat($line[Tag::VatRate])
                    ? 0.0
                    : $line[Tag::VatRate] / 100.0 * $amount;
                $vatRate = sprintf('%.3f', $line[Tag::VatRate]);
                // Add amount to existing vat rate line or create a new line.
                if (isset($this->vatBreakdown[$vatRate])) {
                    $breakdown = &$this->vatBreakdown[$vatRate];
                    $breakdown[Meta::VatAmount] += $vatAmount;
                    $breakdown['amount'] += $amount;
                    $breakdown['count']++;
                } else {
                    $this->vatBreakdown[$vatRate] = [
                        Tag::VatRate => (float) $vatRate,
                        Meta::VatAmount => $vatAmount,
                        'amount' => $amount,
                        'count' => 1,
                    ];
                }
            }
        }
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
        $result = [Tag::VatRate => PHP_INT_MAX];
        foreach ($this->vatBreakdown as $breakDown) {
            if ($breakDown[Tag::VatRate] < $result[Tag::VatRate]) {
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
        $result = [Tag::VatRate => -PHP_INT_MAX];
        foreach ($this->vatBreakdown as $breakDown) {
            if ($breakDown[Tag::VatRate] > $result[Tag::VatRate]) {
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
        foreach ($this->vatBreakdown as $breakDown) {
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
        $this->replacingLines = [];
        $this->init();
        if ($this->checkPreconditions()) {
            return $this->execute();
        } else {
            if (!empty($this->invoice[Tag::Customer][Tag::Invoice][Meta::CompletorStrategyPreconditionFailed])) {
                $this->invoice[Tag::Customer][Tag::Invoice][Meta::CompletorStrategyPreconditionFailed] .= ', ';
            } else {
                $this->invoice[Tag::Customer][Tag::Invoice][Meta::CompletorStrategyPreconditionFailed] = '';
            }
            $this->invoice[Tag::Customer][Tag::Invoice][Meta::CompletorStrategyPreconditionFailed] .= $this->getName();
            return false;
        }
    }

    /**
     * Strategy dependent initialization.
     *
     * This base implementation does nothing.
     */
    protected function init()
    {
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
     * @param array $line2Complete
     *   The invoice line to complete. After it has been completed it is added
     *   to {@see $replacingLines}
     * @param float $vatRate
     *   The vat rate to add to the line.
     *
     * @return float
     *   The vat amount for the completed line.
     */
    protected function completeLine(array $line2Complete, float $vatRate): float
    {
        if (!isset($line2Complete[Tag::Quantity])) {
            $line2Complete[Tag::Quantity] = 1;
        }
        $line2Complete[Tag::VatRate] = $vatRate;
        if (isset($line2Complete[Tag::UnitPrice])) {
            $line2Complete[Meta::VatAmount] = $this->isNoVat($line2Complete[Tag::VatRate])
                ? 0.0
                : ($line2Complete[Tag::VatRate] / 100.0) * $line2Complete[Tag::UnitPrice];
        } else { // isset($line2Complete[Meta::UnitPriceInc])
            $line2Complete[Meta::VatAmount] = $this->isNoVat($line2Complete[Tag::VatRate])
                ? 0.0
                : ($line2Complete[Tag::VatRate] / (100.0 + $line2Complete[Tag::VatRate])) * $line2Complete[Meta::UnitPriceInc];
            $line2Complete[Tag::UnitPrice] = $line2Complete[Meta::UnitPriceInc] - $line2Complete[Meta::VatAmount];
        }
        $this->replacingLines[] = $line2Complete;
        return $line2Complete[Meta::VatAmount] * $line2Complete[Tag::Quantity];
    }

    /**
     * Splits $amount (ex VAT) in 2 amounts, such that if the first amount is
     * taxed with the $lowVatRate and the 2nd amount is taxed with the
     * $highVatRate, the sum of the 2 vat amounts add up to the given vat amount.
     *
     * @param float $amount
     * @param float $vatAmount
     * @param float $lowVatRate
     *   Percentage between 0 and 100.
     * @param float $highVatRate
     *   Percentage between 0 and 100.
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
