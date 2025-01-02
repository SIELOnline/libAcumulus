<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

use DateTimeImmutable;
use DateTimeInterface;
use Siel\Acumulus\Api;
use Siel\Acumulus\Completors\CompletorTaskInterface;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Countries;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Meta;
use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\Helpers\Severity;

use function array_key_exists;
use function count;
use function in_array;
use function is_float;
use function is_scalar;
use function sprintf;

/**
 * The invoice completor class provides functionality to correct and complete
 * invoices before sending them to Acumulus.
 *
 * This class:
 * - Adds line totals. These are compared with what the shop advertises as order
 *   totals to see if an amount might be missing.
 * - Adds the vat type based on inspection of the completed invoice.
 * - Removes an empty shipping line if set to do so in the config.
 *
 * - Calls the invoice line completor.
 * - Calls the strategy line completor.
 *
 * @noinspection PhpLackOfCohesionInspection
 */
class Completor
{
    private const EuSales_Unknown = 0;
    private const EuSales_Safe = 1;
    private const EuSales_Warning = 2;
    private const EuSales_WillReach = 3;
    private const EuSales_Passed = 4;

    /**
     * @var array
     *   A list of vat rate sources that indicate that the vat rate can be
     *   considered correct.
     */
    protected static array $CorrectVatRateSources = [
        VatRateSource::Exact,
        VatRateSource::Exact0,
        VatRateSource::Creator_Lookup,
        VatRateSource::Completor_Range,
        VatRateSource::Completor_Lookup,
        VatRateSource::Completor_Range_Lookup,
        VatRateSource::Completor_Range_Lookup_Foreign,
        VatRateSource::Completor_Max_Appearing,
        VatRateSource::Strategy_Completed,
        VatRateSource::Copied_From_Children,
        VatRateSource::Copied_From_Parent,
    ];

    /**
     * @var int[]
     *   A list of vat types that allow vat free vat rates.
     */
    protected static array $vatTypesAllowingVatFree = [Api::VatType_National, Api::VatType_MarginScheme];
    /**
     * @var int[]
     *   A list of vat types that do not charge vat at all.
     */
    protected static array $zeroVatVatTypes = [Api::VatType_NationalReversed, Api::VatType_EuReversed, Api::VatType_RestOfWorld];
    protected Config $config;
    protected Translator $translator;
    protected Log $log;
    protected Acumulus $acumulusApiClient;
    protected Countries $countries;
    protected InvoiceAddResult $result;
    protected Invoice $invoice;
    protected Source $source;
    /**
     * @var int[]
     *   The list of possible vat types, initially filled with possible vat
     *   types based on client country, invoiceHasLineWithVat(), is_company(),
     *   and the EU vat setting. But then reduced by VAT rates we find on
     *   the order lines.
     */
    protected array $possibleVatTypes;
    /**
     * @var array[]
     *   The list of possible vat rates, based on the possible vat types and
     *   extended with the zero rates (0 and vat-free) if they might be
     *   applicable.
     */
    protected array $possibleVatRates;
    protected CompletorInvoiceLines $LineCompletor;
    protected CompletorStrategyLines $strategyLineCompletor;
    /** @var (string|int)[][] */
    protected array $lineTotalsStates;
    /** @var array[] */
    protected array $vatRatesCache = [];

    public function __construct(
        CompletorInvoiceLines $completorInvoiceLines,
        CompletorStrategyLines $completorStrategyLines,
        Countries $countries,
        Acumulus $acumulusApiClient,
        Config $config,
        Translator $translator,
        Log $log
    ) {
        $this->config = $config;
        $this->log = $log;

        $this->translator = $translator;
        $this->translator->add(new Translations());
        $this->countries = $countries;
        $this->acumulusApiClient = $acumulusApiClient;
        $this->LineCompletor = $completorInvoiceLines;
        $this->LineCompletor->setCompletor($this);
        $this->strategyLineCompletor = $completorStrategyLines;
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

    public function getCompletorTask(string $dataType, string $task): CompletorTaskInterface
    {
        return Container::getContainer()->getCompletorTask($dataType, $task);
    }

    /**
     * Completes the invoice with default settings that do not depend on shop
     * specific data.
     *
     * @param Invoice $invoice
     *   The invoice to complete.
     * @param Source $source
     *   The source object for which this invoice was created.
     * @param \Siel\Acumulus\Invoice\InvoiceAddResult $result
     *   A Result object where local errors and warnings can be added.
     */
    public function complete(Invoice $invoice, Source $source, InvoiceAddResult $result): void
    {
        $this->invoice = $invoice;
        $this->source = $source;
        $this->result = $result;

        // Complete lines as far as they can be completed on their own.
        $this->initPossibleVatTypes();
        $this->initPossibleVatRates();
        $this->convertToEuro();
        $this->LineCompletor->complete($invoice, $this->possibleVatTypes, $this->possibleVatRates);

        $this->checkMissingAmount();

        // Complete strategy lines: those lines that have to be completed based
        // on the whole invoice.
        $this->strategyLineCompletor->complete(
            $invoice,
            $this->source,
            $this->possibleVatTypes,
            $this->possibleVatRates
        );
        // Check if the strategy phase was successful.
        if ($this->strategyLineCompletor->invoiceHasStrategyLine()) {
            // We did not manage to correct all strategy lines: warn and set the
            // invoice to concept.
            $this->changeInvoiceToConcept($invoice, 'message_warning_strategies_failed', 808);
        }

        // Determine the VAT type for the invoice and warn if multiple vat types
        // are possible.
        $this->completeVatType();
        // Correct NULL vat lines.
        $this->correctNullVatLines();
        // Correct 0% and vat free rates where applicable.
        $this->correctNoVatLines();
        // Determine the vat type id for the customer
        $this->completeVatTypeId();

        // If the invoice has margin products, all invoice lines have to follow
        // the margin scheme, i.e. have a cost price and a unit price incl. VAT.
        $this->correctMarginInvoice();

        // Completes the invoice with settings or behaviour that might depend on
        // the fact that the invoice lines have been completed.
        $this->removeEmptyShipping();
        $this->checkEuCommerceThreshold();
    }

    /**
     * Initializes the list of possible vat types for this invoice.
     *
     * The list of possible vat types depends on:
     * - Whether the vat type already has been set.
     * - Whether there is at least 1 line with a cost price.
     * - The country of the client.
     * - Whether the client is a company.
     * - The shop settings (using EU vat or selling vat free products).
     * - Optionally, the date of the invoice.
     *
     * See also: {@link https://www.siel.nl/acumulus/handleiding/?a=facturen_naar_het_buitenland}.
     */
    protected function initPossibleVatTypes(): void
    {
        $possibleVatTypes = [];
        $shopSettings = $this->config->getShopSettings();
        $margin = $shopSettings['marginProducts'];
        $nature = $this->getNature();
        $euVat = $shopSettings['euVat'];

        if (!empty($this->invoice->vatType)) {
            // If shop specific code or an event handler has already set the vat
            // type, we obey so.
            $possibleVatTypes[] = $this->invoice->vatType;
        } else {
            if ($this->isNl()) {
                $possibleVatTypes[] = Api::VatType_National;
                // Can it be national reversed VAT: not really supported but
                // possible. Note that reversed vat should not be used with vat
                // free items. @todo: thus if we know that only vat free is used...
                if ($this->isCompany()) {
                    $possibleVatTypes[] = Api::VatType_NationalReversed;
                }
            } elseif ($this->isEu()) {
                // Can it be Dutch vat?
                if ($euVat !== Config::EuVat_Yes) {
                    $possibleVatTypes[] = Api::VatType_National;
                }
                // Can it be EU vat?
                if ($euVat !== Config::EuVat_No) {
                    $possibleVatTypes[] = Api::VatType_EuVat;
                }
                // Can it be EU reversed VAT? Note that reversed vat should not
                // be used with vat free items.
                if ($this->isCompany()) {
                    $possibleVatTypes[] = Api::VatType_EuReversed;
                }
            } elseif ($this->isUk()) {
                // Handle UK and Northern Ireland separately:
                // https://www.belastingdienst.nl/wps/wcm/connect/nl/btw/content/wat-betekent-brexit-voor-de-btw
                // https://www.taxence.nl/nieuws/fiscaal-nieuws/brexit-en-btw-3/
                if ($this->isNorthernIreland()) {
                    if (($nature & Config::Nature_Products) !== 0) {
                        // Nature = Products => treat Northern Ireland as EU.
                        /** @noinspection NullPointerExceptionInspection will be set at this point */
                        $this->invoice->getCustomer()->getFiscalAddress()->countryCode = 'XI';
                        // @nth: remove duplication (with case isEu()).
                        // Can it be Dutch vat?
                        if ($euVat !== Config::EuVat_Yes) {
                            $possibleVatTypes[] = Api::VatType_National;
                        }
                        // Can it be EU vat?
                        if ($euVat !== Config::EuVat_No) {
                            $possibleVatTypes[] = Api::VatType_EuVat;
                        }
                        // Can it be EU reversed VAT? Note that reversed vat
                        // should not be used with vat free items.
                        if ($this->isCompany()) {
                            $possibleVatTypes[] = Api::VatType_EuReversed;
                        }
                    }
                } elseif (($nature & Config::Nature_Products) !== 0) {
                    // Nature = Products =>
                    // - Up to 135 GBP: seller pays VAT (vat type = 7).
                    // - Above 135 GBP: buyer pays VAT, for seller it becomes
                    //   vat type = 4, unless ..., see below at outsideEu.
                    //   However, seller may decide to pay VAT on behalf of
                    //   buyer: vat type = 7.
                    $possibleVatTypes[] = Api::VatType_OtherForeignVat;
                    $possibleVatTypes[] = Api::VatType_National;
                    $possibleVatTypes[] = Api::VatType_RestOfWorld;
                }
                if (($nature & Config::Nature_Services) !== 0) {
                    // Nature = Services => treat Northern Ireland as UK, i.e.
                    // as rest of world.
                    // Services should use vat type = 1 with vat free.
                    $possibleVatTypes[] = Api::VatType_National;
                }
            } elseif ($this->isOutsideEu()) {
                // Can it be national vat? Services should use vat type = 1 with
                // vat free. @todo: thus if we know that only vat free is used...
                if (($nature & Config::Nature_Services) !== 0) {
                    $possibleVatTypes[] = Api::VatType_National;
                }
                // Can it be rest of world? Goods should use vat type = 4 with
                // 0% vat unless you can't or don't want to prove that the goods
                // will leave the EU in which case we should use vat type = 1
                // with normal vat, see:
                // https://www.belastingdienst.nl/rekenhulpen/leveren_van_goederen_naar_het_buitenland/
                if (($nature & Config::Nature_Products) !== 0) {
                    $possibleVatTypes[] = Api::VatType_National;
                    $possibleVatTypes[] = Api::VatType_RestOfWorld;
                }
            }

            // Can it be a margin invoice?
            if ($margin !== Config::MarginProducts_No) {
                $possibleVatTypes[] = Api::VatType_MarginScheme;
            }
        }
        $this->possibleVatTypes = array_values(array_unique($possibleVatTypes, SORT_NUMERIC));
    }

    /**
     * Initializes the list of possible vat rates.
     *
     * The possible vat rates depend on:
     * - the possible vat types.
     * - optionally, the date of the invoice.
     * - optionally, the country of the client.
     * - optionally, the nature of the articles sold.
     *
     * On finishing, $this->possibleVatRates will contain an array with possible
     * vat rates. A vat rate being an array with fields 'vatRate' and 'vatType'.
     * This is done so to be able to determine to which vat type(s) a vat rate
     * belongs.
     *
     * @noinspection PhpFunctionCyclomaticComplexityInspection
     */
    protected function initPossibleVatRates(): void
    {
        $possibleVatRates = [];

        $shopSettings = $this->config->getShopSettings();
        $vatFreeClass = $shopSettings['vatFreeClass'];
        $zeroVatClass = $shopSettings['zeroVatClass'];
        $nature = $this->getNature();

        foreach ($this->possibleVatTypes as $vatType) {
            switch ($vatType) {
                case Api::VatType_National:
                case Api::VatType_MarginScheme:
                    $countryVatInfos = $this->getVatRatesByCountryAndInvoiceDate('nl');
                    $vatTypeVatRates = [];
                    // Only add positive NL vat rates that are possible given
                    // the plugin settings and the invoice target (buyer).
                    foreach ($countryVatInfos as $countryVatInfo) {
                        $countryVatRate = $countryVatInfo[Fld::VatRate];
                        if (!Number::isZero($countryVatRate) && !Number::floatsAreEqual($countryVatRate, Api::VatFree)) {
                            // Positive (non-zero and non free) vat rate:
                            // @todo: only add if not only selling vat-free or 0% vat products.
                            // @todo: do not add when selling services to UK (should only use vat free).
                            $vatTypeVatRates[] = $countryVatRate;
                        }
                    }
                    // Add 0% vat rate if:
                    // - selling 0% vat products/services.
                    // @todo: do not add when selling services to UK.
                    if ($zeroVatClass !== Config::VatClass_NotApplicable) {
                        $vatTypeVatRates[] = 0.0;
                    }
                    // Add vat free rate if:
                    // - selling vat free products/services
                    // - OR (outside EU AND services).
                    if ($vatFreeClass !== Config::VatClass_NotApplicable) {
                        $vatTypeVatRates[] = Api::VatFree;
                    } elseif ($this->isOutsideEu() && ($nature & Config::Nature_Services !== 0)) {
                        $vatTypeVatRates[] = Api::VatFree;
                    }
                    break;
                case Api::VatType_NationalReversed:
                case Api::VatType_EuReversed:
                case Api::VatType_RestOfWorld:
                    // These vat types can only have the 0% vat rate.
                    $vatTypeVatRates = [0.0];
                    break;
                case Api::VatType_EuVat:
                    /**
                     * @noinspection DuplicatedCode duplicated in case Api::VatType_OtherForeignVat
                     * @todo: remove duplication.
                     */
                    $countryVatInfos = $this->getVatRatesByCountryAndInvoiceDate(
                        $this->invoice->getCustomer()?->getFiscalAddress()?->countryCode,
                        Api::Region_EU
                    );
                    $vatTypeVatRates = [];
                    // Only add those EU vat rates that are possible given
                    // the plugin settings.
                    foreach ($countryVatInfos as $countryVatInfo) {
                        $countryVatRate = $countryVatInfo[Fld::VatRate];
                        if (Number::isZero($countryVatRate)) {
                            // NOTE: we assume here that the 'zeroVatClass'
                            // setting is also set to a vat class when selling
                            // products or services that are 0% in at least one
                            // EU country.
                            if ($zeroVatClass !== Config::VatClass_NotApplicable) {
                                $vatTypeVatRates[] = $countryVatRate;
                            }
                        } elseif (!Number::floatsAreEqual($countryVatRate, Api::VatFree)) {
                            // Positive (non-zero and non-free) vat rate: add if
                            // not only selling vat-free or 0% vat products.
                            $vatTypeVatRates[] = $countryVatRate;
                        }
                    }
                    // Note1: at this moment the API does not accept vat free as
                    //   part of a EU vat invoice.
                    // Note2: I also think that vat free products should not be
                    //   sold as part of a EU vat invoice.
                    // However, for now we will accept this rate here but will
                    // correct it to 0% later on in correctNoVatLines(). This
                    // might change when the API changes.
                    // @todo: remove this?!
                    if ($vatFreeClass !== Config::VatClass_NotApplicable) {
                        $vatTypeVatRates[] = Api::VatFree;
                    }
                    break;
                case Api::VatType_OtherForeignVat:
                    /**
                     * @noinspection DuplicatedCode duplicate from case Api::VatType_EuVat
                     * @todo: remove duplication.
                     */
                    $countryVatInfos = $this->getVatRatesByCountryAndInvoiceDate(
                        $this->invoice->getCustomer()?->getFiscalAddress()?->countryCode,
                        Api::Region_World
                    );
                    $vatTypeVatRates = [];
                    // Only add those EU vat rates that are possible given
                    // the plugin settings.
                    foreach ($countryVatInfos as $countryVatInfo) {
                        $countryVatRate = $countryVatInfo[Fld::VatRate];
                        if (Number::isZero($countryVatRate)) {
                            // NOTE: we assume here that the 'zeroVatClass'
                            // setting is also set to a vat class when selling
                            // products or services that are 0% in at least one
                            // EU country.
                            if ($zeroVatClass !== Config::VatClass_NotApplicable) {
                                $vatTypeVatRates[] = $countryVatRate;
                            }
                        } elseif (!Number::floatsAreEqual($countryVatRate, Api::VatFree)) {
                            // Positive (non-zero and non-free) vat rate: add if
                            // not only selling vat-free or 0% vat products.
                            $vatTypeVatRates[] = $countryVatRate;
                        }
                    }
                    break;
                default:
                    $vatTypeVatRates = [];
                    $this->log->error('Completor::initPossibleVatRates(): unknown vat type %d', $vatType);
                    break;
            }
            // Remove duplicates and convert the list of vat rates to a list of
            // vat rate infos.
            $vatTypeVatRates = array_unique($vatTypeVatRates, SORT_NUMERIC);
            foreach ($vatTypeVatRates as &$vatRate) {
                $vatRate = [Fld::VatRate => $vatRate, Fld::VatType => $vatType];
            }
            /** @noinspection SlowArrayOperationsInLoopInspection */
            $possibleVatRates = array_merge($possibleVatRates, $vatTypeVatRates);
        }
        // Removing duplicates may have created holes in the indexing: re-index.
        $this->possibleVatRates = array_values($possibleVatRates);
    }

    /**
     * Converts amounts to euro if another currency was used.
     *
     * This method only converts amounts at the invoice level. When this method
     * is executed, only the invoice totals are set, the lines totals are not
     * yet set.
     *
     * The line completor handles conversion at the line level and this will be
     * done before calculating the line totals.
     */
    protected function convertToEuro(): void
    {
        $invoice = $this->invoice;
        if ($invoice->metadataExists(Meta::Currency)) {
            /** @var \Siel\Acumulus\Invoice\Currency $currency */
            $currency = $invoice->metadataGet(Meta::Currency);
            if ($currency->shouldConvert()) {
                /** @var \Siel\Acumulus\Invoice\Totals $totals */
                $totals = $invoice->metadataGet(Meta::Totals);
                $totals->amountEx = $currency->convertAmount($totals->amountEx);
                $totals->amountVat = $currency->convertAmount($totals->amountVat);
                $totals->amountInc = $currency->convertAmount($totals->amountInc);
            }
        }
    }

    /**
     * Checks for a missing amount and handles it.
     */
    protected function checkMissingAmount(): void
    {
        // Check if we are missing an amount and, if so, add a line for it.
        $this->completeLineTotals();
        $areTotalsEqual = $this->areTotalsEqual();
        if ($areTotalsEqual === false) {
            $this->addMissingAmountLine();
        }
    }

    /**
     * Calculates the total amount and vat amount for the invoice lines and adds
     * these to the fields 'meta-lines-amount' and 'meta-lines-vatamount'.
     *
     * @todo: use Totals class? but what about incomplete
     */
    protected function completeLineTotals(): void
    {
        $linesAmount = 0.0;
        $linesAmountInc = 0.0;
        $linesVatAmount = 0.0;
        $this->lineTotalsStates = [
            'incomplete' => [],
            'equal' => [],
            'differ' => [],
        ];

        $invoiceLines = $this->invoice->getLines();
        foreach ($invoiceLines as $line) {
            if ($line->metadataExists(Meta::LineAmount)) {
                $linesAmount += $line->metadataGet(Meta::LineAmount);
            } elseif (isset($line->unitPrice)) {
                $linesAmount += $line->quantity * $line->unitPrice;
            } else {
                $this->lineTotalsStates['incomplete'][Meta::LinesAmount] = Meta::LinesAmount;
            }

            if ($line->metadataExists(Meta::LineAmountInc)) {
                $linesAmountInc += $line->metadataGet(Meta::LineAmountInc);
            } elseif ($line->metadataExists(Meta::UnitPriceInc)) {
                $linesAmountInc += $line->quantity * $line->metadataGet(Meta::UnitPriceInc);
            } else {
                $this->lineTotalsStates['incomplete'][Meta::LinesAmountInc] = Meta::LinesAmountInc;
            }

            if ($line->metadataExists(Meta::LineVatAmount)) {
                $linesVatAmount += $line->metadataGet(Meta::LineVatAmount);
            } elseif ($line->metadataExists(Meta::VatAmount)) {
                $linesVatAmount += $line->quantity * $line->metadataGet(Meta::VatAmount);
            } else {
                $this->lineTotalsStates['incomplete'][Meta::LinesVatAmount] = Meta::LinesVatAmount;
            }
        }

        $this->invoice->metadataSet(Meta::LinesAmount, $linesAmount);
        $this->invoice->metadataSet(Meta::LinesAmountInc, $linesAmountInc);
        $this->invoice->metadataSet(Meta::LinesVatAmount, $linesVatAmount);
        if (!empty($this->lineTotalsStates['incomplete'])) {
            sort($this->lineTotalsStates['incomplete']);
            $this->invoice->metadataAddMultiple(Meta::LinesIncomplete, $this->lineTotalsStates['incomplete']);
        }
    }

    /**
     * Compares the invoice totals metadata with the line totals metadata.
     *
     * If any of the 3 values are equal we do consider the totals to be equal
     * (except for a 0 VAT amount (for reversed VAT invoices)). This because in
     * many cases 1 or 2 of the 3 values are either incomplete or incorrect.
     *
     * @return bool|null
     *   True if the totals are equal, false if not equal, null if undecided
     *   (all 3 values are incomplete).
     *
     * @noinspection DuplicatedCode
     */
    protected function areTotalsEqual(): ?bool
    {
        $invoice = $this->invoice;
        /** @var \Siel\Acumulus\Invoice\Totals $totals */
        $totals = $invoice->metadataGet(Meta::Totals);
        if (!in_array(Meta::LinesAmount, $this->lineTotalsStates['incomplete'], true)) {
            if (Number::floatsAreEqual($totals->amountEx, $invoice->metadataGet(Meta::LinesAmount), 0.05)) {
                $this->lineTotalsStates['equal'][Meta::LinesAmount] = Meta::InvoiceAmount;
            } else {
                $this->lineTotalsStates['differ'][Meta::LinesAmount] = $totals->amountEx - $invoice->metadataGet(Meta::LinesAmount);
            }
        }
        if (!in_array(Meta::LinesAmountInc, $this->lineTotalsStates['incomplete'], true)) {
            if (Number::floatsAreEqual($totals->amountInc, $invoice->metadataGet(Meta::LinesAmountInc), 0.05)) {
                $this->lineTotalsStates['equal'][Meta::LinesAmountInc] = Meta::InvoiceAmountInc;
            } else {
                $this->lineTotalsStates['differ'][Meta::LinesAmountInc] = $totals->amountInc - $invoice->metadataGet(Meta::LinesAmountInc);
            }
        }
        if (!in_array(Meta::LinesVatAmount, $this->lineTotalsStates['incomplete'], true)) {
            if (Number::floatsAreEqual($totals->amountVat, $invoice->metadataGet(Meta::LinesVatAmount), 0.05)) {
                $this->lineTotalsStates['equal'][Meta::LinesVatAmount] = Meta::InvoiceVatAmount;
            } else {
                $this->lineTotalsStates['differ'][Meta::LinesVatAmount] = $totals->amountVat - $invoice->metadataGet(Meta::LinesVatAmount);
            }
        }

        $equal = count($this->lineTotalsStates['equal']);
        $differ = count($this->lineTotalsStates['differ']);

        if ($differ > 0) {
            $result = false;
        } elseif ($equal > 0) {
            // If only the vat amounts are equal, while the vat amount = 0, we
            // cannot decide that the totals are equal because this appears to
            // be a vat free/reversed vat invoice without any vat amount.
            $result = $equal === 1
            && array_key_exists(Meta::InvoiceVatAmount, $this->lineTotalsStates['differ'])
            && Number::isZero($invoice->metadataGet(Meta::LinesVatAmount))
                ? null
                : true;
        } else {
            // No equal amounts nor different amounts found: undecided.
            $result = null;
        }
        return $result;
    }

    /**
     * Adds an invoice line if the order amount differs from the lines total.
     *
     * Besides the line, we also add a warning and change the invoice to a
     * concept.
     *
     * The amounts can differ if e.g:
     * - we missed a fee that is stored in custom fields of a not (yet)
     *   supported 3rd party plugin
     * - a manual adjustment that is not separately stored
     * - an error in this plugin's logic
     * - an error in the data as provided by the web shop
     *
     * However, we can only add this line if we have at least 2 complete values,
     * that is, there are no strategy lines.
     *
     * PRECONDITION: areTotalsEqual() returned false;
     */
    protected function addMissingAmountLine(): void
    {
        $invoice = $this->invoice;

        $invoiceSettings = $this->config->getInvoiceSettings();
        $incomplete = count($this->lineTotalsStates['incomplete']);
        if ($invoiceSettings['missingAmount'] === Config::MissingAmount_AddLine && $incomplete <= 1) {
            // NOTE: $incomplete <= 1 IMPLIES $equal + $differ >= 2
            // We want to use the differences in amount and vat amount. Check if
            // they are available, if not compute them based on data that is
            // available.
            if (array_key_exists(Meta::LinesAmount, $this->lineTotalsStates['equal'])) {
                $this->lineTotalsStates['differ'][Meta::LinesAmount] = 0;
            }
            if (array_key_exists(Meta::LinesAmountInc, $this->lineTotalsStates['equal'])) {
                $this->lineTotalsStates['differ'][Meta::LinesAmountInc] = 0;
            }
            if (array_key_exists(Meta::LinesVatAmount, $this->lineTotalsStates['equal'])) {
                $this->lineTotalsStates['differ'][Meta::LinesVatAmount] = 0;
            }
            // NOW HOLDS: $differ >= 2
            if (!array_key_exists(Meta::LinesAmount, $this->lineTotalsStates['differ'])) {
                $this->lineTotalsStates['differ'][Meta::LinesAmount] = $this->lineTotalsStates['differ'][Meta::LinesAmountInc]
                    - $this->lineTotalsStates['differ'][Meta::LinesVatAmount];
            }
            if (!array_key_exists(Meta::LinesVatAmount, $this->lineTotalsStates['differ'])) {
                $this->lineTotalsStates['differ'][Meta::LinesVatAmount] = $this->lineTotalsStates['differ'][Meta::LinesAmountInc]
                    - $this->lineTotalsStates['differ'][Meta::LinesAmount];
            }

            // Create line.
            $missingAmount = $this->lineTotalsStates['differ'][Meta::LinesAmount];
            $missingVatAmount = $this->lineTotalsStates['differ'][Meta::LinesVatAmount];
            $countLines = count($invoice->getLines());
            if ($this->source->getType() === Source::CreditNote) {
                $product = $this->t('refund_adjustment');
            } elseif ($this->lineTotalsStates['differ'][Meta::LinesAmount] < 0.0) {
                $product = $this->t('discount_adjustment');
            } else {
                $product = $this->t('fee_adjustment');
            }

            /** @var \Siel\Acumulus\Data\Line $line */
            $line = Container::getContainer()->createAcumulusObject(DataType::Line);
            $line->product = $product;
            $line->quantity = 1;
            $line->unitPrice = $missingAmount;
            $line->metadataSet(Meta::VatAmount, $missingVatAmount);
            $line->metadataSet(Meta::PrecisionUnitPrice, $countLines * 0.02);
            $line->metadataSet(Meta::PrecisionVatAmount, $countLines * 0.02);
            $this->getCompletorTask(DataType::Line, 'VatRange')->complete($line);
            $line->metadataSet(Meta::SubType, LineType::Corrector);
            // Correct and add this line (round of correcting has already been
            // executed).
            if ($line->metadataGet(Meta::VatRateSource) === VatRateSource::Calculated) {
                $this->LineCompletor->correctVatRateByRange($line);
            }
            $invoice->addLine($line);

            // Add warning.
            $this->changeInvoiceToConcept($invoice, 'message_warning_missing_amount_added', 809, $missingAmount, $missingVatAmount);
        } elseif ($invoiceSettings['missingAmount'] !== Config::MissingAmount_Ignore) {
            // Due to lack of information, we cannot add a missing line, even
            // though we know we are missing something: just add a warning.
            $missing = [];
            if (array_key_exists(Meta::LinesAmount, $this->lineTotalsStates['differ'])) {
                $amount = $this->lineTotalsStates['differ'][Meta::LinesAmount];
                $field = $this->t('amount_ex');
                $missing[] = sprintf($this->t('message_warning_missing_amount_spec'), $field, $amount);
            }
            if (array_key_exists(Meta::LinesAmountInc, $this->lineTotalsStates['differ'])) {
                $amount = $this->lineTotalsStates['differ'][Meta::LinesAmountInc];
                $field = $this->t('amount_inc');
                $missing[] = sprintf($this->t('message_warning_missing_amount_spec'), $field, $amount);
            }
            if (array_key_exists(Meta::LinesVatAmount, $this->lineTotalsStates['differ'])) {
                $amount = $this->lineTotalsStates['differ'][Meta::LinesVatAmount];
                $field = $this->t('amount_vat');
                $missing[] = sprintf($this->t('message_warning_missing_amount_spec'), $field, $amount);
            }
            $this->changeInvoiceToConcept($invoice, 'message_warning_missing_amount_warn', 810, ucfirst(implode(', ', $missing)));
        }
    }

    /**
     * Determines the vat type of the invoice.
     *
     * This method (and class) is aware of:
     * - The setting foreignVat.
     * - The country of the client.
     * - Whether the client is a company.
     * - The actual VAT rates on the day of the order.
     * - Whether there are margin products in the order.
     *
     * So to start with, any list of (possible) vat types is based on the above.
     * Furthermore, this method and {@see getInvoiceLinesVatTypeInfo()} are
     * aware of:
     * - The fact that orders do not have to be split over different vat types,
     *   but that invoices should be split if both national and foreign VAT
     *   rates appear on the order.
     * - The vat class metadata per line and which classes denote EU vat.
     *   This info is used to distinguish between NL and EU vat for EU
     *   countries that have VAT rates in common with NL and the settings
     *   indicate that this shop sells products in both vat type categories.
     *
     * If multiple vat types are possible, the invoice is sent as concept, so
     * that it may be corrected in Acumulus.
     *
     * @noinspection PhpFunctionCyclomaticComplexityInspection
     * @noinspection PhpComplexFunctionInspection
     */
    protected function completeVatType(): void
    {
        $invoice = $this->invoice;
        $this->checkForKnownVatType();
        // If shop specific code or an event handler has already set the vat
        // type, we don't change it.
        if (empty($invoice->vatType)) {
            $vatTypeInfo = $this->getInvoiceLinesVatTypeInfo();
            $message = '';
            $code = 0;
            if (count($vatTypeInfo['intersection']) === 0) {
                // No single vat type is correct for all lines, use the
                // union to guess what went wrong.
                if (count($vatTypeInfo['union']) === 0) {
                    // None of the vat rates of the invoice lines could be
                    // matched with any vat rate for any possible vat type.
                    // Possible causes:
                    // - Invoice has no vat but cannot be a reversed vat invoice
                    //   nor outside the EU, nor are vat free products or
                    //   services sold. Message: 'Check "about your shop"
                    //   settings or the vat rates assigned to your products.'
                    // - Vat rates are incorrect for given country (and date).
                    //   Message: 'Did you configure the correct settings for
                    //   country ...?' or 'Were there recent changes in tax
                    //   rates?'.
                    // - Vat rates are for EU VAT but the shop does not use EU
                    //   VAT. Message 'Check "about your shop" settings'.
                    // - Vat rates are Dutch vat rates but shop uses EU VAT and
                    //   client is in the EU. Message: 'Check the vat rates
                    //   assigned to your products.'.
                    //
                    // Pick the first - and perhaps only - vat type from the
                    // original list of possible vat types, this is probably vat
                    // type 1.
                    $invoice->vatType = reset($this->possibleVatTypes);
                    $message = 'message_warning_no_vattype_at_all';
                    $code = 804;
                } elseif (count($vatTypeInfo['union']) === 1) {
                    // One or more lines could be matched with exactly 1 vat
                    // type, but not all lines.
                    // Possible causes:
                    // - Non-matching lines have no vat. Message: 'Manual line
                    //   entered without vat' or 'Check vat settings on those
                    //   products.'.
                    // - Non-matching lines have vat. Message: 'Manual line
                    //   entered with incorrect vat' or 'Check vat settings on
                    //   those products.'.
                    $invoice->vatType = reset($vatTypeInfo['union']);
                    $message = 'message_warning_no_vattype_incorrect_lines';
                    $code = 812;
                } else {
                    // Separate lines could be matched with possible vat types,
                    // but not all with the same vat type.
                    // Possible causes:
                    // - Mix of foreign and NL VAT rates. Message: 'Split
                    //   invoice.'.
                    // - Some lines have no vat but no vat free goods or
                    //   services are sold and thus this could be a reversed vat
                    //   (company in EU) or vat free invoice (outside EU).
                    //   Message: check vat settings.
                    // - Mix of margin scheme and normal vat: this can be solved
                    //   by making it a margin scheme invoice and adding
                    //   costPrice = 0 to all normal lines.
                    /** @noinspection NestedPositiveIfStatementsInspection */
                    if (in_array(Api::VatType_MarginScheme, $vatTypeInfo['union'])) {
                        $invoice->vatType = Api::VatType_MarginScheme;
                        $invoice->metadataSet(Meta::VatTypeSource, 'Completor::completeVatType: Convert all lines to margin scheme');
                    } else {
                        // Take the first vat type as a guess but add a warning.
                        $invoice->vatType = reset($vatTypeInfo['union']);
                        $message = 'message_warning_no_vattype_must_split';
                        $code = 806;
                    }
                }
            } elseif (count($vatTypeInfo['intersection']) === 1) {
                // Exactly 1 vat type was found to be possible for all lines:
                // use that one as the vat type for the invoice.
                $invoice->vatType = reset($vatTypeInfo['intersection']);
                $invoice->metadataSet(Meta::VatTypeSource, 'Completor::completeVatType: Only 1 choice fits all');
            } else {
                // Multiple vat types were found to be possible for all lines:
                // Guess which one to take or add a warning.
                // Possible causes:
                // - Client country has same VAT rates as the Netherlands and
                //   shop uses foreign and NL VAT rates. Kind of solvable by
                //   correct shop settings, but those are the current settings
                //   that may not hold for older invoices.
                // - Invoice has no vat and the client is outside the EU, and it
                //   is unknown whether the invoice lines contain services or
                //   goods. Perhaps solvable by correct shop settings.
                // - Margin invoice: all lines that have a cost price will
                //   probably also satisfy the normal vat. This is solvable by
                //   making it a margin scheme invoice and adding cost price = 0
                //   to all normal lines.
                $this->guessVatType($vatTypeInfo['intersection']);
                /** @noinspection PhpConditionAlreadyCheckedInspection  can be set by guessVatType() */
                if (empty($invoice->vatType)) {
                    if (in_array(Api::VatType_MarginScheme, $vatTypeInfo['union'])) {
                        $invoice->vatType = Api::VatType_MarginScheme;
                    } elseif ($this->isEuWithSameVatRate($vatTypeInfo['intersection'])) {
                        $shopSettings = $this->config->getShopSettings();
                        $euVat = $shopSettings['euVat'];
                        if ($euVat === Config::EuVat_No) {
                            $vatType = Api::VatType_National;
                        } elseif ($euVat === Config::EuVat_Yes) {
                            $vatType = Api::VatType_EuVat;
                        } elseif ($euVat === Config::EuVat_SwitchOnLimit) {
                            $year = (int) $this->getInvoiceDate()->format('Y');
                            $currentYear = (int) date('Y');
                            if ($year < $currentYear) {
                                // Older year: we can only be sure if we did
                                // not pass the limit at all.
                                $vatType = Api::VatType_National;
                                if (!in_array($this->getEuSalesReport($year), [Completor::EuSales_Safe, Completor::EuSales_Warning])) {
                                    // We chose one, but should warn.
                                    $notice = sprintf(
                                        $this->t('message_notice_multiple_possible_vattype_chose_one'),
                                        $this->t('vat_type'),
                                        $this->t('vat_type_' . $vatType),
                                        $this->invoice->getCustomer()?->getFiscalAddress()->country
                                        ?? $this->invoice->getCustomer()?->getFiscalAddress()->countryCode,
                                        $this->t('netherlands'),
                                        $this->t('field_euVat')
                                    );
                                    $this->result->createAndAddMessage($notice, Severity::Notice, 814);
                                    $this->addWarning($invoice, $notice, Meta::Notice);
                                }
                            } else {
                                // Current year: we can assume to be sure when
                                // below the warning or above the limit,
                                // otherwise we should warn.
                                switch ($this->getEuSalesReport($year)) {
                                    case Completor::EuSales_Passed:
                                    case Completor::EuSales_WillReach:
                                        $vatType = Api::VatType_EuVat;
                                        break;
                                    case Completor::EuSales_Safe:
                                        $vatType = Api::VatType_National;
                                        break;
                                    case Completor::EuSales_Warning:
                                        // Choose one but warn.
                                        $vatType = Api::VatType_National;
                                        $notice = sprintf(
                                            $this->t('message_notice_multiple_possible_vattype_chose_one'),
                                            $this->t('vat_type'),
                                            $this->t('vat_type_' . $vatType),
                                            $this->invoice->getCustomer()?->getFiscalAddress()->country
                                            ?? $this->invoice->getCustomer()?->getFiscalAddress()->countryCode,
                                            $this->t('netherlands'),
                                            $this->t('field_euVat')
                                        );
                                        $this->result->createAndAddMessage($notice, Severity::Notice, 813);
                                        $this->addWarning($invoice, $notice, Meta::Notice);
                                        break;
                                }
                            }
                        }
                        if (isset($vatType)) {
                            $invoice->vatType = $vatType;
                        }
                    }

                    if (empty($invoice->vatType)) {
                        // Take the first vat type as a guess but add a warning.
                        $invoice->vatType = reset($vatTypeInfo['intersection']);
                        $message = 'message_warning_no_vattype_multiple_possible';
                        $code = 811;
                    }
                }
            }

            if (!empty($message)) {
                // Make the invoice a concept, so it can be changed in Acumulus
                // and add message and meta info.
                $startSentence = count($vatTypeInfo['intersection']) === 0
                    ? 'message_warning_no_vattype'
                    : 'message_warning_multiple_vattypes';
                $this->changeInvoiceToConcept($invoice, $message, $code, $this->t($startSentence));
            }
            $invoice->metadataAddMultiple(Meta::VatTypesPossibleInvoice, $this->possibleVatTypes);
            $invoice->metadataAddMultiple(Meta::VatTypesPossibleInvoiceLinesIntersection, $vatTypeInfo['intersection']);
            $invoice->metadataAddMultiple(Meta::VatTypesPossibleInvoiceLinesUnion, $vatTypeInfo['union']);
        }
    }

    /**
     * Returns information about possible vat types based on the invoice lines.
     *
     * This method returns:
     * - Possible vat types per invoice line (with a correct vat rate).
     * - The union of these results.
     * - The intersection of these results.
     *
     * @return int[][]
     *   List of possible vat types per invoice line. The outer array is keyed
     *   by the line index, the inner array is keyed by vat type. In addition to
     *   the line indices, the outer array also contains 2 keys 'union' and
     *  'intersection', containing resp. the union and intersection of the other
     *   array values.
     *
     * @noinspection PhpFunctionCyclomaticComplexityInspection
     */
    protected function getInvoiceLinesVatTypeInfo(): array
    {
        $list = [];
        $union = [];
        $intersection = null;
        foreach ($this->invoice->getLines() as $index => $line) {
            if (static::isCorrectVatRate($line->metadataGet(Meta::VatRateSource))) {
                $possibleLineVatTypes = [];
                foreach ($this->possibleVatRates as $vatRateInfo) {
                    $vatRate = (float) $vatRateInfo[Fld::VatRate];
                    $vatType = $vatRateInfo[Fld::VatType];
                    // We should treat 0 and vat free vat rates as equal as
                    // they are not yet corrected.
                    $equal = Number::floatsAreEqual($vatRate, $line->vatRate);
                    $bothZeroOrVatFree = $this->isNoVat($vatRate) && $this->isNoVat($line->vatRate);
                    if ($equal || $bothZeroOrVatFree) {
                        // We have a possibly matching vat type. Perform some
                        // additional checks before we really add it as a match:
                        $doAdd = true;

                        // 1) Vat type margin scheme requires a cost price.
                        if (($vatType === Api::VatType_MarginScheme) && empty($line->costPrice)) {
                            $doAdd = false;
                        }

                        // 2) If this is a 0 vat rate while the lookup vat rate
                        //   or class, if available, is not, it must be a no vat
                        //   invoice:
                        //   - one of the no-vat vat types.
                        //   - vat type national with client outside EU.
                        if ($this->lineHasNoVat($line)) {
                            if (!empty($line->metadataGet(Meta::VatClassId)) || !empty($line->metadataGet(Meta::VatRateLookup))) {
                                $zeroOrVatFreeClass = $this->is0VatClass($line) || $this->isVatFreeClass($line);
                                $zeroOrVatFreeRate = !empty($line->metadataGet(Meta::VatRateLookup))
                                    && $this->metaDataHasANoVat($line->metadataGet(Meta::VatRateLookup));
                                // According to the available lookup data, this
                                // article cannot be intrinsically 0% or vat
                                // free. So the vat type must be a vat type
                                // allowing no vat, i.e. a no-vat vat type or
                                // national vat for a customer outside the EU.
                                /**
                                 * @noinspection TypeUnsafeArraySearchInspection
                                 *   Not sure if $vatType may actually be a string.
                                 */
                                if (!($zeroOrVatFreeClass || $zeroOrVatFreeRate)
                                    && !in_array($vatType, static::$zeroVatVatTypes)
                                    && !($vatType === Api::VatType_National && $this->isOutsideEu())
                                ) {
                                    $doAdd = false;
                                }
                            }
                            // If the customer is outside the EU AND we do not
                            // charge vat, goods should get vat type 4 and
                            // services vat type 1. However, we only look at
                            // item lines, as services like shipping and packing
                            // are part of the delivery as a whole and should
                            // not change the vat type just because they are a
                            // service.
                            if ($this->isOutsideEu()
                                && $line->metadataGet(Meta::SubType) === LineType::Item
                                && !empty($line->nature)
                            ) {
                                if ($vatType === Api::VatType_National && $line->nature === Api::Nature_Product) {
                                    $doAdd = false;
                                }
                                if ($vatType === Api::VatType_RestOfWorld && $line->nature === Api::Nature_Service) {
                                    $doAdd = false;
                                }
                            }
                        }

                        if ($doAdd) {
                            // Ensure unique entries by also using the value as key.
                            $possibleLineVatTypes[$vatType] = $vatType;
                        }
                    }
                }
                // Add meta info to Acumulus invoice.
                $possibleLineVatTypes = array_values($possibleLineVatTypes);
                $line->metadataAddMultiple(Meta::VatTypesPossible, $possibleLineVatTypes);
                // Add to result, union and intersection.
                $list[$index] = $possibleLineVatTypes;
                /** @noinspection SlowArrayOperationsInLoopInspection */
                $union = array_merge($union, $possibleLineVatTypes);
                $intersection = $intersection !== null ? array_intersect($intersection, $possibleLineVatTypes) : $possibleLineVatTypes;
            }
        }

        // Union can obviously contain double results.
        $list['union'] = array_values(array_unique($union));
        // Intersection can contain double results due to handling 0% and
        // vat-free as being the same (as they have not yet been corrected).
        $list['intersection'] = $intersection !== null ? array_values(array_unique($intersection)) : [];
        return $list;
    }

    /**
     * Checks if the vat type can be known for sure and if so, sets it.
     *
     * This method should be overridden by a shop specific Completor override if
     * the shop stores additional data from which the vat type could be
     * determined with certainty.
     *
     * This base method checks these rules:
     * - If {@see initPossibleVatTypes()} arrived at only 1 possible vat type,
     *   we choose that one without further checking.
     * - If only vat free services are sold, an invoice cannot be a reversed
     *   vat invoice, as the tax office says about this situation: "... U mag op
     *   de factuur niet vermelden dat de btw is verlegd, maar in plaats daarvan
     *   geeft u aan dat de dienst in het land van uw afnemer onder een
     *   vrijstelling of het 0%-tarief valt.".
     */
    protected function checkForKnownVatType(): void
    {
        $possibleVatTypes = $this->possibleVatTypes;
        sort($possibleVatTypes, SORT_NUMERIC);
        if (count($possibleVatTypes) === 1) {
            // Rule 1.
            $this->invoice->vatType = reset($possibleVatTypes);
            $this->invoice->metadataSet(Meta::VatTypeSource, 'Completor::checkForKnownVatType: only 1 possible vat type');
//        } elseif ($possibleVatTypes == [Api::VatType_National, Api::VatType_EuReversed]) {
            // @todo: re-enable if we can determine that the shop only sells vat free services.
//            $shopSettings = $this->config->getShopSettings();
//            $vatFreeProducts = $shopSettings['vatFreeProducts'];
//            $nature = $shopSettings['nature_shop'];
//            if ($vatFreeProducts == Config::VatFreeProducts_Only && $nature == Config::Nature_Services) {
//                // Rule 2.
//                $this->invoice->vatType = Api::VatType_National;
//                $this->invoice->metadataSet(Meta::VatTypeSource, 'Completor::checkForKnownVatType: vat free services only');
//            }
        }
    }

    /**
     * Tries to guess the vat type from a list of possible vat types.
     *
     * This method gets called when Completor::completeVatType() decided that
     * multiple vat types are possible for all invoice lines, and should, given
     * that situation, try to make an educated guess. If it can make such a
     * choice, the vat type is set to that choice and no warning will be
     * emitted, nor will the invoice be sent as concept. So this choice should
     * be a pretty sure one.
     *
     * Override this method in a shop specific override of Completor if the shop
     * may have some additional information to facilitate making this choice.
     *
     * @param int[] $possibleVatTypes
     *   A list of vat types that are possible for each invoice line (as found
     *   by Completor::completeVatType()).
     */
    protected function guessVatType(array $possibleVatTypes): void
    {
        sort($possibleVatTypes, SORT_NUMERIC);
        if ($possibleVatTypes === [Api::VatType_National, Api::VatType_EuReversed]) {
            // The invoice does not have vat and no vat class refers to only > 0
            // vat rates (but could be unknown). Reversed vat would do but if we
            // really only have vat-free services, reversed vat would make it 0%
            // instead of vat-free, which we should not want because of what the
            // tax office says to do in this situation: "... U mag op de factuur
            // niet vermelden dat de btw is verlegd, maar in plaats daarvan
            // geeft u aan dat de dienst in het land van uw afnemer onder een
            // vrijstelling of het 0%-tarief valt."
            $allItemsVatFree = true;
            $allItemsService = true;
            foreach ($this->invoice->getLines() as $line) {
                if (!empty($line->metadataGet(Meta::VatClassId))) {
                    if (!$this->isVatFreeClass($line->metadataGet(Meta::VatClassId))) {
                        $allItemsVatFree = false;
                        break;
                    }
                } elseif (!empty($line->metadataGet(Meta::VatRateLookup))) {
                    if ($this->metaDataHasOnlyNoVat($line->metadataGet(Meta::VatRateLookup))) {
                        $allItemsVatFree = null;
                        break;
                    }
                } else {
                    $allItemsVatFree = null;
                    break;
                }

                if (empty($line->nature)) {
                    // We have a possible non-service item line: do not
                    // choose.
                    $allItemsService = null;
                    break;
                } elseif ($line->nature !== Api::Nature_Service) {
                    // We have a non-service item line: do not choose.
                    $allItemsService = false;
                    break;
                }
            }
            if ($allItemsVatFree && $allItemsService) {
                $this->invoice->vatType = Api::VatType_National;
                $this->invoice->metadataSet(Meta::VatTypeSource, 'Completor::guessVatType: all items are vat free services');
            } elseif ($allItemsService === false) {
                $this->invoice->vatType = Api::VatType_EuReversed;
                $this->invoice->metadataSet(Meta::VatTypeSource, 'Completor::guessVatType: at least 1 item is not a service');
            }
        }
    }

    /**
     * Adds the field 'vatTypeId' to the customer part of the invoice.
     *
     * - If shop specific code or an event handler has already set the vat type
     *   id, we don't change it.
     * - Non companies are always 1 - private.
     * - Companies will get 2 - business unless we can determine that the
     *   invoice targets a vat exempt company: the company is EU (outside NL)
     *   and the invoice type is not EU reversed and the invoice is not vat-free
     *   as vat-free trumps reversed vat.
     *
     * @todo: Convert to a {@see \Siel\Acumulus\Completors\CompletorTaskInterface} and use
     *   the fiscal address
     */
    protected function completeVatTypeId(): void
    {
        if (empty($this->invoice->getCustomer()->vatTypeId)) {
            $vatTypeId = $this->isCompany() ? Api::VatTypeId_Business : Api::VatTypeId_Private;
            if ($this->isCompany()
                && $this->isEu()
                && !empty($this->invoice->vatType)
                && $this->invoice->vatType !== Api::VatType_EuReversed
                && !$this->isVatFreeInvoice()
            ) {
                $vatTypeId = Api::VatTypeId_Private;
            }
            $this->invoice->getCustomer()->vatTypeId = $vatTypeId;
        }
    }

    /**
     * Corrects an invoice if it is a margin scheme invoice.
     *
     * If an invoice is of the margin scheme type, all lines have to follow the
     * margin scheme rules. These rules are:
     * - Each line must have a cost price, but that cost price may be 0.
     * - The unit price should now contain the price including VAT (requirement
     *   of the web service API).
     * Thus, if there are e.g. shipping lines or other fee lines, they have to
     * be converted to the margin scheme (cost price tag and change of unit
     * price).
     */
    protected function correctMarginInvoice(): void
    {
        if (isset($this->invoice->vatType)
            && $this->invoice->vatType === Api::VatType_MarginScheme
        ) {
            foreach ($this->invoice->getLines() as $line) {
                // For margin invoices, Acumulus expects the unit price to be
                // the sales price, i.e. the price the client pays. So we set
                // 'unitPrice' to 'unitPriceInc'.
                // Non margin lines may "officially" not appear on margin
                // invoices, so we turn them into margin lines by adding a
                // 'costPrice' of 0 and also setting 'unitPrice' to
                // 'unitPriceInc'.
                if (!isset($line->costPrice)) {
                    // "Normal" line: set costPrice as 0.
                    $line->costPrice = 0.0;
                }
                // Add "marker" tag (for debug purposes) for this correction.
                $line->metadataSet(Meta::RecalculateOldPrice, $line->unitPrice);
                // Change 'unitPrice' tag to include VAT.
                if ($line->metadataExists(Meta::UnitPriceInc)) {
                    $line->unitPrice = $line->metadataGet(Meta::UnitPriceInc);
                } elseif ($line->metadataExists(Meta::VatAmount)) {
                    $line->unitPrice += $line->metadataGet(Meta::VatAmount);
                } elseif (isset($line->vatRate)) {
                    $line->unitPrice += $line->vatRate / 100.0 * ($line->unitPrice - $line->costPrice);
                } //else {
                // Impossible to correct the 'unitPrice'. Probably all
                // strategies failed, so the invoice should already
                // have a warning.
                //}
            }
        }
    }

    /**
     * Correct vat lines that have a NULL vat rate.
     *
     * Some lines may still have null for vat rate because earlier on, no choice
     * could be made. However, if we were able to choose a vat type (in
     * completeVatType()), we now might be able to reduce the possibilities
     * to a single choice.
     */
    protected function correctNullVatLines(): void
    {
        if (empty($this->invoice->vatType)) {
            return;
        }
        $vatType = $this->invoice->vatType;
        foreach ($this->invoice->getLines() as $line) {
            if ($line->vatRate === null && $line->metadataExists(Meta::VatRateRangeMatches)) {
                // See if we can find a single match based on vat type.
                $vatRate = null;
                foreach ($line->metadataGet(Meta::VatRateRangeMatches) as $vatRateMatch) {
                    if ($vatRateMatch[Fld::VatType] === $vatType) {
                        if ($vatRate === null) {
                            // First match: set.
                            $vatRate = $vatRateMatch[Fld::VatRate];
                        } else {
                            // 2nd match: unset, we cannot choose.
                            $vatRate = false;
                        }
                    }
                }
                if (!empty($vatRate)) {
                    // We have a single match, choose and set.
                    $line->vatRate = $vatRate;
                }
            }
        }
    }

    /**
     * Correct 0% and vat free rates.
     *
     * Acumulus distinguishes between 0% vat and vat free.
     * 0% vat should be used with:
     * - Products or services that have 0% vat (currently e.g. face masks).
     * - Reversed vat invoices, EU or national (vat type = 2 or 3).
     * - Products outside the EU (vat type = 4).
     * Vat free should be used for:
     * - Small companies that use the KOR: All products in their catalog should
     *   use the vat free tax class.
     * - Vat free products and services, e.g. care, education (vat type = 1 or
     *   5): The vat free products in their catalog should use the vat free tax
     *   class.
     * - Services outside the EU, consumers or companies (vat type = 1): The
     *   product/service should have a "normal" vat class, but the rate should
     *   be 0 anyway and the customer should be outside the EU.
     *
     * However, both will typically be stored as a €0,- amount or 0% rate. To
     * correct these lines, we use the shop settings about vat classes used for
     * 0% and vat free; the vat class of the product; and the vat type.
     *
     * Note: to do this correctly, especially choosing between vat type 1 and 4
     * for invoices outside the EU, we should also be able to distinguish
     * between services and products. For that, the shop should only sell
     * products or only services, or the nature field should be filled in.
     * If not filled in, we act as if the line invoices a product.
     *
     * See:
     * - {@link https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/tarieven_en_vrijstellingen/}
     * - {@link https://www.siel.nl/acumulus/handleiding/?a=facturen_naar_het_buitenland}:
     */
    protected function correctNoVatLines(): void
    {
        $vatType = $this->invoice->vatType ?? null;
        foreach ($this->invoice->getLines() as $line) {
            if ($this->isFreeVatRate($line)) {
                // Change if the vat class indicates a 0% vat-rate or if vat free is not
                // allowed for the vat type
                if ($line->getType() !== LineType::Voucher
                    && ($this->is0VatClass($line) || !in_array($vatType, static::$vatTypesAllowingVatFree, true))
                ) {
                    $line->vatRate = 0.0;
                    $line->metadataAdd(Meta::VatRateSource, VatRateSource::Corrected_NoVat);
                }
            } elseif ($this->is0VatRate($line)) {
                // Change if the vat class indicates a vat free rate and vat
                // free is allowed for the vat type, or if 0% is not an allowed
                // vat rate.
                /**
                 * @noinspection TypeUnsafeArraySearchInspection
                 *   Not sure if $vatType may actually be a string.
                 */
                if (($this->isVatFreeClass($line) && in_array($vatType, static::$vatTypesAllowingVatFree, true))
                    || !$this->is0VatPossibleForVatType($vatType)
                    || $line->getType() === LineType::Voucher
                ) {
                    $line->vatRate = Api::VatFree;
                    $line->metadataAdd(Meta::VatRateSource, VatRateSource::Corrected_NoVat);
                }
            }
        }
    }

    /**
     * Removes an empty shipping line (if so configured).
     */
    protected function removeEmptyShipping(): void
    {
        $invoiceSettings = $this->config->getInvoiceSettings();
        if (!$invoiceSettings['sendEmptyShipping']) {
            foreach ($this->invoice->getLines() as $line) {
                if ($line->metadataGet(Meta::SubType) === LineType::Shipping && Number::isZero($line->unitPrice)) {
                    $this->invoice->removeLine($line);
                }
            }
        }
    }

    /**
     * Checks the EU commerce threshold.
     *
     * - We only check if this feature is enabled.
     * - we only check positive invoices, thus no credit notes, of vat type
     *   national (1) to EU customers:
     *   - Are allowed as long as the company is below the threshold.
     *   - If the threshold has been reached or will be reached with this
     *     invoice, the invoice will be sent as concept and a warning will be
     *     added to the mail sent to the shop owner.
     *   - If the warning percentage is or will be passed, the invoice will be
     *     sent as is, but a notice will be mailed to the user.
     */
    protected function checkEuCommerceThreshold(): void
    {
        $warningPercentage = $this->config->getInvoiceSettings()['euCommerceThresholdPercentage'];
        $invoice = $this->invoice;
        if (is_float($warningPercentage)
            && $this->source->getType() !== Source::CreditNote
            && $this->isEu()
            && isset($invoice->vatType)
            && $invoice->vatType === Api::VatType_National
        ) {
            $year = (int) $this->getInvoiceDate()->format('Y');
            switch ($this->getEuSalesReport($year)) {
                case Completor::EuSales_Passed:
                    $this->changeInvoiceToConcept($invoice, 'eu_commerce_threshold_passed', 830);
                    break;
                case Completor::EuSales_WillReach:
                    $this->changeInvoiceToConcept($invoice, 'eu_commerce_threshold_will_pass', 831);
                    break;
                case Completor::EuSales_Warning:
                    // Send mail with notice, xml message will not be added if
                    // there are no warnings or worse.
                    $this->result->createAndAddMessage($this->t('eu_commerce_threshold_warning'), Severity::Notice, 832);
                    break;
            }
        }
    }

    /**
     * Returns whether the given line has a no-vat vat rate (0% or vat free).
     *
     * @param Line $line
     *   The invoice line to check.
     *
     * @return bool
     *   True if the given line has a no-vat vat rate (0% or vat free), false
     *   if it has a positive vat rate.
     */
    protected function lineHasNoVat(Line $line): bool
    {
        return $this->isNoVat($line);
    }

    /**
     * Returns whether $vatRates contains a no vat rate.
     *
     * @param float|float[] $vatRates
     *   Either a single vat rate or an array of vat rates.
     *
     * @return bool
     *   True if $vatRates contains a no-vat vat rate, false if it only contains
     *   positive vat rates.
     */
    protected function metaDataHasANoVat(float|array $vatRates): bool
    {
        $vatRates = (array) $vatRates;
        foreach ($vatRates as $vatRate) {
            if ($this->isNoVat($vatRate)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns whether $vatRates contains only no-vat vat rates.
     *
     * @param float|float[] $vatRates
     *   Either a single vat rate or an array of vat rates.
     *
     * @return bool
     *   True if $vatRates contains only no-vat vat rates, false if it contains
     *   at least 1 positive vat rate.
     */
    protected function metaDataHasOnlyNoVat(float|array $vatRates): bool
    {
        $vatRates = (array) $vatRates;
        foreach ($vatRates as $vatRate) {
            if (!$this->isNoVat($vatRate)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Helper method to get the vat rates for the current invoice.
     * - This method contacts the Acumulus server and will cache the results.
     * - The vat rates returned reflect those as they were at the invoice date.
     *
     * @param string $countryCode
     *   The country code of the country to fetch the vat rates for.
     * @param int $region
     *   One of the Api::Region_... constants to filter the vat rates by the
     *   region of the country. Countries may, even at one given date, fiscally
     *   be handled like an EU country OR a country outside the EU. At this
     *   moment, early 2022, only Northern Ireland, part of UK, is such a
     *   "country".
     *
     * @return array[]
     *   A numerically indexed array of "vat info" arrays, each "vat info" array
     *   being a keyed array with keys as defined by
     *   {@see \Siel\Acumulus\ApiClient\Acumulus::getVatInfo()}. The empty array
     *   if no vat rates are kept for the given country and invoice date, i.e.
     *   non EU, non GB countries.
     */
    protected function getVatRatesByCountryAndInvoiceDate(string $countryCode, int $region = Api::Region_NotSet): array
    {
        $countryCode = strtoupper($countryCode);
        $date = $this->getInvoiceDate();
        $date_iso = $date->format(Api::DateFormat_Iso);
        $cacheKey = "$countryCode&$date_iso";
        if (!isset($this->vatRatesCache[$cacheKey])) {
            $result = $this->acumulusApiClient->getVatInfo($countryCode, $date);
            if ($result->hasRealMessages() && $countryCode === 'XI') {
                $result = $this->acumulusApiClient->getVatInfo('GB', $date);
            }
            if ($result->hasRealMessages()) {
                $this->result->addMessages($result->getMessages(Severity::InfoOrWorse));
                $result = [];
            } else {
                $result = $result->getMainAcumulusResponse();
            }
            $this->vatRatesCache[$cacheKey] = $result;
        }
        $result = $this->vatRatesCache[$cacheKey];
        if ($region !== Api::Region_NotSet) {
            $result = array_filter($result, static function ($value) use ($region) {
                return (int) $value[Fld::CountryRegion] === $region;
            });
        }
        return $result;
    }

    /**
     * Returns the invoice date.
     */
    protected function getInvoiceDate(): DateTimeInterface
    {
        return !empty($this->invoice->issueDate)
            ? $this->invoice->issueDate
            : new DateTimeImmutable();
    }

    /**
     * Wrapper around Countries::isNl().
     */
    protected function isNl(): bool
    {
        return $this->countries->isNl($this->invoice->getCustomer()?->getFiscalAddress()->countryCode);
    }

    /**
     * Returns whether the country is an EU country outside the Netherlands.
     *
     * This method determines whether a country is in or outside the EU based on
     * fiscal handling of invoices to customers in that country. If this method
     * returns true, vat type 3 - EU reversed vat - and 6 - foreign EU vat - are
     * allowed.
     *
     * Note: Northern Ireland, part of the UK (country code = GB), is handled
     * differently. This method wil return false for Northern Ireland, but vat
     * type 3 and 6 will be allowed.
     */
    protected function isEu(): bool
    {
        $result = false;
        if (!$this->isNl()) {
            $vatInfos = $this->getVatRatesByCountryAndInvoiceDate($this->invoice->getCustomer()?->getFiscalAddress()->countryCode);
            $regions = array_unique(array_column($vatInfos, Fld::CountryRegion));
            $result = count($regions) === 1 && (int) reset($regions) === Api::Region_EU;
        }
        return $result;
    }

    /**
     * Returns whether the country is the UK, including Northern Ireland, or
     * specifically Northern Ireland (XI), and the invoice date is post Brexit
     * (2021-01-01).
     */
    protected function isUk(): bool
    {
        return in_array(strtoupper($this->invoice->getCustomer()?->getFiscalAddress()->countryCode), ['GB', 'XI']);
    }

    /**
     * Returns whether the address of the customer is in Northern Ireland.
     *
     * - Country code XI is always Northern Ireland
     * - Country code GB only if the postal code starts with BT. See how to
     *   distinguish NI within country code UK:
     *   {@link https://www.webmasterworld.com/forum22/4514.htm}.
     *
     * @noinspection NullPointerExceptionInspection
     *   Customer and fiscal address will be set at this point.
     */
    protected function isNorthernIreland(): bool
    {
        $fiscalAddress = $this->invoice->getCustomer()->getFiscalAddress();
        return strtoupper($fiscalAddress->countryCode) === 'XI'
            || ($this->isUk() && strncasecmp($fiscalAddress->postalCode ?? '', 'BT', 2) === 0);
    }

    /**
     * Returns whether the client is located outside the EU.
     */
    protected function isOutsideEu(): bool
    {
        return !$this->isNl() && !$this->isEu();
    }

    /**
     * Returns whether the client is a company with a vat number.
     */
    protected function isCompany(): bool
    {
        // Note: companies outside EU must also fill in their vat number!? Even
        // if there's no way to check it with a webservice like VIES.
        /** @noinspection NullPointerExceptionInspection */
        return !empty($this->invoice->getCustomer()->getFiscalAddress()->companyName1) && !empty($this->invoice->getCustomer()->vatNumber);
    }

    /**
     * Returns if the vat rate is correct, given its source.
     *
     * @param string $source
     *   The vat rate source, one of the Creator::VatRateSource_... or
     *   Completor::VatRateSource... constants.
     *
     * @return bool
     *   True if the given vat rate source indicates that the vat rate is
     *   correct, false otherwise.
     */
    public static function isCorrectVatRate(string $source): bool
    {
        return in_array($source, self::$CorrectVatRateSources, true);
    }

    /**
     * Returns whether the vat class id denotes vat free.
     *
     * @param int|Line|string $lineOrVatClassId
     *   The vat class to check or an invoice line that may contain the key
     *   Meta::VatClassId that refers to a vat class.
     *
     * @return bool
     *   True if the shop might sell vat free articles and the vat class id is
     *   given and denotes the vat free class (or is left empty), false
     *   otherwise.
     */
    protected function isVatFreeClass(int|Line|string $lineOrVatClassId): bool
    {
        $vatClassId = !is_scalar($lineOrVatClassId) ? $lineOrVatClassId->metadataGet(Meta::VatClassId) : (string) $lineOrVatClassId;
        $shopSettings = $this->config->getShopSettings();
        $vatFreeClass = $shopSettings['vatFreeClass'];
        return $vatClassId === $vatFreeClass;
    }

    /**
     * Returns whether the vat class id denotes the 0% vat rat.
     *
     * @param int|Line|string $lineOrVatClassId
     *   The vat class to check or an invoice line that may contain the key
     *   Meta::VatClassId that refers to a vat class.
     *
     * @return bool
     *   True if the shop might sell 0% vat articles and the vat class id
     *   denotes the 0% vat rate class, false otherwise.
     */
    protected function is0VatClass(int|Line|string $lineOrVatClassId): bool
    {
        $vatClassId = (string) (!is_scalar($lineOrVatClassId) ? $lineOrVatClassId->metadataGet(Meta::VatClassId) : $lineOrVatClassId);
        $shopSettings = $this->config->getShopSettings();
        $zeroVatClass = $shopSettings['zeroVatClass'];
        return $vatClassId === $zeroVatClass;
    }

    /**
     * Returns whether the $vatRate is the 0% or the vat free vat rate.
     */
    public function isNoVat(float|Line $vatRate): bool
    {
        return $this->is0VatRate($vatRate) || $this->isFreeVatRate($vatRate);
    }

    /**
     * Returns whether the $vatRate is the 0% vat rate.
     */
    protected function is0VatRate(float|Line $lineOrVatRate): bool
    {
        $vatRate = !is_scalar($lineOrVatRate) ? $lineOrVatRate->vatRate : $lineOrVatRate;
        return isset($vatRate) && Number::isZero($vatRate);
    }

    /**
     * Returns whether the $vatRate is the vat free vat rate.
     */
    protected function isFreeVatRate(float|Line $lineOrVatRate): bool
    {
        $vatRate = !is_scalar($lineOrVatRate) ? $lineOrVatRate->vatRate : $lineOrVatRate;
        return isset($vatRate) && Number::floatsAreEqual($vatRate, Api::VatFree);
    }

    /**
     * Returns whether the invoice is a vat free invoice.
     *
     * @return bool
     *   True if the invoice is vat free, i.e. has only vat free lines, false
     *   otherwise.
     */
    protected function isVatFreeInvoice(): bool
    {
        foreach ($this->invoice->getLines() as $line) {
            if (!$this->isFreeVatRate($line)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns whether the 0% vat rate is a possible vat rate for this vat type
     * in the given circumstances
     */
    protected function is0VatPossibleForVatType(?int $vatType): bool
    {
        foreach ($this->possibleVatRates as $possibleVatRate) {
            if ($possibleVatRate[Fld::VatType] === $vatType && Number::isZero($possibleVatRate[Fld::VatRate])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the possible natures for the invoice lines.
     *
     * @return int
     *   One of the Config::Nature_... constants, being a bitwise combination
     *   of possible natures (product and service).
     */
    protected function getNature(): int
    {
        $shopSettings = $this->config->getShopSettings();
        /** @var int $nature */
        $nature = $shopSettings['nature_shop'];
        switch ($nature) {
            case Config::Nature_Products:
            case Config::Nature_Services:
                $result = $nature;
                break;
            case Config::Nature_Unknown:
            case Config::Nature_Both:
            default:
                // Look at the lines to determine the possible nature(s).
                // Degenerate case: no lines: return "both" anyway instead of
                // "unknown".
                $result = count($this->invoice->getLines()) === 0
                    ? Config::Nature_Both
                    : Config::Nature_Unknown;
                foreach ($this->invoice->getLines() as $line) {
                    if (!empty($line->nature)) {
                        if ($line->nature === Api::Nature_Product) {
                            $result |= Config::Nature_Products;
                        } elseif ($line->nature === Api::Nature_Service) {
                            $result |= Config::Nature_Services;
                        } else {
                            $result = Config::Nature_Both;
                        }
                    } else {
                        $result = Config::Nature_Both;
                    }
                }
                break;
        }
        return $result;
    }

    /**
     * Returns whether this invoice may get a 0-vat vat type
     *
     * @return bool
     *   True if this invoice may get a 0-vat vat type, false otherwise.
     */
    public function is0VatVatTypePossible(): bool
    {
        return count(array_intersect($this->possibleVatTypes, static::$zeroVatVatTypes)) !== 0;
    }

    /**
     * Returns whether this invoice suffers from the "NL or BE" vat problem.
     *
     * For invoices to EU countries that have the same vat rate(s) as the
     * Netherlands it cannot (always) be decided whether EU or Dutch vat was
     * applied. This will be the case when the web shop data does not allow to
     * uniquely distinguish these vat rates, especially for older orders.
     *
     * @param int[] $possibleVatTypes
     *   the set of possible vat type for each line (the intersection).
     *
     * @return bool
     *   True if vat types 1 and 6 are possible for each line.
     */
    protected function isEuWithSameVatRate(array $possibleVatTypes): bool
    {
        return count($possibleVatTypes) === 2
            && in_array(Api::VatType_National, $possibleVatTypes)
            && in_array(Api::VatType_EuVat, $possibleVatTypes);
    }

    /**
     * Makes the invoice a concept invoice and optionally adds a warning.
     *
     * @param AcumulusObject $acumulusObject
     *   The (sub) array of the Acumulus invoice array for which the warning is
     *   intended. The warning will also be added under a Meta::Warning tag
     * @param string $messageKey
     *   The key of the message to add as warning, or the empty string if no
     *   warning has to be added.
     * @param int $code
     *   The code for this message.
     * @param mixed ...$args
     *   Additional arguments to format the message.
     */
    public function changeInvoiceToConcept(AcumulusObject $acumulusObject, string $messageKey, int $code, mixed ...$args): void
    {
        $pdfMessage = '';
        $invoiceSettings = $this->config->getInvoiceSettings();
        $concept = $invoiceSettings[Fld::Concept];
        if ($concept === Config::Concept_Plugin) {
            $this->invoice->concept = Api::Concept_Yes;
            $emailAsPdfSettings = $this->config->getEmailAsPdfSettings();
            if ($emailAsPdfSettings['emailAsPdf']) {
                $pdfMessage = ' ' . $this->t('message_warning_no_pdf');
            }
        }

        if ($messageKey !== '') {
            $message = $this->t($messageKey) . $pdfMessage;
            if (count($args) !== 0) {
                $message = sprintf($message, ...$args);
            }
            $this->result->createAndAddMessage($message, Severity::Warning, $code);
            /** @noinspection NullPointerExceptionInspection */
            $this->addWarning($acumulusObject, $this->result->getByCode($code)->format(Message::Format_Plain));
        }
    }

    /**
     * Helper method to add a warning to an array.
     *
     * Warnings are placed in the $array under the key Meta::Warning. If no
     * warning is set, $warning is added as a string, otherwise it becomes an
     * array of warnings to which this $warning is added.
     */
    protected function addWarning(AcumulusObject $acumulusObject, string $warning, string $severity = Meta::Warning): void
    {
        $acumulusObject->metadataAdd($severity, $warning);
    }

    /**
     * @param int $year
     *
     * @return int
     *   One of the {@see Completor}::EuSales_... constants.
     */
    protected function getEuSalesReport(int $year): int
    {
        static $reports = [];

        if (!array_key_exists($year, $reports)) {
            $result = $this->acumulusApiClient->reportThresholdEuCommerce($year);
            if ($result->hasError()) {
                $reports[$year] = Completor::EuSales_Unknown;
            } else {
                $euSales = $result->getMainAcumulusResponse();
                if ((int) $euSales['reached'] === 1) {
                    $reports[$year] = Completor::EuSales_Passed;
                } else {
                    $warningPercentage = $this->config->getInvoiceSettings()['euCommerceThresholdPercentage'];
                    $invoiceAmount = $this->invoice->metadataGet(Meta::Totals)->amountEx ?? 0.0;
                    $percentage = ((float) $euSales['nltaxed'] + $invoiceAmount) / ((float) $euSales['threshold']) * 100.0;
                    if ($percentage > 100.0) {
                        $reports[$year] = Completor::EuSales_WillReach;
                    } elseif ($percentage >= $warningPercentage) {
                        $reports[$year] = Completor::EuSales_Warning;
                    } else {
                        $reports[$year] = Completor::EuSales_Safe;
                    }
                }
            }
        }

        return $reports[$year];
    }
}
