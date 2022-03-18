<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Helpers\Countries;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;
use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\Helpers\Severity;

/**
 * The invoice completor class provides functionality to correct and complete
 * invoices before sending them to Acumulus.
 *
 * This class:
 * - Changes the customer into a fictitious client if set so in the config.
 * - Validates the email address: the Acumulus api does not allow an empty email
 *   address (but does allow a non provided email address).
 * - Adds line totals. These are compared with what the shop advertises as order
 *   totals to see if an amount might be missing.
 * - Adds the vat type based on inspection of the completed invoice.
 * - Removes an empty shipping line if set to do so in the config.
 *
 * - Calls the invoice line completor.
 * - Calls the strategy line completor.
 *
 * @todo: this class is too large: place groups of methods in utility classes
 *   like Countries  (isNl(), isEu(), etc.) and "Knowledge" classes,e.g. a vat
 *   type class that knows which vat types are possible in a given situation
 *   and with what rates.
 */
class Completor
{
    public const VatRateSource_Completor_Range = 'completor-range';
    public const VatRateSource_Completor_Lookup = 'completor-lookup';
    public const VatRateSource_Completor_Range_Lookup = 'completor-range-lookup';
    public const VatRateSource_Completor_Range_Lookup_Foreign = 'completor-range-lookup-foreign';
    public const VatRateSource_Completor_Max_Appearing = 'completor-max-appearing';
    public const VatRateSource_Strategy_Completed = 'strategy-completed';
    public const VatRateSource_Copied_From_Children = 'copied-from-children';
    public const VatRateSource_Copied_From_Parent = 'copied-from-parent';
    public const VatRateSource_Corrected_NoVat = 'corrected-no-vat';

    /**
     * @var array
     *   A list of vat rate sources that indicate that the vat rate can be
     *   considered correct.
     */
    protected static $CorrectVatRateSources = [
        Creator::VatRateSource_Exact,
        Creator::VatRateSource_Exact0,
        Creator::VatRateSource_Creator_Lookup,
        self::VatRateSource_Completor_Range,
        self::VatRateSource_Completor_Lookup,
        self::VatRateSource_Completor_Range_Lookup,
        self::VatRateSource_Completor_Range_Lookup_Foreign,
        self::VatRateSource_Completor_Max_Appearing,
        self::VatRateSource_Strategy_Completed,
        self::VatRateSource_Copied_From_Children,
        self::VatRateSource_Copied_From_Parent,
    ];

    /**
     * @var int[]
     *   A list of vat types that allow vat free vat rates.
     */
    protected static $vatTypesAllowingVatFree = [Api::VatType_National, Api::VatType_MarginScheme];

    /**
     * @var int[]
     *   A list of vat types that do not charge vat.
     */
    protected static $zeroVatVatTypes = [Api::VatType_NationalReversed, Api::VatType_EuReversed, Api::VatType_RestOfWorld];

    /** @var \Siel\Acumulus\Config\Config */
    protected $config;

    /** @var \Siel\Acumulus\Helpers\Translator */
    protected $translator;

    /** @var \Siel\Acumulus\Helpers\Log */
    protected $log;

    /** @var \Siel\Acumulus\ApiClient\Acumulus */
    protected $acumulusApiClient;

    /** @var \Siel\Acumulus\Helpers\Countries */
    protected $countries;

    /** @var \Siel\Acumulus\ApiClient\AcumulusResult */
    protected $result;

    /** @var array */
    protected $invoice;

    /** @var Source */
    protected $source;

    /**
     * @var int[]
     *   The list of possible vat types, initially filled with possible vat
     *   types based on client country, invoiceHasLineWithVat(), is_company(),
     *   and the EU vat setting. But then reduced by VAT rates we find on
     *   the order lines.
     */
    protected $possibleVatTypes;

    /**
     * @var array[]
     *   The list of possible vat rates, based on the possible vat types and
     *   extended with the zero rates (0 and vat-free) if they might be
     *   applicable.
     */
    protected $possibleVatRates;

    /** @var \Siel\Acumulus\Invoice\CompletorInvoiceLines */
    protected $LineCompletor = null;

    /** @var \Siel\Acumulus\Invoice\CompletorStrategyLines */
    protected $strategyLineCompletor = null;

    /** @var (string|int)[][] */
    protected $lineTotalsStates;

    /** @var float[][] */
    protected $vatRatesCache = [];

    /**
     * Constructor.
     *
     * @param \Siel\Acumulus\Invoice\CompletorInvoiceLines $completorInvoiceLines
     * @param \Siel\Acumulus\Invoice\CompletorStrategyLines $completorStrategyLines
     * @param \Siel\Acumulus\Helpers\Countries $countries
     * @param \Siel\Acumulus\ApiClient\Acumulus $acumulusApiClient
     * @param \Siel\Acumulus\Config\Config $config
     * @param \Siel\Acumulus\Helpers\Translator $translator
     * @param \Siel\Acumulus\Helpers\Log $log
     */
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
        $invoiceHelperTranslations = new Translations();
        $this->translator->add($invoiceHelperTranslations);

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

    /**
     * Completes the invoice with default settings that do not depend on shop
     * specific data.
     *
     * @param array $invoice
     *   The invoice to complete.
     * @param Source $source
     *   The source object for which this invoice was created.
     * @param \Siel\Acumulus\Invoice\InvoiceAddResult $result
     *   A Result object where local errors and warnings can be added.
     *
     * @return array
     *   The completed invoice.
     */
    public function complete(array $invoice, Source $source, InvoiceAddResult $result): array
    {
        $this->invoice = $invoice;
        $this->source = $source;
        $this->result = $result;

        // Completes the invoice with default settings that do not depend on
        // shop specific data.
        $this->fictitiousClient();
        $this->validateEmail();
        $this->invoiceTemplate();

        // Complete lines as far as they can be completed on their own.
        $this->initPossibleVatTypes();
        $this->initPossibleVatRates();
        $this->convertToEuro();
        $this->invoice = $this->LineCompletor->complete($this->invoice, $this->possibleVatTypes, $this->possibleVatRates);

        $this->checkMissingAmount();

        // Complete strategy lines: those lines that have to be completed based
        // on the whole invoice.
        $this->invoice = $this->strategyLineCompletor->complete($this->invoice, $this->source, $this->possibleVatTypes, $this->possibleVatRates);
        // Check if the strategy phase was successful.
        if ($this->strategyLineCompletor->invoiceHasStrategyLine()) {
            // We did not manage to correct all strategy lines: warn and set the
            // invoice to concept.
            $this->changeInvoiceToConcept($this->invoice[Tag::Customer][Tag::Invoice],'message_warning_strategies_failed', 808);
        }

        // Determine the VAT type for the invoice and warn if multiple vat types
        // are possible.
        $this->completeVatType();
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

        // Massages the metadata before sending the invoice.
        $this->processMetaData();

        return $this->invoice;
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
     * See also: {@see https://wiki.acumulus.nl/index.php?page=facturen-naar-het-buitenland}.
     */
    protected function initPossibleVatTypes()
    {
        $possibleVatTypes = [];
        $shopSettings = $this->config->getShopSettings();
        $margin = $shopSettings['marginProducts'];
        $nature = $this->getNature();

        if (!empty($this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType])) {
            // If shop specific code or an event handler has already set the vat
            // type, we obey so.
            $possibleVatTypes[] = $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType];
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
                // It can be normal vat.
                // @todo: unless we know that is not used.
                $possibleVatTypes[] = Api::VatType_National;
                // Can it be EU vat?
                if ($this->usesEuVat()) {
                    $possibleVatTypes[] = Api::VatType_EuVat;
                }
                // Can it be EU reversed VAT. Note that reversed vat should not
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
                        $this->invoice[Tag::Customer][Tag::CountryCode] = 'XI';
                        // @nth: remove duplication (with case isEu()).
                        // It can be normal vat.
                        $possibleVatTypes[] = Api::VatType_National;
                        // Can it be EU vat?
                        if ($this->usesEuVat()) {
                            $possibleVatTypes[] = Api::VatType_EuVat;
                        }
                        // Can it be EU reversed VAT. Note that reversed vat
                        // should not be used with vat free items.
                        if ($this->isCompany()) {
                            $possibleVatTypes[] = Api::VatType_EuReversed;
                        }
                    }
                    if (($nature & Config::Nature_Services) !== 0) {
                        // Nature = Services => treat Northern Ireland as UK,
                        // thus as rest of world.
                        // Services should use vat type = 1 with vat free.
                        $possibleVatTypes[] = Api::VatType_National;
                    }
                } else {
                    if (($nature & Config::Nature_Products) !== 0) {
                        // Nature = Products =>
                        // - Up to 135 GBP: seller pays VAT (vat type = 7).
                        // - Above 135 GBP: buyer pays VAT, for seller it
                        //   becomes vat type = 4, unless ..., see below at
                        //   outsideEu. However, seller may decide to pay VAT on
                        //   behalf of buyer: vat type = 7.
                        $possibleVatTypes[] = Api::VatType_OtherForeignVat;
                        $possibleVatTypes[] = Api::VatType_National;
                        $possibleVatTypes[] = Api::VatType_RestOfWorld;
                    }
                    if (($nature & Config::Nature_Services) !== 0) {
                        // Nature = Services => treat as outside EU.
                        $possibleVatTypes[] = Api::VatType_National;
                    }
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
        $this->possibleVatTypes = array_unique($possibleVatTypes, SORT_NUMERIC);
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
     * vat rates. A vat rate being an array with keys 'vatrate' and 'vattype'.
     * This is done so to be able to determine to which vat type(s) a vat rate
     * belongs.
     */
    protected function initPossibleVatRates()
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
                        $countryVatRate = $countryVatInfo[Tag::VatRate];
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
                    if ($zeroVatClass != Config::VatClass_NotApplicable) {
                        $vatTypeVatRates[] = 0.0;
                    }
                    // Add vat free rate if:
                    // - selling vat free products/services
                    // - OR (outside EU AND services).
                    if ($vatFreeClass != Config::VatClass_NotApplicable) {
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
                    /** @noinspection DuplicatedCode @todo: remove duplication.*/
                    $countryVatInfos = $this->getVatRatesByCountryAndInvoiceDate($this->invoice[Tag::Customer][Tag::CountryCode], Api::Region_EU);
                    $vatTypeVatRates = [];
                    // Only add those EU vat rates that are possible given
                    // the plugin settings.
                    foreach ($countryVatInfos as $countryVatInfo) {
                        $countryVatRate = $countryVatInfo[Tag::VatRate];
                        if (Number::isZero($countryVatRate)) {
                            // NOTE: we assume here that the 'zeroVatClass'
                            // setting is also set to a vat class when selling
                            // products or services that are 0% in at least one
                            // EU country.
                            if ($zeroVatClass != Config::VatClass_NotApplicable) {
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
                    if ($vatFreeClass != Config::VatClass_NotApplicable) {
                        $vatTypeVatRates[] = Api::VatFree;
                    }
                    break;
                case Api::VatType_OtherForeignVat:
                    /** @noinspection DuplicatedCode @todo: remove duplication.*/
                    $countryVatInfos = $this->getVatRatesByCountryAndInvoiceDate($this->invoice[Tag::Customer][Tag::CountryCode], Api::Region_World);
                    $vatTypeVatRates = [];
                    // Only add those EU vat rates that are possible given
                    // the plugin settings.
                    foreach ($countryVatInfos as $countryVatInfo) {
                        $countryVatRate = $countryVatInfo[Tag::VatRate];
                        if (Number::isZero($countryVatRate)) {
                            // NOTE: we assume here that the 'zeroVatClass'
                            // setting is also set to a vat class when selling
                            // products or services that are 0% in at least one
                            // EU country.
                            if ($zeroVatClass != Config::VatClass_NotApplicable) {
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
            // Convert the list of vat rates to a list of vat rate infos.
            foreach ($vatTypeVatRates as &$vatRate) {
                $vatRate = [Tag::VatRate => $vatRate, Tag::VatType => $vatType];
            }
            /** @noinspection SlowArrayOperationsInLoopInspection */
            $possibleVatRates = array_merge($possibleVatRates, $vatTypeVatRates);
        }
        $this->possibleVatRates = $possibleVatRates;
    }

    /**
     * Anonymize customer if set so.
     *
     * - We don't do this for business clients, only consumers.
     * - We keep the country code as it is needed to determine the vat type.
     */
    protected function fictitiousClient()
    {
        $customerSettings = $this->config->getCustomerSettings();
        if (!$customerSettings['sendCustomer'] && !$this->isCompany()) {
            $keysToKeep = [Tag::CountryCode, Tag::Invoice];
            foreach ($this->invoice[Tag::Customer] as $key => $value) {
                if (!in_array($key, $keysToKeep)) {
                    unset($this->invoice[Tag::Customer][$key]);
                }
            }
            $this->invoice[Tag::Customer][Tag::Email] = $customerSettings['genericCustomerEmail'];
            $this->invoice[Tag::Customer][Tag::ContactStatus] = Api::ContactStatus_Disabled;
            $this->invoice[Tag::Customer][Tag::OverwriteIfExists] = Api::OverwriteIfExists_No;
        }
    }

    /**
     * Validates the email address of the invoice.
     *
     * Validations performed:
     * - Multiple, comma separated, email addresses are not allowed.
     * - Display names (My Name <my.name@example.com>) are not allowed.
     * - The email address may not be empty but may be left out though in which
     *   case a new relation will be created. To prevent both, we use a fake
     *   address AND we will set a warning.
     */
    protected function validateEmail()
    {
        // Check email address.
        if (empty($this->invoice[Tag::Customer][Tag::Email])) {
            $customerSettings = $this->config->getCustomerSettings();
            $this->invoice[Tag::Customer][Tag::Email] = $customerSettings['emailIfAbsent'];
            $this->result->createAndAdd($this->t('message_warning_no_email'), Severity::Warning, 801);
        } else {
            $email = $this->invoice[Tag::Customer][Tag::Email];
            $at = strpos($email, '@');
            // Comma (,) used as separator?
            $comma = strpos($email, ',', $at);
            if ($at < $comma) {
                $email = trim(substr($email, 0, $comma));
            }
            // Semicolon (;) used as separator?
            $semicolon = strpos($email, ';', $at);
            if ($at < $semicolon) {
                $email = trim(substr($email, 0, $semicolon));
            }

            // Display name used in single remaining address?
            if (preg_match('/^(.+?)<([^>]+)>$/', $email, $matches)) {
                $email = trim($matches[2]);
            }
            $this->invoice[Tag::Customer][Tag::Email] = $email;
        }
    }

    /**
     * Fills the invoice template to use when sending an invoice from Acumulus.
     *
     * As getting the payment status right is notoriously hard, we fill this
     * value only here in the completor phase to give users the chance to change
     * the payment status in the acumulus invoice created event.
     */
    protected function invoiceTemplate()
    {
        $invoiceSettings = $this->config->getInvoiceSettings();

        // Acumulus invoice template to use.
        $settingToUse = isset($this->invoice[Tag::Customer][Tag::Invoice][Tag::PaymentStatus])
                        && $this->invoice[Tag::Customer][Tag::Invoice][Tag::PaymentStatus] == Api::PaymentStatus_Paid
                        // 0 (= empty) = use same invoice template as for non paid invoices.
                        && $invoiceSettings['defaultInvoicePaidTemplate'] != 0
            ? 'defaultInvoicePaidTemplate'
            : 'defaultInvoiceTemplate';
        $this->addDefault($this->invoice[Tag::Customer][Tag::Invoice], Tag::Template, $invoiceSettings[$settingToUse]);
    }

    /**
     * Converts amounts to euro if another currency was used.
     *
     * This method only converts amounts at the invoice level. When this method
     * is executed, only the invoice totals are set, the lines totals are not
     * yet set.
     *
     * The line level is handled by the line completor and will be done before
     * the lines totals are calculated.
     */
    protected function convertToEuro()
    {
        if ($this->shouldConvertCurrency($this->invoice)) {
            // Convert all amounts at the invoice level.
            $invoice = &$this->invoice[Tag::Customer][Tag::Invoice];
            $conversionRate = $invoice[Meta::CurrencyRate];
            $this->convertAmount($invoice, Meta::InvoiceAmount, $conversionRate);
            $this->convertAmount($invoice, Meta::InvoiceAmountInc, $conversionRate);
            $this->convertAmount($invoice, Meta::InvoiceVatAmount, $conversionRate);
        }
    }

    /**
     * Checks for a missing amount and handles it.
     */
    protected function checkMissingAmount()
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
     */
    protected function completeLineTotals()
    {
        $linesAmount = 0.0;
        $linesAmountInc = 0.0;
        $linesVatAmount = 0.0;
        $this->lineTotalsStates = [
            'incomplete' => [],
            'equal' => [],
            'differ' => [],
        ];

        $invoiceLines = $this->invoice[Tag::Customer][Tag::Invoice][Tag::Line];
        foreach ($invoiceLines as $line) {
            if (isset($line[Meta::LineAmount])) {
                $linesAmount += $line[Meta::LineAmount];
            } elseif (isset($line[Tag::UnitPrice])) {
                $linesAmount += $line[Tag::Quantity] * $line[Tag::UnitPrice];
            } else {
                $this->lineTotalsStates['incomplete'][Meta::LinesAmount] = Meta::LinesAmount;
            }

            if (isset($line[Meta::LineAmountInc])) {
                $linesAmountInc += $line[Meta::LineAmountInc];
            } elseif (isset($line[Meta::UnitPriceInc])) {
                $linesAmountInc += $line[Tag::Quantity] * $line[Meta::UnitPriceInc];
            } else {
                $this->lineTotalsStates['incomplete'][Meta::LinesAmountInc] = Meta::LinesAmountInc;
            }

            if (isset($line[Meta::LineVatAmount])) {
                $linesVatAmount += $line[Meta::LineVatAmount];
            } elseif (isset($line[Meta::VatAmount])) {
                $linesVatAmount += $line[Tag::Quantity] * $line[Meta::VatAmount];
            } else {
                $this->lineTotalsStates['incomplete'][Meta::LinesVatAmount] = Meta::LinesVatAmount;
            }
        }

        $this->invoice[Tag::Customer][Tag::Invoice][Meta::LinesAmount ] = $linesAmount;
        $this->invoice[Tag::Customer][Tag::Invoice][Meta::LinesAmountInc] = $linesAmountInc;
        $this->invoice[Tag::Customer][Tag::Invoice][Meta::LinesVatAmount] = $linesVatAmount;
        if (!empty($this->lineTotalsStates['incomplete'])) {
            sort($this->lineTotalsStates['incomplete']);
            $this->invoice[Tag::Customer][Tag::Invoice][Meta::LinesIncomplete] = implode(',', $this->lineTotalsStates['incomplete']);
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
        $invoice = $this->invoice[Tag::Customer][Tag::Invoice];
        if (!in_array(Meta::LinesAmount, $this->lineTotalsStates['incomplete'])) {
            if (Number::floatsAreEqual($invoice[Meta::InvoiceAmount], $invoice[Meta::LinesAmount], 0.05)) {
                $this->lineTotalsStates['equal'][Meta::LinesAmount] = Meta::InvoiceAmount;
            } else {
                $this->lineTotalsStates['differ'][Meta::LinesAmount] = $invoice[Meta::InvoiceAmount] - $invoice[Meta::LinesAmount];
            }
        }
        if (!in_array(Meta::LinesAmountInc, $this->lineTotalsStates['incomplete'])) {
            if (Number::floatsAreEqual($invoice[Meta::InvoiceAmountInc], $invoice[Meta::LinesAmountInc], 0.05)) {
                $this->lineTotalsStates['equal'][Meta::LinesAmountInc] = Meta::InvoiceAmountInc;
            } else {
                $this->lineTotalsStates['differ'][Meta::LinesAmountInc] = $invoice[Meta::InvoiceAmountInc] - $invoice[Meta::LinesAmountInc];
            }
        }
        if (!in_array(Meta::LinesVatAmount, $this->lineTotalsStates['incomplete'])) {
            if (Number::floatsAreEqual($invoice[Meta::InvoiceVatAmount], $invoice[Meta::LinesVatAmount], 0.05)) {
                $this->lineTotalsStates['equal'][Meta::LinesVatAmount] = Meta::InvoiceVatAmount;
            } else {
                $this->lineTotalsStates['differ'][Meta::LinesVatAmount] = $invoice[Meta::InvoiceVatAmount] - $invoice[Meta::LinesVatAmount];
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
            $result = $equal === 1 && array_key_exists(Meta::InvoiceVatAmount, $this->lineTotalsStates['differ']) && Number::isZero($invoice[Meta::LinesVatAmount])
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
    protected function addMissingAmountLine()
    {
        $invoice = &$this->invoice[Tag::Customer][Tag::Invoice];

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
                $this->lineTotalsStates['differ'][Meta::LinesAmount] = $this->lineTotalsStates['differ'][Meta::LinesAmountInc] - $this->lineTotalsStates['differ'][Meta::LinesVatAmount];
            }
            if (!array_key_exists(Meta::LinesVatAmount, $this->lineTotalsStates['differ'])) {
                $this->lineTotalsStates['differ'][Meta::LinesVatAmount] = $this->lineTotalsStates['differ'][Meta::LinesAmountInc] - $this->lineTotalsStates['differ'][Meta::LinesAmount];
            }

            // Create line.
            $missingAmount = $this->lineTotalsStates['differ'][Meta::LinesAmount];
            $missingVatAmount = $this->lineTotalsStates['differ'][Meta::LinesVatAmount];
            $countLines = count($invoice[Tag::Line]);
            if ($this->source->getType() === Source::CreditNote) {
                $product = $this->t('refund_adjustment');
            } elseif ($this->lineTotalsStates['differ'][Meta::LinesAmount] < 0.0) {
                $product = $this->t('discount_adjustment');
            } else {
                $product = $this->t('fee_adjustment');
            }

            $line = [
                    Tag::Product => $product,
                    Tag::Quantity => 1,
                    Tag::UnitPrice => $missingAmount,
            ];
            $line += Creator::getVatRangeTags($missingVatAmount, $missingAmount, $countLines * 0.02, $countLines * 0.02);
            $line[Meta::LineType] = Creator::LineType_Corrector;
            // Correct and add this line (round of correcting has already been
            // executed).
            if ($line[Meta::VatRateSource] === Creator::VatRateSource_Calculated) {
                $line = $this->LineCompletor->correctVatRateByRange($line);
            }
            $invoice[Tag::Line][] = $line;

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
     */
    protected function completeVatType()
    {
        $invoice = &$this->invoice[Tag::Customer][Tag::Invoice];
        $this->checkForKnownVatType();
        // If shop specific code or an event handler has already set the vat
        // type, we don't change it.
        if (empty($invoice[Tag::VatType])) {
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
                    //   country ..?' or 'Were there recent changes in tax
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
                    $invoice[Tag::VatType] = reset($this->possibleVatTypes);
                    $message = 'message_warning_no_vattype_at_all';
                    $code = 804;
                } elseif (count($vatTypeInfo['union']) === 1) {
                    // One or more lines could be matched with exactly 1 vat
                    // type, but not all lines.
                    // Possible causes:
                    // - Non-matching lines have no vat. Message: 'Manual line
                    //   entered without vat' or 'Check vat settings on those
                    //   products.'.
                    // - Non matching lines have vat. Message: 'Manual line
                    //   entered with incorrect vat' or 'Check vat settings on
                    //   those products.'.
                    $invoice[Tag::VatType] = reset($vatTypeInfo['union']);
                    $message = 'message_warning_no_vattype_incorrect_lines';
                    $code = 812;
                } /** @noinspection PhpSeparateElseIfInspection */ else {
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
                    //   costprice = 0 to all normal lines.
                    if (in_array(Api::VatType_MarginScheme, $vatTypeInfo['union'])) {
                        $invoice[Tag::VatType] = Api::VatType_MarginScheme;
                        $invoice[Meta::VatTypeSource] = 'Completor::completeVatType: Convert all lines to margin scheme';
                    } else {
                        // Take the first vat type as a guess but add a warning.
                        $invoice[Tag::VatType] = reset($vatTypeInfo['union']);
                        $message = 'message_warning_no_vattype_must_split';
                        $code = 806;
                    }
                }
            } elseif (count($vatTypeInfo['intersection']) === 1) {
                // Exactly 1 vat type was found to be possible for all lines:
                // use that one as the vat type for the invoice.
                $invoice[Tag::VatType] = reset($vatTypeInfo['intersection']);
                $invoice[Meta::VatTypeSource] = 'Completor::completeVatType: Only one choice fits all';
            } else {
                // Multiple vat types were found to be possible for all lines:
                // Guess which one to take or add a warning.
                // Possible causes:
                // - Client country has same VAT rates as the Netherlands and
                //   shop uses foreign and NL VAT rates. Solvable by correct
                //   shop settings.
                // - Invoice has no vat and the client is outside the EU, and it
                //   is unknown whether the invoice lines contain services or
                //   goods. Perhaps solvable by correct shop settings.
                // - Margin invoice: all lines that have a cost price will
                //   probably also satisfy the normal vat. This is solvable by
                //   making it a margin scheme invoice and adding cost price = 0
                //   to all normal lines.
                $this->guessVatType($vatTypeInfo['intersection']);
                if (empty($invoice[Tag::VatType])) {
                    if (in_array(Api::VatType_MarginScheme, $vatTypeInfo['union'])) {
                        $invoice[Tag::VatType] = Api::VatType_MarginScheme;
                    } else {
                        // Take the first vat type as a guess but add a warning.
                        $invoice[Tag::VatType] = reset($vatTypeInfo['intersection']);
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
            $invoice[Meta::VatTypesPossibleInvoice] = implode(',', $this->possibleVatTypes);
            $invoice[Meta::VatTypesPossibleInvoiceLinesIntersection] = implode(',', $vatTypeInfo['intersection']);
            $invoice[Meta::VatTypesPossibleInvoiceLinesUnion] = implode(',', $vatTypeInfo['union']);
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
     *   array values
     */
    protected function getInvoiceLinesVatTypeInfo(): array
    {
        $list = [];
        $union = [];
        $intersection = null;
        foreach ($this->invoice[Tag::Customer][Tag::Invoice][Tag::Line] as $index => &$line) {
            if ($this->isCorrectVatRate($line[Meta::VatRateSource])) {
                $possibleLineVatTypes = [];
                foreach ($this->possibleVatRates as $vatRateInfo) {
                    $vatRate = $vatRateInfo['vatrate'];
                    $vatType = $vatRateInfo['vattype'];
                    // We should treat 0 and vat free vat rates as equal as
                    // they are not yet corrected.
                    $equal = Number::floatsAreEqual($vatRate, $line[Tag::VatRate]);
                    $bothZeroOrVatFree = $this->isNoVat($vatRate) && $this->isNoVat($line[Tag::VatRate]);
                    if ($equal || $bothZeroOrVatFree) {
                        // We have a possibly matching vat type. Perform some
                        // additional checks before we really add it as a match:
                        $doAdd = true;

                        // 1) Vat type margin scheme requires a cost price.
                        if ($vatType === Api::VatType_MarginScheme) {
                            if (empty($line[Tag::CostPrice])) {
                                $doAdd = false;
                            }
                        }

                        // 2) If this is a 0 vat rate while the lookup vat rate
                        //   or class, if available, is not, it must be a no vat
                        //   invoice:
                        //   - one of the no-vat vat types.
                        //   - vat type national with client outside EU.
                        if ($this->lineHasNoVat($line)) {
                            $zeroOrVatFreeClass = $this->is0VatClass($line) || $this->isVatFreeClass($line);
                            $zeroOrVatFreeRate = !empty($line[Meta::VatRateLookup]) && $this->metaDataHasANoVat($line[Meta::VatRateLookup]);
                            if (!($zeroOrVatFreeClass || $zeroOrVatFreeRate)) {
                                // This article cannot be intrinsically 0% or
                                // vat free. So the vat type must be a vat type
                                // allowing no vat, i.e. a no-vat vat type or
                                // national vat for a customer outside the EU.
                                if (!in_array($vatType, static::$zeroVatVatTypes) && !($vatType === Api::VatType_National && $this->isOutsideEu())) {
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
                            if ($this->isOutsideEu() && $line[Meta::LineType] === Creator::LineType_OrderItem && !empty($line[Tag::Nature])) {
                                if ($vatType === Api::VatType_National && $line[Tag::Nature] === Api::Nature_Product) {
                                    $doAdd = false;
                                }
                                if ($vatType === Api::VatType_RestOfWorld && $line[Tag::Nature] === Api::Nature_Service) {
                                    $doAdd = false;
                                }
                            }
                        }

                        // 3) In EU: If the vat class is known and denotes EU
                        //    vat, we do not add the Dutch vat type.
                        if ($this->isEu() && !empty($line[Meta::VatClassId]) && $this->isEuVatClass($line[Meta::VatClassId])) {
                            if ($vatType === Api::VatType_National) {
                                $doAdd = false;
                            }
                        }

                        // 4) If the vat class is known and does not denote EU
                        //   vat, we do not add the EU vat type.
                        //   Note that as this can prevent finding vat type 6
                        //   when there's a fee line at NL vat rate which
                        //   happens to be the foreign vat rate as well, we only
                        //   do this for item lines.
                        if ($line[Meta::LineType] === Creator::LineType_OrderItem && !empty($line[Meta::VatClassId]) && !$this->isEuVatClass($line[Meta::VatClassId])) {
                            if ($vatType === Api::VatType_EuVat) {
                                $doAdd = false;
                            }
                        }

                        if ($doAdd) {
                            // Ensure unique entries
                            $possibleLineVatTypes[$vatType] = $vatType;
                        }
                    }
                }
                // Add meta info to Acumulus invoice.
                $possibleLineVatTypes = array_values($possibleLineVatTypes);
                $line[Meta::VatTypesPossible] = implode(',', $possibleLineVatTypes);
                // Add to result, union and intersection.
                $list[$index] = $possibleLineVatTypes;
                /** @noinspection SlowArrayOperationsInLoopInspection */
                $union = array_merge($union, $possibleLineVatTypes);
                $intersection = $intersection !== null ? array_intersect($intersection, $possibleLineVatTypes) : $possibleLineVatTypes;
            }
        }

        // Union can obviously contain double results.
        $list['union'] = array_unique($union);
        // Intersection can contain double results due to handling 0% and
        // vat-free as being the same (as they have not yet been corrected).
        $list['intersection'] = $intersection !== null ? array_unique($intersection) : [];
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
    protected function checkForKnownVatType()
    {
        $possibleVatTypes = $this->possibleVatTypes;
        sort($possibleVatTypes, SORT_NUMERIC);
        if (count($possibleVatTypes) === 1) {
            // Rule 1.
            $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType] = reset($possibleVatTypes);
            $this->invoice[Tag::Customer][Tag::Invoice][Meta::VatTypeSource] = 'Completor::checkForKnownVatType: only 1 possible vat type';
//        } elseif ($possibleVatTypes == [Api::VatType_National, Api::VatType_EuReversed]) {
            // @todo: re-enable if we can determine that the shop only sells vat free services.
//            $shopSettings = $this->config->getShopSettings();
//            $vatFreeProducts = $shopSettings['vatFreeProducts'];
//            $nature = $shopSettings['nature_shop'];
//            if ($vatFreeProducts == Config::VatFreeProducts_Only && $nature == Config::Nature_Services) {
//                // Rule 2.
//                $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType] = Api::VatType_National;
//                $this->invoice[Tag::Customer][Tag::Invoice][Meta::VatTypeSource] = 'Completor::checkForKnownVatType: vat free services only';
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
    protected function guessVatType(array $possibleVatTypes)
    {
        sort($possibleVatTypes, SORT_NUMERIC);
        if ($possibleVatTypes == [Api::VatType_National, Api::VatType_EuReversed]) {
            // The invoice does not have vat and no vat class refers to only >0
            // vat rates (but could be unknown). Reversed vat would do but if we
            // really only have vat-free services, reversed vat would make it 0%
            // instead of vat-free, which we should not want because of what the
            // tax office says to do in this situation: "... U mag op de factuur
            // niet vermelden dat de btw is verlegd, maar in plaats daarvan
            // geeft u aan dat de dienst in het land van uw afnemer onder een
            // vrijstelling of het 0%-tarief valt."
            $allItemsVatFree = true;
            $allItemsService = true;
            foreach ($this->invoice[Tag::Customer][Tag::Invoice][Tag::Line] as $line) {
                if (!empty($line[Meta::VatClassId])) {
                    if (!$this->isVatFreeClass($line[Meta::VatClassId])) {
                        $allItemsVatFree = false;
                        break;
                    }

                } elseif (!empty($line[Meta::VatRateLookup])) {
                    if ($this->metaDataHasOnlyNoVat($line[Meta::VatRateLookup])) {
                        $allItemsVatFree = null;
                        break;
                    }
                } else {
                    $allItemsVatFree = null;
                    break;
                }

                if (empty($line[Tag::Nature])) {
                    // We have a possible non-service item line: do not
                    // choose.
                    $allItemsService = null;
                    break;
                } elseif ($line[Tag::Nature] !== Api::Nature_Service) {
                    // We have a non-service item line: do not choose.
                    $allItemsService = false;
                    break;
                }
            }
            if ($allItemsVatFree && $allItemsService) {
                $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType] = Api::VatType_National;
                $this->invoice[Tag::Customer][Tag::Invoice][Meta::VatTypeSource] = 'Completor::guessVatType: all items are vat free services';
            } elseif ($allItemsService === false) {
                $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType] = Api::VatType_EuReversed;
                $this->invoice[Tag::Customer][Tag::Invoice][Meta::VatTypeSource] = 'Completor::guessVatType: at least 1 item is not a service';
            }
        }
    }

    /**
     * Adds the field 'vattypeid' to the customer part of the invoice.
     *
     * - If shop specific code or an event handler has already set the vat type
     *   id, we don't change it.
     * - Non companies are always 1 - private.
     * - Companies will get 2 - business unless we can determine that the
     *   invoice targets a vat exempt company: the company is EU (outside NL)
     *   and the invoice type is not EU reversed and the invoice is not vat-free
     *   as vat-free trumps reversed vat.
     */
    protected function completeVatTypeId()
    {
        $vatTypeId = $this->invoice[Tag::Customer][Tag::VatTypeId];
        if (empty($vatTypeId)) {
            $vatTypeId = $this->isCompany() ? Api::VatTypeId_Business : Api::VatTypeId_Private;
            if ($this->isCompany() && $this->isEu()) {
                if (!empty($this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType])
                        && $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType] !== Api::VatType_EuReversed
                        && !$this->isVatFreeInvoice()) {
                    $vatTypeId = Api::VatTypeId_Private;
                }
            }
        }
        $this->invoice[Tag::Customer][Tag::VatTypeId] = $vatTypeId;
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
    protected function correctMarginInvoice()
    {
        if (isset($this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType]) && $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType] === Api::VatType_MarginScheme) {
            foreach ($this->invoice[Tag::Customer][Tag::Invoice][Tag::Line] as &$line) {
                // For margin invoices, Acumulus expects the unit price to be
                // the sales price, i.e. the price the client pays. So we set
                // 'unitprice' to 'unitpriceinc'.
                // Non margin lines may "officially" not appear on margin
                // invoices, so we turn them into margin lines by adding a
                // 'costprice' of 0 and also setting 'unitprice' to
                // 'unitpriceinc'.
                if (!isset($line[Tag::CostPrice])) {
                    // "Normal" line: set costprice as 0.
                    $line[Tag::CostPrice] = 0.0;
                }
                // Add "marker" tag (for debug purposes) for this correction.
                $line[Meta::RecalculateOldPrice] = $line[Tag::UnitPrice];
                // Change 'unitprice' tag to include VAT.
                if (isset($line[Meta::UnitPriceInc])) {
                    $line[Tag::UnitPrice] = $line[Meta::UnitPriceInc];
                } elseif (isset($line[Meta::VatAmount])) {
                    $line[Tag::UnitPrice] += $line[Meta::VatAmount];
                } elseif (isset($line[Tag::VatRate])) {
                    $line[Tag::UnitPrice] += $line[Tag::VatRate]/100.0 * ($line[Tag::UnitPrice] - $line[Tag::CostPrice]);
                } //else {
                    // Impossible to correct the 'unitprice'. Probably all
                    // strategies failed, so the invoice should already
                    // have a warning.
                //   }
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
     * - Small companies that use the KOR.
     * - Vat free products and services, e.g. care, education (vat type = 1 or
     *   5).
     * - Services invoiced to companies outside the EU (vat type = 1).
     * - Digital services outside the EU, consumers or companies (vat type = 1).
     *
     * However, both will typically be stored as a €0,- amount or 0% rate. To
     * correct these line, we use the shop settings about vat classes used for
     * 0% and vat free; the vat class of the product; and the vat type.
     *
     * Note: to do this correctly, especially choosing between vat type 1 and 4
     * for invoices outside the EU, we should also be able to distinguish
     * between services and products. For that, the shop should only sell
     * products or only services, or the nature field should be filled in.
     * If not filled in, we act as if the line invoices a product.
     *
     * See:
     * - {@see https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/tarieven_en_vrijstellingen/}
     * - {@see https://wiki.acumulus.nl/index.php?page=facturen-naar-het-buitenland}:
     */
    protected function correctNoVatLines()
    {
        $vatType = $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType] ?? null;
        foreach ($this->invoice[Tag::Customer][Tag::Invoice][Tag::Line] as &$line) {
            if ($this->isFreeVatRate($line)) {
                // Change if the vat class indicates a 0% vat-rate or if vat
                // free is not allowed for the vat type
                if ($this->is0VatClass($line) || !in_array($vatType, static::$vatTypesAllowingVatFree))
                {
                    $line[Tag::VatRate] = 0.0;
                    $line[Meta::VatRateSource] .= ',' . self::VatRateSource_Corrected_NoVat;
                }
            }
            if ($this->is0VatRate($line)) {
                // Change if the vat class indicates a vat free rate and vat
                // free is allowed for the vat type.
                if ($this->isVatFreeClass($line) && in_array($vatType, static::$vatTypesAllowingVatFree)) {
                        $line[Tag::VatRate] = Api::VatFree;
                    $line[Meta::VatRateSource] .= ',' . self::VatRateSource_Corrected_NoVat;
                }
            }
        }
    }

    /**
     * Removes an empty shipping line (if so configured).
     */
    protected function removeEmptyShipping()
    {
        $invoiceSettings = $this->config->getInvoiceSettings();
        if (!$invoiceSettings['sendEmptyShipping']) {
            $this->invoice[Tag::Customer][Tag::Invoice][Tag::Line] = array_filter($this->invoice[Tag::Customer][Tag::Invoice][Tag::Line],
                function ($line) {
                    return $line[Meta::LineType] !== Creator::LineType_Shipping || !Number::isZero($line[Tag::UnitPrice]);
                });
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
    protected function checkEuCommerceThreshold()
    {
        $warningPercentage = $this->config->getInvoiceSettings()['euCommerceThresholdPercentage'];
        $invoice = &$this->invoice[Tag::Customer][Tag::Invoice];
        if (is_float($warningPercentage)
            && $this->source->getType() !== Source::CreditNote
            && $this->isEu()
            && isset($invoice[Tag::VatType])
            && $invoice[Tag::VatType] === Api::VatType_National
        ) {
            $year = (int) substr($this->getInvoiceDate(), 0, 4);
            $result = $this->acumulusApiClient->reportThresholdEuCommerce($year);
            if (!$result->hasError()) {
                $euCommerceReport = $result->getResponse();
                if ($euCommerceReport['reached'] == 1) {
                    $this->changeInvoiceToConcept($invoice, 'eu_commerce_threshold_passed', 830);
                } else {
                    $invoiceAmount = $invoice[Meta::InvoiceAmount];
                    $percentage = ((float) $euCommerceReport['nltaxed'] + (float) $invoiceAmount) / ((float) $euCommerceReport['threshold']) * 100.0;
                    if ($percentage > 100.0) {
                        $this->changeInvoiceToConcept($invoice, 'eu_commerce_threshold_will_pass', 831);
                    } elseif ($percentage > $warningPercentage) {
                        // Send mail with notice, xml message will not be added
                        // if there are no warnings or worse.
                        $this->result->createAndAdd(
                            sprintf($this->t('eu_commerce_threshold_warning'), $percentage),
                            Severity::Notice,
                            832
                        );
                    }
                }
            }
        }
    }

    /**
     * Processes metadata before sending the invoice.
     *
     * Currently, the following processing is done:
     * - Meta::Warning, Meta::VatRateLookup, Meta::VatRateLookupLabel,
     *   Meta::FieldsCalculated, Meta::VatRateLookupMatches, and
     *   Meta::VatRateRangeMatches are converted to a json string if they are an
     *   array.
     */
    protected function processMetaData()
    {
        if (isset($this->invoice[Tag::Customer][Meta::Warning]) && is_array($this->invoice[Tag::Customer][Meta::Warning])) {
            $this->invoice[Tag::Customer][Meta::Warning] = json_encode($this->invoice[Tag::Customer][Meta::Warning]);
        }
        if (isset($this->invoice[Tag::Customer][Tag::Invoice][Meta::Warning]) && is_array($this->invoice[Tag::Customer][Tag::Invoice][Meta::Warning])) {
            $this->invoice[Tag::Customer][Tag::Invoice][Meta::Warning] = json_encode($this->invoice[Tag::Customer][Tag::Invoice][Meta::Warning]);
        }
        foreach ($this->invoice[Tag::Customer][Tag::Invoice][Tag::Line] as &$line) {
            if (isset($line[Meta::VatRateLookup]) && is_array($line[Meta::VatRateLookup])) {
                $line[Meta::VatRateLookup] = json_encode($line[Meta::VatRateLookup]);
            }
            if (isset($line[Meta::VatRateLookupLabel]) && is_array($line[Meta::VatRateLookupLabel])) {
                $line[Meta::VatRateLookupLabel] = json_encode($line[Meta::VatRateLookupLabel]);
            }
            if (isset($line[Meta::FieldsCalculated]) && is_array($line[Meta::FieldsCalculated])) {
                $line[Meta::FieldsCalculated] = json_encode(array_unique($line[Meta::FieldsCalculated]));
            }
            if (isset($line[Meta::VatRateLookupMatches]) && is_array($line[Meta::VatRateLookupMatches])) {
                $line[Meta::VatRateLookupMatches] = json_encode($line[Meta::VatRateLookupMatches]);
            }
            if (isset($line[Meta::VatRateRangeMatches]) && is_array($line[Meta::VatRateRangeMatches])) {
                $line[Meta::VatRateRangeMatches] = json_encode($line[Meta::VatRateRangeMatches]);
            }
            if (isset($line[Meta::Warning]) && is_array($line[Meta::Warning])) {
                $line[Meta::Warning] = json_encode($line[Meta::Warning]);
            }
        }
    }

    /**
     * Returns whether the given line has a no-vat vat rate (0% or vat free).
     *
     * @param array $line
     *   The invoice line.
     *
     * @return bool
     *   True if the given line has a no-vat vat rate (0% or vat free), false
     *   if it has a positive vat rate.
     */
    protected function lineHasNoVat(array $line): bool
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
     *   True if $vatRates contains a no vat vat-rate, false if it only contains
     *   positive vat rates.
     */
    protected function metaDataHasANoVat($vatRates): bool
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
    protected function metaDataHasOnlyNoVat($vatRates): bool
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
        $cacheKey = "$countryCode&$date";
        if (!isset($this->vatRatesCache[$cacheKey])) {
            $result = $this->acumulusApiClient->getVatInfo($countryCode, $date);
            if ($result->hasRealMessages() && $countryCode === 'XI') {
                $result = $this->acumulusApiClient->getVatInfo('GB', $date);
            }
            if ($result->hasRealMessages()) {
                $this->result->addMessages($result->getMessages(Severity::InfoOrWorse));
                $result = [];
            } else {
                $result = $result->getResponse();
            }
            $this->vatRatesCache[$cacheKey] = $result;
        }
        $result = $this->vatRatesCache[$cacheKey];
        if ($region !== Api::Region_NotSet) {
            $result = array_filter($result, function($value) use ($region) {
                return $value['countryregion'] == $region;
            });
        }
        return $result;
    }

    /**
     * Returns the invoice date in the iso yyyy-mm-dd format.
     *
     * @return string
     *   The invoice date in the iso yyyy-mm-dd format.
     */
    protected function getInvoiceDate(): string
    {
        return !empty($this->invoice[Tag::Customer][Tag::Invoice][Tag::IssueDate])
            ? $this->invoice[Tag::Customer][Tag::Invoice][Tag::IssueDate]
            : date(Api::DateFormat_Iso);
    }

    /**
     * Wrapper around Countries::isNl().
     */
    protected function isNl(): bool
    {
        return $this->countries->isNl($this->invoice[Tag::Customer][Tag::CountryCode]);
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
            $vatInfos = $this->getVatRatesByCountryAndInvoiceDate($this->invoice[Tag::Customer][Tag::CountryCode]);
            $regions = array_unique(array_column($vatInfos, Tag::CountryRegion));
            $result = count($regions) === 1 && reset($regions) == Api::Region_EU;
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
        return in_array(strtoupper($this->invoice[Tag::Customer][Tag::CountryCode]), ['GB', 'XI']);
    }

    /**
     * Returns whether the address of the customer is in Northern Ireland.
     *
     * - Country code XI is always Northern Ireland
     * - Country code GB only if the postal code starts with BT. See how to
     *   distinguish NI within country code UK:
     *   {@see https://www.webmasterworld.com/forum22/4514.htm}.
     */
    protected function isNorthernIreland(): bool
    {
        return strtoupper($this->invoice[Tag::Customer][Tag::CountryCode]) === 'XI'
               || (
                   $this->isUk()
                   && isset($this->invoice[Tag::Customer][Tag::PostalCode])
                   && strtoupper(substr($this->invoice[Tag::Customer][Tag::PostalCode], 0, strlen('BT'))) === 'BT'
                  );
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
        return !empty($this->invoice[Tag::Customer][Tag::CompanyName1]) && !empty($this->invoice[Tag::Customer][Tag::VatNumber]);
    }

    /**
     * Returns whether the amounts in the invoice are in another currency.
     *
     * The amounts in te invoice are to be converted if:
     * - All currency meta tags are set.
     * - The "currency rate" does not equal 1.0, otherwise converting would
     *   result in the same amounts.
     * - The meta tag "do convert" equals "currency !== 'EUR'".
     *
     * @param array $invoice
     *   The invoice (starting with the customer part).
     *
     * @return bool
     *   True if the invoice uses another currency, false otherwise.
     */
    public function shouldConvertCurrency(array &$invoice): bool
    {
        $invoicePart = &$invoice[Tag::Customer][Tag::Invoice];
        $currencyMetaAvailable = isset($invoicePart[Meta::Currency])
            && isset($invoicePart[Meta::CurrencyRate])
            && isset($invoicePart[Meta::CurrencyDoConvert]);
        $shouldConvert = $currencyMetaAvailable
            && !Number::floatsAreEqual($invoicePart[Meta::CurrencyRate], 1.0, 0.0001);
        if ($shouldConvert) {
            if ($invoicePart[Meta::Currency] !== 'EUR') {
                // Order/refund is not in euro's: convert if amounts are stored
                // in the order's currency, not the shop's default currency
                // (which should be EUR).
                $shouldConvert = $invoicePart[Meta::CurrencyDoConvert];
                $invoicePart[Meta::CurrencyRateInverted] = false;
            } else {
                // Order/refund is in euro's but that is not the shop's default:
                // convert if the amounts are in the shop's default currency,
                // not the order's currency (which is EUR).
                $shouldConvert = !$invoicePart[Meta::CurrencyDoConvert];
                // Invert the rate only once, even if this method may be called
                // multiple times per invoice.
                if (!isset($invoicePart[Meta::CurrencyRateInverted])) {
                    $invoicePart[Meta::CurrencyRateInverted] = true;
                    $invoicePart[Meta::CurrencyRate] = 1.0 / (float) $invoicePart[Meta::CurrencyRate];
                }
            }
        }
        return $shouldConvert;
    }

    /**
     * Helper method to convert an amount field to euros.
     *
     * @return bool
     *   Whether the amount was converted.
     */
    public function convertAmount(array &$array, string $key, float $conversionRate): bool
    {
        if (!empty($array[$key]) && !empty($conversionRate)) {
            $array[$key] = (float) $array[$key] / $conversionRate;
            return true;
        }
        return false;
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
        return in_array($source, self::$CorrectVatRateSources);
    }

    /**
     * Returns whether the shop uses EU vat.
     *
     * @return bool
     *   True if the shop might sell using EU vat, false otherwise.
     *
     * @todo: this and the following methods are actually config related
     *   questions and thus should be part of Config
     */
    public function usesEuVat(): bool
    {
        $shopSettings = $this->config->getShopSettings();
        $foreignVatEuClasses = $shopSettings['euVatClasses'];
        /** @noinspection PhpStrictComparisonWithOperandsOfDifferentTypesInspection  error in type engine */
        return reset($foreignVatEuClasses) !== Config::VatClass_NotApplicable;
    }

    /**
     * Returns whether the vat class id denotes EU vat.
     *
     * @param int|string $vatClassId
     *   The vat class to check.
     *
     * @return bool
     *   True if the vat class id denotes an EU vat class, false otherwise.
     */
    public function isEuVatClass($vatClassId): bool
    {
        $shopSettings = $this->config->getShopSettings();
        $foreignVatEuClasses = $shopSettings['euVatClasses'];
        return in_array($vatClassId, $foreignVatEuClasses);
    }

    /**
     * Returns whether the vat class id denotes vat free.
     *
     * @param int|string|array $vatClassId
     *   The vat class to check or an invoice line that may contain the key
     *   Meta::VatClassId that refers to a vat class.
     *
     * @return bool
     *   True if the shop might sell vat free articles and the vat class id is
     *   given and denotes the vat free class (or is left empty), false
     *   otherwise.
     */
    protected function isVatFreeClass($vatClassId): bool
    {
        if (is_array($vatClassId)) {
            $vatClassId = array_key_exists(Meta::VatClassId, $vatClassId) ? $vatClassId[Meta::VatClassId] : null;
        }
        $shopSettings = $this->config->getShopSettings();
        $vatFreeClass = $shopSettings['vatFreeClass'];
        return $vatClassId == $vatFreeClass;
    }

    /**
     * Returns whether the vat class id denotes the 0% vat rat.
     *
     * @param int|string|array $vatClassId
     *   The vat class to check or an invoice line that may contain the key
     *   Meta::VatClassId that refers to a vat class.
     *
     * @return bool
     *   True if the shop might sell 0% vat articles and the vat class id
     *   denotes the 0% vat rate class, false otherwise.
     */
    protected function is0VatClass($vatClassId): bool
    {
        if (is_array($vatClassId)) {
            $vatClassId = array_key_exists(Meta::VatClassId, $vatClassId) ? $vatClassId[Meta::VatClassId] : null;
        }
        $shopSettings = $this->config->getShopSettings();
        $zeroVatClass = $shopSettings['zeroVatClass'];
        return $vatClassId == $zeroVatClass;
    }

    /**
     * Returns whether the $vatRate is the 0% or the vat free vat rate.
     *
     * @param float|array $vatRate
     *   A float (or numeric string), or an array containing a Tag::VatRate key
     *   and that contains a vat rate.
     *
     * @return bool
     *   True if $vatRate is the 0% or the vat free vat rate, false otherwise.
     */
    public function isNoVat($vatRate): bool
    {
        return $this->is0VatRate($vatRate) || $this->isFreeVatRate($vatRate);
    }

    /**
     * Returns whether the $vatRate is the 0% or the vat free vat rate.
     *
     * @param float|array $vatRate
     *   A float (or numeric string), or an array containing a Tag::VatRate key
     *   and that contains a vat rate.
     *
     * @return bool
     *   True if $vatRate is the 0% or the vat free vat rate, false otherwise.
     */
    protected function is0VatRate($vatRate): bool
    {
        if (is_array($vatRate)) {
            $vatRate = $vatRate[Tag::VatRate] ?? null;
        }
        return isset($vatRate) && Number::isZero($vatRate);
    }

    /**
     * Returns whether the $vatRate is the 0% or the vat free vat rate.
     *
     * @param float|array $vatRate
     *   A float (or numeric string), or an array containing a Tag::VatRate key
     *   and that contains a vat rate.
     *
     * @return bool
     *   True if $vatRate is the 0% or the vat free vat rate, false otherwise.
     */
    protected function isFreeVatRate($vatRate): bool
    {
        if (is_array($vatRate)) {
            $vatRate = $vatRate[Tag::VatRate] ?? null;
        }
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
        foreach ($this->invoice[Tag::Customer][Tag::Invoice][Tag::Line] as $line) {
            if (!$this->isFreeVatRate($line)) {
                return false;
            }
        }
        return true;
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
                $result = count($this->invoice[Tag::Customer][Tag::Invoice][Tag::Line]) === 0 ? Config::Nature_Both : Config::Nature_Unknown;
                foreach ($this->invoice[Tag::Customer][Tag::Invoice][Tag::Line] as $line) {
                    if (!empty($line[Tag::Nature])) {
                        if ($line[Tag::Nature] === Api::Nature_Product) {
                            $result |= Config::Nature_Products;
                        } elseif ($line[Tag::Nature] === Api::Nature_Service) {
                            $result |= Config::Nature_Services;
                        } else {
                            $result = Config::Nature_Both;
                        }
                    }  else {
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
     * Makes the invoice a concept invoice and optionally adds a warning.
     *
     * @param array $array
     *   The (sub) array of the Acumulus invoice array for which the warning is
     *   intended. The warning will also be added under a Meta::Warning tag
     * @param string $messageKey
     *   The key of the message to add as warning, or the empty string if no
     *   warning has to be added.
     * @param int $code
     *   The code for this message.
     * @param string ...$args
     *   Additional arguments to format the message.
     */
    public function changeInvoiceToConcept(array &$array, string $messageKey, int $code, string ...$args)
    {
        $pdfMessage = '';
        $invoiceSettings = $this->config->getInvoiceSettings();
        $concept = $invoiceSettings['concept'];
        if ($concept == Config::Concept_Plugin) {
            $this->invoice[Tag::Customer][Tag::Invoice][Tag::Concept] = Api::Concept_Yes;
            $emailAsPdfSettings = $this->config->getEmailAsPdfSettings();
            if ($emailAsPdfSettings['emailAsPdf']) {
                $pdfMessage = ' ' . $this->t('message_warning_no_pdf');
            }
        }

        if ($messageKey !== '') {
            $message = $this->t($messageKey) . $pdfMessage;
            if (!empty($args)) {
                $message = sprintf($message, ...$args);
            }
            $this->result->createAndAdd($message, Severity::Warning, $code);
            $this->addWarning($array, $this->result->getByCode($code)->format(Message::Format_Plain));
        }
    }

    /**
     * Helper method to add a default non-empty value to an array.
     * This method will not overwrite existing values.
     *
     * @return bool
     *   Whether the default was added.
     */
    protected function addDefault(array &$array, string $key, $value): bool
    {
        if (empty($array[$key]) && !empty($value)) {
            $array[$key] = $value;
            return true;
        }
        return false;
    }

    /**
     * Helper method to add a warning to an array.
     * Warnings are placed in the $array under the key Meta::Warning. If no
     * warning is set, $warning is added as a string, otherwise it becomes an
     * array of warnings to which this $warning is added.
     */
    protected function addWarning(array &$array, string $warning)
    {
        if (!isset($array[Meta::Warning])) {
            $array[Meta::Warning] = $warning;
        } else {
            if (!is_array($array[Meta::Warning])) {
                $array[Meta::Warning] = (array) $array[Meta::Warning];
            }
            // @todo: find out why a warning could be added multiple times and
            //   try to prevent it in the first place.
            if (in_array($warning, $array[Meta::Warning])) {
                $array[Meta::Warning][] = $warning;
            }
        }
    }
}
