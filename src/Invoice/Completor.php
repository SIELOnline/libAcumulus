<?php
namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Helpers\Countries;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Meta;
use Siel\Acumulus\PluginConfig;
use Siel\Acumulus\Tag;
use Siel\Acumulus\ApiClient\Result as WebResult;
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
 * @package Siel\Acumulus
 */
class Completor
{
    const VatRateSource_Completor_Range = 'completor-range';
    const VatRateSource_Completor_Lookup = 'completor-lookup';
    const VatRateSource_Completor_Range_Lookup = 'completor-range-lookup';
    const VatRateSource_Completor_Range_Lookup_Foreign = 'completor-range-lookup-foreign';
    const VatRateSource_Completor_Max_Appearing = 'completor-max-appearing';
    const VatRateSource_Strategy_Completed = 'strategy-completed';
    const VatRateSource_Copied_From_Children = 'copied-from-children';
    const VatRateSource_Copied_From_Parent = 'copied-from-parent';

    /**
     * A list of vat rate sources that indicate that the vat rate can be
     * considered correct.
     *
     * @var array
     */
    protected static $CorrectVatRateSources = array(
        Creator::VatRateSource_Exact,
        Creator::VatRateSource_Exact0,
        self::VatRateSource_Completor_Range,
        self::VatRateSource_Completor_Lookup,
        self::VatRateSource_Completor_Range_Lookup,
        self::VatRateSource_Completor_Range_Lookup_Foreign,
        self::VatRateSource_Completor_Max_Appearing,
        self::VatRateSource_Strategy_Completed,
        self::VatRateSource_Copied_From_Children,
        self::VatRateSource_Copied_From_Parent,
    );

    /**
     * A list of vat types that allow vat free vat rates.
     *
     * @var int[]
     */
    protected static $vatTypesAllowingVatFree = array(Api::VatType_National, Api::VatType_ForeignVat, Api::VatType_MarginScheme);

    /**
     * A list of vat types that do not charge vat.
     *
     * @var int[]
     */
    protected static $noVatVatTypes = array(Api::VatType_NationalReversed, Api::VatType_EuReversed, Api::VatType_RestOfWorld);


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

    /** @var \Siel\Acumulus\ApiClient\Result */
    protected $result;

    /** @var array */
    protected $invoice;

    /** @var Source */
    protected $source;

    /**
     * The list of possible vat types, initially filled with possible vat types
     * based on client country, invoiceHasLineWithVat(), is_company(), and the
     * foreign vat setting. But then reduced by VAT rates we find on the
     * order lines.
     *
     * @var int[]
     */
    protected $possibleVatTypes;

    /**
     * The list of possible vat rates, based on the possible vat types and
     * extended with the zero rates (0 and vat-free) if they might be
     * applicable.
     *
     * @var array[]
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
    protected function t($key)
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
     * @param \Siel\Acumulus\ApiClient\Result $result
     *   A Result object where local errors and warnings can be added.
     *
     * @return array
     *   The completed invoice.
     */
    public function complete(array $invoice, Source $source, WebResult $result)
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
            $this->changeInvoiceToConcept('message_warning_strategies_failed', 808);
        }

        // Determine the VAT type and warn if multiple vat types are possible.
        $this->completeVatType();
        // If the invoice has margin products, all invoice lines have to follow
        // the margin scheme, i.e. have a costprice and a unitprice incl. VAT.
        $this->correctMarginInvoice();
        // Correct vatrate = 0 to vat-free where applicable.
        $this->correct0VatToVatFree();

        // Completes the invoice with settings or behaviour that might depend on
        // the fact that the invoice lines have been completed.
        $this->removeEmptyShipping();

        // Massages the meta data before sending the invoice.
        $this->processMetaData();

        return $this->invoice;
    }

    /**
     * Initializes the list of possible vat types for this invoice.
     *
     * The list of possible vat types depends on:
     * - Whether the vat type already has been set.
     * - Whether there is at least 1 line with a costprice.
     * - The country of the client.
     * - Whether the client is a company.
     * - The shop settings (selling foreign vat or vat free products).
     * - Optionally, the date of the invoice.
     *
     * See also: {@see https://wiki.acumulus.nl/index.php?page=facturen-naar-het-buitenland}.
     */
    protected function initPossibleVatTypes()
    {
        $possibleVatTypes = array();
        $shopSettings = $this->config->getShopSettings();
        $nature = $shopSettings['nature_shop'];
        $margin = $shopSettings['marginProducts'];
        $foreignVat = $shopSettings['foreignVat'];

        if (!empty($this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType])) {
            // If shop specific code or an event handler has already set the vat
            // type, we obey so.
            $possibleVatTypes[] = $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType];
        } else {
            if ($this->isNl()) {
                $possibleVatTypes[] = Api::VatType_National;
                // Can it be national reversed VAT: not really supported but
                // possible.
                if ($this->isCompany()) {
                    $possibleVatTypes[] = Api::VatType_NationalReversed;
                }
            } elseif ($this->isEu()) {
                // Can it be normal vat?
                if ($foreignVat !== PluginConfig::ForeignVat_Only) {
                    $possibleVatTypes[] = Api::VatType_National;
                }
                // Can it be foreign vat?
                if ($foreignVat !== PluginConfig::ForeignVat_No) {
                    $possibleVatTypes[] = Api::VatType_ForeignVat;
                }
                // Can it be EU reversed VAT.
                if ($this->isCompany()) {
                    $possibleVatTypes[] = Api::VatType_EuReversed;
                }
            } elseif ($this->isOutsideEu()) {
                // Can it be national vat (possibly vat free)? Services should
                // use vattype = 1.
                if ($nature !== PluginConfig::Nature_Products) {
                    $possibleVatTypes[] = Api::VatType_National;
                }
                // Can it be rest of world (0%)? Goods should use vat type = 4
                // unless you can't or don't want to prove that the goods will
                // leave the EU (see
                // https://www.belastingdienst.nl/rekenhulpen/leveren_van_goederen_naar_het_buitenland/),
                // in which case we should use vat type = 1.
                if ($nature !== PluginConfig::Nature_Services) {
                    $possibleVatTypes[] = Api::VatType_National;
                    $possibleVatTypes[] = Api::VatType_RestOfWorld;
                }
            }

            // Can it be a margin invoice?
            if ($margin !== PluginConfig::MarginProducts_No) {
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
     * vat rates. A vat rate being an array with keys vatrate and vattype. This
     * is done so to be able to determine to which vat type(s) a vat rate
     * belongs.
     */
    protected function initPossibleVatRates()
    {
        $possibleVatRates = array();

        $shopSettings = $this->config->getShopSettings();
        $nature = $shopSettings['nature_shop'];
        $vatFreeProducts = $shopSettings['vatFreeProducts'];

        foreach ($this->possibleVatTypes as $vatType) {
            switch ($vatType) {
                case Api::VatType_National:
                case Api::VatType_MarginScheme:
                    $countryVatRates = $this->getVatRatesByCountryAndInvoiceDate('nl');
                    $vatTypeVatRates = [];
                    // Only add those NL vat rates that are possible given the
                    // plugin settings and the invoice target (buyer).
                    foreach ($countryVatRates as $countryVatRate) {
                        if (Number::isZero($countryVatRate) || Number::floatsAreEqual($countryVatRate, Api::VatFree)) {
                            // Zero or vat free rate: add if
                            // - selling vat free products/services
                            // - OR (outside EU AND services).
                            if ($vatFreeProducts != PluginConfig::VatFreeProducts_No) {
                                $vatTypeVatRates[] = $countryVatRate;
                            } elseif ($this->isOutsideEu() && $nature !== PluginConfig::Nature_Products) {
                                $vatTypeVatRates[] = $countryVatRate;
                            }
                        } else {
                            // Positive (non-zero and non free) vat rate: add if
                            // not only selling vat-free products.
                            if ($vatFreeProducts != PluginConfig::VatFreeProducts_Only) {
                                $vatTypeVatRates[] = $countryVatRate;
                            }
                        }
                    }
                    break;
                case Api::VatType_NationalReversed:
                case Api::VatType_EuReversed:
                case Api::VatType_RestOfWorld:
                    // These vat types can only have the 0% vat rate.
                    $vatTypeVatRates = array(0);
                    break;
                case Api::VatType_ForeignVat:
                    $vatTypeVatRates = $this->getVatRatesByCountryAndInvoiceDate($this->invoice[Tag::Customer][Tag::CountryCode]);
                    // Now, I could (should?) try to remove reduced and 0% &
                    // vat-free rates, but as the vat rate to apply to an item
                    // (standard, reduced or 0/vat-free) might not be the same
                    // in all countries, I won't.
                    // Plus that we are still working on the assumption of
                    // digital services, not the broader concept of including
                    // all e-commerce (as will be the case as of july 1, 2021)
                    // this is not really necessary.
                    break;
                default:
                    $vatTypeVatRates = array();
                    $this->log->error('Completor::initPossibleVatRates(): unknown vat type %d', $vatType);
                    break;
            }
            // Convert the list of vat rates to a list of vat rate infos.
            foreach ($vatTypeVatRates as &$vatRate) {
                $vatRate = array(Tag::VatRate => $vatRate, Tag::VatType => $vatType);
            }
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
            $keysToKeep = array(Tag::CountryCode, Tag::Invoice);
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
     *   address and we will set a warning.
     */
    protected function validateEmail()
    {
        // Check email address.
        if (empty($this->invoice[Tag::Customer][Tag::Email])) {
            $customerSettings = $this->config->getCustomerSettings();
            $this->invoice[Tag::Customer][Tag::Email] = $customerSettings['emailIfAbsent'];
            $this->result->addMessage($this->t('message_warning_no_email'), Severity::Warning, '', 801);
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
     * these to the fields meta-lines-amount and meta-lines-vatamount.
     */
    protected function completeLineTotals()
    {
        $linesAmount = 0.0;
        $linesAmountInc = 0.0;
        $linesVatAmount = 0.0;
        $this->lineTotalsStates = array(
            'incomplete' => array(),
            'equal' => array(),
            'differ' => array(),
        );

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
    protected function areTotalsEqual()
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
            // be a vat free/reversed vat invoice without any vatamount.
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
     * Besides the line we also add a warning and change the invoice to a
     * concept.
     *
     * The amounts can differ if e.g:
     * - we missed a fee that is stored in custom fields of a not (yet)
     *   supported 3rd party plugin
     * - a manual adjustment that is not separately stored
     * - an error in this plugin's logic
     * - an error in the data as provided by the webshop
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
        if ($invoiceSettings['missingAmount'] === PluginConfig::MissingAmount_AddLine && $incomplete <= 1) {
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

            $line = array(
                    Tag::Product => $product,
                    Tag::Quantity => 1,
                    Tag::UnitPrice => $missingAmount,
                );
            $line += Creator::getVatRangeTags($missingVatAmount, $missingAmount, $countLines * 0.02, $countLines * 0.02);
            $line[Meta::LineType] = Creator::LineType_Corrector;
            // Correct and add this line (round of correcting has already been
            // executed).
            if ($line[Meta::VatRateSource] === Creator::VatRateSource_Calculated) {
                $line = $this->LineCompletor->correctVatRateByRange($line);
            }
            $invoice[Tag::Line][] = $line;

            // Add warning.
            $this->changeInvoiceToConcept('message_warning_missing_amount_added', 809, $missingAmount, $missingVatAmount);
        } elseif ($invoiceSettings['missingAmount'] !== PluginConfig::MissingAmount_Ignore) {
            // Due to lack of information, we cannot add a missing line, even
            // though we know we are missing something: just add a warning.
            $missing = array();
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
            $this->changeInvoiceToConcept('message_warning_missing_amount_warn', 810, ucfirst(implode(', ', $missing)));
        }
    }

    /**
     * Determines the vattype of the invoice.
     *
     * This method (and class) is aware of:
     * - The setting foreignVat.
     * - The country of the client.
     * - Whether the client is a company.
     * - The actual VAT rates on the day of the order.
     * - Whether there are margin products in the order.
     *
     * So to start with, any list of (possible) vat types is based on the above.
     * Furthermore this method and {@see getInvoiceLinesVatTypeInfo()} are aware
     * of:
     * - The fact that orders do not have to be split over different vat types,
     *   but that invoices should be split if both national and foreign VAT
     *   rates appear on the order.
     * - The vat class meta data per line and which classes denote foreign vat.
     *   This info is used to distinguish between NL and foreign vat for EU
     *   countries that have VAT rates in common with NL and the settings
     *   indicate that this shop sells products in both vat type categories.
     *
     * If multiple vat types are possible, the invoice is sent as concept, so
     * that it may be corrected in Acumulus.
     */
    protected function completeVatType()
    {
        $this->checkForKnownVatType();
        // If shop specific code or an event handler has already set the vat
        // type, we don't change it.
        if (empty($this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType])) {
        	// @todo: if we only have one possible vattype, should we use that
            //  or should we perform all checks to look for contradictory
            //  evidence?
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
                    // - Vat rates are for foreign VAT but the shop does
                    //   not sell foreign VAT products. Message'Check "about
                    //   your shop" settings'.
                    // - Vat rates are Dutch vat rates but shop only sells
                    //   foreign VAT products and client is in the EU. Message:
                    //   'Check the vat rates assigned to your products.'.
                    $message = 'message_warning_no_vattype_at_all';
                    $code = 804;
                } elseif (count($vatTypeInfo['union']) === 1) {
                    // One or more lines could be matched with exactly 1 vat
                    // type, but not all lines.
                    // Possible causes:
                    // - Non matching lines have no vat. Message: 'Manual line
                    //   entered without vat' or 'Check vat settings on those
                    //   products.'.
                    // - Non matching lines have vat. Message: 'Manual line
                    //   entered with incorrect vat' or 'Check vat settings on
                    //   those products.'.
                    $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType] = reset($vatTypeInfo['union']);
                    $message = 'message_warning_no_vattype_incorrect_lines';
                    $code = 812;
                } else {
                    // Separate lines could be matched with some of the possible
                    // vat types, but not all with the same vat type.
                    // Possible causes:
                    // - Mix of foreign VAT rates and other goods or services.
                    //   Message: 'Split invoice.'.
                    // - Some lines have no vat but no vat free goods or
                    //   services are sold and thus this could be a reversed vat
                    //   (company in EU) or vat free invoice (outside EU).
                    //   Message: check vat settings.
                    // - Mix of margin scheme and normal vat: this can be solved
                    //   by making it a margin scheme invoice and adding
                    //   costprice = 0 to all normal lines.
                    if (in_array(Api::VatType_MarginScheme, $vatTypeInfo['union'])) {
                        $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType] = Api::VatType_MarginScheme;
                        $this->invoice[Tag::Customer][Tag::Invoice][Meta::VatTypeSource] = 'Completor::completeVatType: Convert all lines to margin scheme';
                    } else {
                        // Take the first vat type as a guess but add a warning.
                        $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType] = reset($vatTypeInfo['union']);
                        $message = 'message_warning_no_vattype_must_split';
                        $code = 806;
                    }
                }
            } elseif (count($vatTypeInfo['intersection']) === 1) {
                // Exactly 1 vat type was found to be possible for all lines:
                // use that one as the vat type for the invoice.
                $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType] = reset($vatTypeInfo['intersection']);
                $this->invoice[Tag::Customer][Tag::Invoice][Meta::VatTypeSource] = 'Completor::completeVatType: Only one choice fits all';
            } else {
                // Multiple vat types were found to be possible for all lines:
                // Guess which one to take or add a warning.
                // Possible causes:
                // - Client country has same vat rates as the Netherlands and
                //   shop sells products at foreign vat rates but also other
                //   products or services. Solvable by correct shop settings.
                // - Invoice has no vat and the client is outside the EU and it
                //   is unknown whether the invoice lines contain services or
                //   goods. Perhaps solvable by correct shop settings.
                // - Margin invoice: all lines that have a costprice will
                //   probably also satisfy the normal vat. This is solvable by
                //   making it a margin scheme invoice and adding costprice = 0
                //   to all normal lines.
                $this->guessVatType($vatTypeInfo['intersection']);
                if (empty($this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType])) {
                    if (in_array(Api::VatType_MarginScheme, $vatTypeInfo['union'])) {
                        $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType] = Api::VatType_MarginScheme;
                    } else {
                        // Take the first vat type as a guess but add a warning.
                        $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType] = reset($vatTypeInfo['intersection']);
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
                $this->changeInvoiceToConcept($message, $code, $this->t($startSentence));
            }
            $this->invoice[Tag::Customer][Tag::Invoice][Meta::VatTypesPossibleInvoice] = implode(',', $this->possibleVatTypes);
            $this->invoice[Tag::Customer][Tag::Invoice][Meta::VatTypesPossibleInvoiceLinesIntersection] = implode(',', $vatTypeInfo['intersection']);
            $this->invoice[Tag::Customer][Tag::Invoice][Meta::VatTypesPossibleInvoiceLinesUnion] = implode(',', $vatTypeInfo['union']);
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
    protected function getInvoiceLinesVatTypeInfo()
    {
        $list = array();
        $union = array();
        $intersection = null;
        foreach ($this->invoice[Tag::Customer][Tag::Invoice][Tag::Line] as $index => &$line) {
            if ($this->isCorrectVatRate($line[Meta::VatRateSource])) {
                $possibleLineVatTypes = array();
                foreach ($this->possibleVatRates as $vatRateInfo) {
                    $vatRate = $vatRateInfo['vatrate'];
                    $vatType = $vatRateInfo['vattype'];
                    // We should treat 0 and vat free vat rates as equal as
                    // they are not yet corrected.
                    $equal = Number::floatsAreEqual($vatRate, $line[Tag::VatRate]);
                    $zeroAndFree = Number::isZero($vatRate) && Number::floatsAreEqual($line[Tag::VatRate], Api::VatFree);
                    $freeAndZero = Number::floatsAreEqual($vatRate, Api::VatFree) && Number::isZero($line[Tag::VatRate]);
                    if ($equal || $zeroAndFree || $freeAndZero) {
                        // We have a possibly matching vat type. Perform some
                        // additional checks before we really add it as a match:
                        $doAdd = true;

                        // 1) Vat type margin scheme requires a cost price.
                        if ($vatType === Api::VatType_MarginScheme) {
                            if (empty($line[Tag::CostPrice])) {
                                $doAdd = false;
                            }
                        }

                        // 2) If this is a 0 vat rate while the lookup vat rate,
                        //   if available, is not, it must be a no vat invoice:
                        //   - one of the no vat vat types.
                        //   - vat type national with client outside EU.
                        if ($this->lineHas0VatRate($line) && !empty($line[Meta::VatRateLookup]) && !$this->metaDataHas0VatRate($line[Meta::VatRateLookup])) {
                            // This article is not intrinsically vat free, so
                            // the vat type must be a vat type allowing no vat,
                            // i.e. a no vat vat type or national vat for a
                            // customer outside the EU.
                            if (!in_array($vatType, static::$noVatVatTypes) && !($vatType === Api::VatType_National && $this->isOutsideEu())) {
                                $doAdd = false;
                            }

                            // If the customer is outside the EU and we do not
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

                        // 3) In EU: If the vat class is known and denotes
                        //   foreign vat, we do not add the Dutch vat type.
                        if ($this->isEu() && !empty($line[Meta::VatClassId]) && $this->isForeignVatClass($line[Meta::VatClassId])) {
                            if ($vatType === Api::VatType_National) {
                                $doAdd = false;
                            }
                        }

                        // 4) If the vat class is known and does not denote
                        //   foreign vat, we do not add the Foreign vat type.
                        //   Note that as this can prevent finding vat type 6
                        //   when there's a fee line at NL vat rate which
                        //   happens to be the foreign vat rate as well, we only
                        //   do this for item lines.
                        if ($line[Meta::LineType] === Creator::LineType_OrderItem && !empty($line[Meta::VatClassId]) && !$this->isForeignVatClass($line[Meta::VatClassId])) {
                            if ($vatType === Api::VatType_ForeignVat) {
                                $doAdd = false;
                            }
                        }

                        if ($doAdd) {
                            $possibleLineVatTypes[] = $vatType;
                        }
                    }
                }
                // Add meta info to Acumulus invoice.
                $line[Meta::VatTypesPossible] = implode(',', $possibleLineVatTypes);
                // Add to result, union and intersection.
                $list[$index] = $possibleLineVatTypes;
                $union = array_merge($union, $possibleLineVatTypes);
                $intersection = $intersection !== null ? array_intersect($intersection, $possibleLineVatTypes) : $possibleLineVatTypes;
            }
        }

        // Union can obviously contain double results.
        $list['union'] = array_unique($union);
        // Intersection can contain double results due to handling 0% and
        // vat-free as being the same (as they have not yet been corrected).
        $list['intersection'] = $intersection !== null ? array_unique($intersection) : array();
        return $list;
    }

    /**
     * Checks if the vat type can be known for sure and if so, sets it.
     *
     * This method should be overridden by a shop specific Completor override if
     * the shop stores data from which the vat type can be determined with
     * certainty.
     *
     * This default implementation does nothing.
     */
    protected function checkForKnownVatType()
    {
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
            $shopSettings = $this->config->getShopSettings();
            $vatFreeProducts = $shopSettings['vatFreeProducts'];
            $nature = $shopSettings['nature_shop'];
            $allIItemsVatFree = true;
            $allIItemsService = true;
            if ($vatFreeProducts == PluginConfig::VatFreeProducts_Only && $nature == PluginConfig::Nature_Services) {
                $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType] = Api::VatType_National;
                $this->invoice[Tag::Customer][Tag::Invoice][Meta::VatTypeSource] = 'Completor::guessVatType: VatFreeProducts_Only';
            } else {
                foreach ($this->invoice[Tag::Customer][Tag::Invoice][Tag::Line] as $index => $line) {
                    if (!empty($line[Meta::VatRateLookup])) {
                        foreach ($line[Meta::VatRateLookup] as $vatRate) {
                            if (!Number::isZero($vatRate)) {
                                // We have a positive possible vat rate for the
                                // item. Do not choose
                                $allIItemsVatFree = null;
                                break 2;
                            }
                        }
                    } else {
                        $allIItemsVatFree = null;
                        break;
                    }

                    if (empty($line[Tag::Nature])) {
                        // We have a (possible) non-service item line: do not
                        // choose
                        $allIItemsService = null;
                        break;
                    }
                    if ($line[Tag::Nature] !== Api::Nature_Service) {
                        // We have a non-service item line: do not choose
                        $allIItemsService = false;
                        break;
                    }
                }
            }
            if ($allIItemsVatFree && $allIItemsService) {
                $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType] = Api::VatType_National;
                $this->invoice[Tag::Customer][Tag::Invoice][Meta::VatTypeSource] = 'Completor::guessVatType: all items are vat free services';
            } elseif ($allIItemsService === false) {
                $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType] = Api::VatType_EuReversed;
                $this->invoice[Tag::Customer][Tag::Invoice][Meta::VatTypeSource] = 'Completor::guessVatType: at least 1 item is not a service';
            }
        }
    }

    /**
     * Corrects an invoice if it is a margin scheme invoice.
     *
     * If an invoice is of the margin scheme type, all lines have to follow the
     * margin scheme rules. These rules are:
     * - Each line must have a costprice, but that cost price may be 0.
     * - The unitprice should now contain the price including VAT (requirement
     *   of the web service API).
     * Thus if there are e.g. shipping lines or other fee lines, they have to be
     * converted to the margin scheme (costprice tag and change of unitprice).
     */
    protected function correctMarginInvoice()
    {
        if (isset($this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType]) && $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType] === Api::VatType_MarginScheme) {
            foreach ($this->invoice[Tag::Customer][Tag::Invoice][Tag::Line] as &$line) {
                // For margin invoices, Acumulus expects the unitprice to be the
                // sales price, ie the price the client pays. So we set
                // unitprice to unitpriceinc.
                // Non margin lines may "officially" not appear on margin
                // invoices, so we turn them into margin lines by adding a
                // costprice of 0 and also setting unitprice to unitpriceinc.
                if (!isset($line[Tag::CostPrice])) {
                    // "Normal" line: set costprice as 0.
                    $line[Tag::CostPrice] = 0.0;
                }
                // Add "marker" tag (for debug purposes) for this correction.
                $line[Meta::RecalculateOldPrice] = $line[Tag::UnitPrice];
                // Change unitprice tag to include VAT.
                if (isset($line[Meta::UnitPriceInc])) {
                    $line[Tag::UnitPrice] = $line[Meta::UnitPriceInc];
                } elseif (isset($line[Meta::VatAmount])) {
                    $line[Tag::UnitPrice] += $line[Meta::VatAmount];
                } elseif (isset($line[Tag::VatRate])) {
                    $line[Tag::UnitPrice] += $line[Tag::VatRate]/100.0 * ($line[Tag::UnitPrice] - $line[Tag::CostPrice]);
                } //else {
                    // Impossible to correct the unitprice. Probably all
                    // strategies failed, so the invoice should already
                    // have a warning.
                //}
            }
        }
    }

    /**
     * Change 0% vat rates to vat free.
     *
     * Acumulus distinguishes between 0% vat and vat free.
     * 0% vat should be used with:
     * - Reversed vat invoices, EU or national (vat type = 2 or 3).
     * - Products invoiced outside the EU (vat type = 4).
     * Vat free should be used for:
     * - Vat free products and services, e.g. care, education (vat type = 1, 5
     *   or, theoretically, 6).
     * - Services invoiced to companies outside the EU (vat type = 1).
     * - Digital services outside the EU, consumers or companies (vat type = 1).
     *
     * Thus, to do this correctly, especially for invoices outside the EU, we
     * should be able to distinguish between services and products. For that,
     * the nature field should be filled in or the shop should only sell
     * products or only services. If not, we act as if the line invoices a
     * product.
     *
     * See:
     * - {@see https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/tarieven_en_vrijstellingen/}
     * - {@see https://wiki.acumulus.nl/index.php?page=facturen-naar-het-buitenland}:
     */
    protected function correct0VatToVatFree()
    {
        if (isset($this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType])) {
            $vatType = $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType];
            if (in_array($vatType, static::$vatTypesAllowingVatFree)) {
                foreach ($this->invoice[Tag::Customer][Tag::Invoice][Tag::Line] as &$line) {
                    if ($this->lineHas0VatRate($line)) {
                        $line[Tag::VatRate] = Api::VatFree;
                    }
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
     * Processes meta data before sending the invoice.
     *
     * Currently the following processing is done:
     * - Meta::VatRateLookup, Meta::VatRateLookupLabel, Meta::FieldsCalculated,
     *   Meta::VatRateLookupMatches, and Meta::VatRateRangeMatches are converted
     *   a json string if they are an array.
     */
    protected function processMetaData()
    {
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
        }
    }

    /**
     * Returns whether the given line has a 0% or vat free vat rate.
     *
     * @param array $line
     *   The invoice line.
     *
     * @return bool
     *   True if the given line has a 0% or vat free vat rate, false otherwise.
     */
    protected function lineHas0VatRate(array $line)
    {
        $result = false;
        if ($this->is0VatRate($line)) {
            $result = true;
        }
        return $result;
    }

    /**
     * Returns whether $vatRates contains a 0 vat rate.
     *
     * @param float|float[] $vatRates
     *
     * @return bool
     *   True if $vatRates contains a 0 vat rate, false otherwise.
     */
    protected function metaDataHas0VatRate($vatRates)
    {
    	$vatRates = (array) $vatRates;
        foreach ($vatRates as $vatRate) {
            if ($this->is0VatRate($vatRate)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns whether the vat rate is a 0 vat rate.
     *
     * @param int|array $vatRate
     *   An integer (or numeric string), an array possibly containing an entry
     *   with key Tag::VatRate entry
     *
     * @return bool
     *   True if the vat rate is a 0 vat rate, false otherwise.
     */
    protected function is0VatRate($vatRate)
    {
        if (is_array($vatRate)) {
            $vatRate = isset($vatRate[Tag::VatRate]) ? $vatRate[Tag::VatRate] : null;
        }
        return isset($vatRate) && (Number::isZero($vatRate) || Number::floatsAreEqual($vatRate, Api::VatFree));
    }

    /**
     * Helper method to get the vat rates for the current invoice.
     *
     * - This method contacts the Acumulus server and will cache the results.
     * - The vat rates returned reflect those as they were at the invoice date.
     * - No zero vat rates are returned.
     *
     * @param string $countryCode
     *   The country code of the country to fetch the vat rates for.
     *
     * @return float[]
     *   Actual type will be string[] containing strings representing floats.
     *
     * @see \Siel\Acumulus\ApiClient\Acumulus::getVatInfo().
     */
    protected function getVatRatesByCountryAndInvoiceDate($countryCode)
    {
        $countryCode = strtoupper($countryCode);
        $date = $this->getInvoiceDate();
        $cacheKey = "$countryCode&$date";
        if (!isset($this->vatRatesCache[$cacheKey])) {
            $result = $this->acumulusApiClient->getVatInfo($countryCode, $date);
            if ($result->hasRealMessages()) {
                $this->result->addMessages($result->getMessages(Severity::InfoOrWorse));
            }
            $this->vatRatesCache[$cacheKey] = array_column($result->getResponse(), Tag::VatRate);
        }
        return $this->vatRatesCache[$cacheKey];
    }

    /**
     * Returns the invoice date in the iso yyyy-mm-dd format.
     *
     * @return string
     *   The invoice date in the iso yyyy-mm-dd format.
     */
    protected function getInvoiceDate()
    {
        return !empty($this->invoice[Tag::Customer][Tag::Invoice][Tag::IssueDate])
            ? $this->invoice[Tag::Customer][Tag::Invoice][Tag::IssueDate]
            : date(API::DateFormat_Iso);
    }

    /**
     * Wrapper around Countries::isNl().
     *
     * @return bool
     */
    protected function isNl()
    {
        return $this->countries->isNl($this->invoice[Tag::Customer][Tag::CountryCode]);
    }

    /**
     * Returns whether the country is a EU country outside the Netherlands.
     *
     * This method determines whether a country is in or outside the EU based on
     * fiscal handling of invoices to customers in that country. If vattype 3 -
     * EU reversed vat - and 6 - foreign vat - are possible it is considered to
     * be in the EU.
     *
     * @return bool
     */
    protected function isEu()
    {
        $result = false;
        if (!$this->isNl()) {
            $result = !empty($this->getVatRatesByCountryAndInvoiceDate($this->invoice[Tag::Customer][Tag::CountryCode]));
        }
        return $result;
    }

    /**
     * Returns whether the client is located outside the EU.
     *
     * @return bool
     */
    protected function isOutsideEu()
    {
        return !$this->isEu();
    }

    /**
     * Returns whether the client is a company with a vat number.
     *
     * @return bool
     */
    protected function isCompany()
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
     * - The meta tag "do convert" equals "currency !== 'EUR'.
     *
     * @param array $invoice
     *   The invoice (starting with the customer part).
     *
     * @return bool
     *   True if the invoice uses another currency, false otherwise.
     */
    public function shouldConvertCurrency(array &$invoice)
    {
        $invoicePart = &$invoice[Tag::Customer][Tag::Invoice];
        $shouldConvert = isset($invoicePart[Meta::Currency]) && isset($invoicePart[Meta::CurrencyRate]) && isset($invoicePart[Meta::CurrencyDoConvert]);
        $shouldConvert = $shouldConvert && (float) $invoicePart[Meta::CurrencyRate] != 1.0;
        if ($shouldConvert) {
            if ($invoicePart[Meta::Currency] !== 'EUR') {
                // Order/refund is not in euro's: convert if amounts are stored
                // in the order's currency, not the shop's default currency
                // (which should be EUR).
                $shouldConvert = $invoicePart[Meta::CurrencyDoConvert];
                $invoicePart[Meta::CurrencyRateInverted] = false;
            } else {
                // Order/refund is in euro's but that is not the shop's default:
                // convert if the amounts are in the in the shop's default
                // currency, not the order's currency (which is EUR).
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
     * @param array $array
     * @param string $key
     * @param float $conversionRate
     *
     * @return bool
     *   Whether the amount was converted.
     */
    public function convertAmount(array &$array, $key, $conversionRate)
    {
        if (!empty($array[$key]) && !empty($conversionRate)) {
            $array[$key] = (float) $array[$key] / (float) $conversionRate;
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
    public static function isCorrectVatRate($source)
    {
        return in_array($source, self::$CorrectVatRateSources);
    }

    /**
     * Returns whether the vat class id denotes foreign vat.
     *
     * @param int|string $vatClassId
     *   The vat class to check.
     *
     * @return bool
     *   True if the shop might sell foreign vat articles and the vat class id
     *   denotes a foreign vat class, false otherwise.
     */
    public function isForeignVatClass($vatClassId)
    {
        $shopSettings = $this->config->getShopSettings();
        $foreignVat = $shopSettings['foreignVat'];
        $foreignVatClasses = $shopSettings['foreignVatClasses'];
        return $foreignVat !== PluginConfig::ForeignVat_No && in_array($vatClassId, $foreignVatClasses);
    }

    /**
     * Makes the invoice a concept invoice and optionally adds a warning.
     *
     * @param string $messageKey
     *   The key of the message to add as warning, or the empty string if no
     *   warning has to be added.
     * @param int $code
     *   The code for this message.
     * @param string ...
     *   Additional arguments to format the message.
     */
    public function changeInvoiceToConcept($messageKey, $code)
    {
        $pdfMessage = '';
        $invoiceSettings = $this->config->getInvoiceSettings();
        $concept = $invoiceSettings['concept'];
        if ($concept == PluginConfig::Concept_Plugin) {
            $this->invoice[Tag::Customer][Tag::Invoice][Tag::Concept] = Api::Concept_Yes;
            $emailAsPdfSettings = $this->config->getEmailAsPdfSettings();
            if ($emailAsPdfSettings['emailAsPdf']) {
                $pdfMessage = ' ' . $this->t('message_warning_no_pdf');
            }
        }

        if ($messageKey !== '') {
            $message = $this->t($messageKey) . $pdfMessage;
            if (func_num_args() > 2) {
                $args = func_get_args();
                $message = vsprintf($message, array_slice($args, 2));
            }
            $this->result->addMessage($message, Severity::Warning, '', $code);
        }
    }

    /**
     * Helper method to add a default non-empty value to an array.
     *
     * This method will not overwrite existing values.
     *
     * @param array $array
     * @param string $key
     * @param mixed $value
     *
     * @return bool
     *   Whether the default was added.
     */
    protected function addDefault(array &$array, $key, $value)
    {
        if (empty($array[$key]) && !empty($value)) {
            $array[$key] = $value;
            return true;
        }
        return false;
    }
}
