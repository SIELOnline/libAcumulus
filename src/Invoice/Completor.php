<?php
namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Config\ConfigInterface;
use Siel\Acumulus\Helpers\Countries;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Helpers\TranslatorInterface;
use Siel\Acumulus\Meta;
use Siel\Acumulus\PluginConfig;
use Siel\Acumulus\Tag;
use Siel\Acumulus\Web\Result as WebResult;
use Siel\Acumulus\Web\Service;

/**
 * The invoice completor class provides functionality to correct and complete
 * invoices before sending them to Acumulus.
 *
 * This class:
 * - Changes the customer into a fictitious client if set so in the config.
 * - Validates the email address: the webservice does not allow an empty email
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
    const VatRateSource_Calculated_Corrected = 'calculated-corrected';
    const VatRateSource_Looked_Up = 'completor-looked-up';
    const VatRateSource_Completor_Completed = 'completor-completed';
    const VatRateSource_Strategy_Completed = 'strategy-completed';
    const VatRateSource_Copied_From_Children = 'copied-from-children';
    const VatRateSource_Copied_From_Parent = 'copied-from-parent';

    const Vat_HasVat = 1;
    const Vat_Has0Vat = 2;
    const Vat_Unknown = 4;

    /**
     * A list of vat rate sources that indicate that the vat rate can be
     * considered correct.
     *
     * @var array
     */
    protected static $CorrectVatRateSources = array(
        Creator::VatRateSource_Exact,
        Creator::VatRateSource_Exact0,
        self::VatRateSource_Calculated_Corrected,
        self::VatRateSource_Looked_Up,
        self::VatRateSource_Completor_Completed,
        self::VatRateSource_Strategy_Completed,
        self::VatRateSource_Copied_From_Children,
        self::VatRateSource_Copied_From_Parent,
    );

    /** @var \Siel\Acumulus\Config\ConfigInterface */
    protected $config;

    /** @var \Siel\Acumulus\Helpers\TranslatorInterface */
    protected $translator;

    /** @var \Siel\Acumulus\Web\Service */
    protected $service;

    /** @var \Siel\Acumulus\Helpers\Countries */
    protected $countries;

    /** @var \Siel\Acumulus\Web\Result */
    protected $result;

    /** @var array */
    protected $invoice;

    /** @var Source */
    protected $source;

    /**
     * The list of possible vat types, initially filled with possible vat types
     * based on client country, invoiceHasLineWithVat(), is_company(), and the
     * digital services setting. But then reduced by VAT rates we find on the
     * order lines.
     *
     * @var int[]
     */
    protected $possibleVatTypes;

    /** @var array[] */
    protected $possibleVatRates;

    /** @var \Siel\Acumulus\Invoice\CompletorInvoiceLines */
    protected $LineCompletor = null;

    /** @var \Siel\Acumulus\Invoice\CompletorStrategyLines */
    protected $strategyLineCompletor = null;

    /** @var array */
    protected $incompleteValues;

    /** @var (string|int)[][] */
    protected $lineTotalsStates;

    /**
     * Constructor.
     *
     * @param \Siel\Acumulus\Config\ConfigInterface $config
     * @param \Siel\Acumulus\Invoice\CompletorInvoiceLines $completorInvoiceLines
     * @param \Siel\Acumulus\Invoice\CompletorStrategyLines $completorStrategyLines
     * @param \Siel\Acumulus\Helpers\Countries $countries
     * @param \Siel\Acumulus\Helpers\TranslatorInterface $translator
     * @param \Siel\Acumulus\Web\Service $service
     */
    public function __construct(
        ConfigInterface $config,
        CompletorInvoiceLines $completorInvoiceLines,
        CompletorStrategyLines $completorStrategyLines,
        Countries $countries,
        TranslatorInterface $translator,
        Service $service
    ) {
        $this->config = $config;

        $this->translator = $translator;
        $invoiceHelperTranslations = new Translations();
        $this->translator->add($invoiceHelperTranslations);

        $this->countries = $countries;
        $this->service = $service;

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
     * @param \Siel\Acumulus\Web\Result $result
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

        // Completes the invoice with default settings that do not depend on shop
        // specific data.
        $this->fictitiousClient();
        $this->validateEmail();
        $this->invoiceTemplate();

        // Complete lines as far as they can be completed om their own.
        $this->initPossibleVatTypes();
        $this->initPossibleVatRates();
        $this->convertToEuro();
        $this->invoice = $this->LineCompletor->complete($this->invoice, $this->possibleVatTypes, $this->possibleVatRates);

        // Check if we are missing an amount and, if so, add a line for it.
        $this->completeLineTotals();
        $areTotalsEqual = $this->areTotalsEqual();
        if ($areTotalsEqual === false) {
            $this->addMissingAmountLine();
        }

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
        // Another check: do we have lines without VAT while the vat type and
        // settings prohibit this?
        // - if so: warn and set to concept.
        // - if not: correct vatrate = 0 to vatrate = -1 (vat-free) where
        //    applicable.
        $vatSituation = $this->getVatSituation($this->invoice[Tag::Customer][Tag::Invoice][Tag::Line], true);
        $vatTypeAllowsPositiveVatRates = in_array($this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType],
            array(Api::VatType_National, Api::VatType_ForeignVat, Api::VatType_MarginScheme));
        if ($vatTypeAllowsPositiveVatRates && ($vatSituation & static::Vat_Has0Vat) !== 0) {
            $shopSettings = $this->config->getShopSettings();
            $vatFreeProducts = $shopSettings['vatFreeProducts'];
            if ($vatFreeProducts === PluginConfig::VatFreeProducts_No) {
                $this->changeInvoiceToConcept('message_warning_line_without_vat', 802);
            } else {
                $this->correct0VatToVatFree();
            }
        }

        // Completes the invoice with settings or behaviour that might depend on
        // the fact that the invoice lines have been completed.
        $this->removeEmptyShipping();

        return $this->invoice;
    }

    /**
     * Initializes the list of possible vat types for this invoice.
     *
     * The list of possible vat types depends on:
     * - whether there are lines with vat or if all lines appear vat free.
     * - whether there is at least 1 line with a costprice.
     * - the country of the client.
     * - optionally, the date of the invoice.
     */
    protected function initPossibleVatTypes()
    {
        $possibleVatTypes = array();
        $shopSettings = $this->config->getShopSettings();
        $digitalServices = $shopSettings['digitalServices'];
        $vatFreeProducts = $shopSettings['vatFreeProducts'];

        if (!empty($this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType])) {
            // If shop specific code or an event handler has already set the vat
            // type, we obey so.
            $possibleVatTypes[] = $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType];
        } elseif ($this->invoiceHasLineWithCostPrice($this->invoice[Tag::Customer][Tag::Invoice][Tag::Line])) {
            $possibleVatTypes[] = Api::VatType_MarginScheme;
        } else {
            $vatSituation = $this->getVatSituation($this->invoice[Tag::Customer][Tag::Invoice][Tag::Line], true);
            if (($vatSituation & (static::Vat_HasVat | static::Vat_Unknown)) !== 0) {
                // NL or EU Foreign vat.
                if ($digitalServices === PluginConfig::DigitalServices_No) {
                    // No electronic services are sold: can only be dutch VAT.
                    $possibleVatTypes[] = Api::VatType_National;
                } else {
                    if ($this->isEu() && $this->getInvoiceDate() >= '2015-01-01') {
                        if ($digitalServices !== PluginConfig::DigitalServices_Only) {
                            // Also normal goods are sold, so dutch VAT still possible.
                            $possibleVatTypes[] = Api::VatType_National;
                        }
                        // As of 2015, electronic services should be taxed with the rates of
                        // the clients' country. And they might be sold in the shop.
                        $possibleVatTypes[] = Api::VatType_ForeignVat;
                    } else {
                        // Not EU or before 2015-01-01: special regulations for electronic
                        // services were not yet active: dutch VAT only.
                        $possibleVatTypes[] = Api::VatType_National;
                    }
                }
            }
            if (($vatSituation & (static::Vat_Has0Vat | static::Vat_Unknown)) !== 0) {
                // No VAT: National/EU reversed vat, only vat free
                // products, or no vat (rest of world).
                if ($this->isNl()) {
                    // Can it be VAT free products (e.g. education)? Digital
                    // services are never VAT free, nor are there any VAT free
                    // products if set so.
                    if ($digitalServices !== PluginConfig::DigitalServices_Only && $vatFreeProducts !== PluginConfig::VatFreeProducts_No) {
                        $possibleVatTypes[] = Api::VatType_National;
                    }
                    // National reversed VAT: not really supported but possible.
                    if ($this->isCompany()) {
                        $possibleVatTypes[] = Api::VatType_NationalReversed;
                    }
                } elseif ($this->isEu()) {
                    // EU reversed VAT.
                    if ($this->isCompany()) {
                        $possibleVatTypes[] = Api::VatType_EuReversed;
                    }
                    // Can it be VAT free products (e.g. education)? Digital
                    // services are never VAT free, nor are there any if set so.
                    if ($digitalServices !== PluginConfig::DigitalServices_Only && $vatFreeProducts !== PluginConfig::VatFreeProducts_No) {
                        $possibleVatTypes[] = Api::VatType_National;
                    }
                } elseif ($this->isOutsideEu()) {
                    $possibleVatTypes[] = Api::VatType_RestOfWorld;
                }

                if (empty($possibleVatTypes)) {
                    // Warning + fall back.
                    $possibleVatTypes[] = Api::VatType_National;
                    $possibleVatTypes[] = $this->isNl() ? Api::VatType_NationalReversed : Api::VatType_EuReversed;
                    $this->changeInvoiceToConcept('message_warning_no_vat', 803);
                }
            }
        }
        $this->possibleVatTypes = $possibleVatTypes;
    }

    /**
     * Initializes the list of possible vat rates.
     *
     * The possible vat rates depend on:
     * - the possible vat types.
     * - optionally, the date of the invoice.
     * - optionally, the country of the client.
     *
     * On finishing, $this->possibleVatRates will contain an array with possible
     * vat rates. A vat rate being an array with keys vatrate and vattype. This
     * to be able to retrieve to which vat type a vat rate belongs and to allow
     * for the same vat rate to be valid for multiple vat types.
     */
    protected function initPossibleVatRates()
    {
        $possibleVatRates = array();
        foreach ($this->possibleVatTypes as $vatType) {
            switch ($vatType) {
                case Api::VatType_National:
                case Api::VatType_MarginScheme:
                default:
                    $vatTypeVatRates = $this->getVatRates('nl');
                    break;
                case Api::VatType_NationalReversed:
                case Api::VatType_EuReversed:
                    $vatTypeVatRates = array(0);
                    break;
                case Api::VatType_RestOfWorld:
                    $vatTypeVatRates = array(-1);
                    break;
                case Api::VatType_ForeignVat:
                    $vatTypeVatRates = $this->getVatRates($this->invoice[Tag::Customer][Tag::CountryCode]);
                    break;
            }
            $vatTypeVatRates = array_map(function ($vatRate) use ($vatType) {
                return array(Tag::VatRate => $vatRate, Tag::VatType =>$vatType);
            }, $vatTypeVatRates);
            $possibleVatRates = array_merge($possibleVatRates, $vatTypeVatRates);
        }
        $this->possibleVatRates = $possibleVatRates;
    }

    /**
     * Anonymize customer if set so. We don't do this for business clients, only
     * consumers.
     */
    protected function fictitiousClient()
    {
        $customerSettings = $this->config->getCustomerSettings();
        if (!$customerSettings['sendCustomer'] && !$this->isCompany()) {
            $keysToKeep = array(Tag::Invoice);
            foreach ($this->invoice[Tag::Customer] as $key => $value) {
                if (!in_array($key, $keysToKeep)) {
                    unset($this->invoice[Tag::Customer][$key]);
                }
            }
            $this->invoice[Tag::Customer][Tag::Email] = $customerSettings['genericCustomerEmail'];
            $this->invoice[Tag::Customer][Tag::ContactStatus] = Api::ContactStatus_Disabled;
            $this->invoice[Tag::Customer][Tag::OverwriteIfExists] = 0;
        }
    }

    /**
     * Validates the email address of the invoice.
     *
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
            $this->result->addWarning(801,'', $this->t('message_warning_no_email'));
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

    protected function invoiceTemplate()
    {
        $invoiceSettings = $this->config->getInvoiceSettings();

        // Acumulus invoice template to use.
        if (isset($this->invoice[Tag::Customer][Tag::Invoice][Tag::PaymentStatus])
            && $this->invoice[Tag::Customer][Tag::Invoice][Tag::PaymentStatus] == Api::PaymentStatus_Paid
            // 0 = empty = use same invoice template as for non paid invoices.
            && $invoiceSettings['defaultInvoicePaidTemplate'] != 0
        ) {
            $this->addDefault($this->invoice[Tag::Customer][Tag::Invoice], Tag::Template, $invoiceSettings['defaultInvoicePaidTemplate']);
        } else {
            $this->addDefault($this->invoice[Tag::Customer][Tag::Invoice], Tag::Template, $invoiceSettings['defaultInvoiceTemplate']);
        }
    }

    /**
     * Converts amounts to euro if another currency was used.
     *
     * This method only converts amounts at the invoice level. When this method is executed, only the
     * invoice totals are set, the lines totals are not yet set.
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
     *   True if the totals are equal, false if not equal, null if undecided (all
     *   3 values are incomplete).
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

        $incomplete = count($this->lineTotalsStates['incomplete']);
        if ($incomplete <= 1) {
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
                    Meta::VatAmount => $missingVatAmount,
                ) + Creator::getVatRangeTags($missingVatAmount, $missingAmount, $countLines * 0.02, $countLines * 0.02)
                    + array(
                    Meta::LineType => Creator::LineType_Corrector,
                );
            // Correct and add this line (round of correcting has already been executed).
            if ($line[Meta::VatRateSource] === Creator::VatRateSource_Calculated) {
                $line = $this->LineCompletor->correctVatRateByRange($line);
            }
            $invoice[Tag::Line][] = $line;

            // Add warning.
            $this->changeInvoiceToConcept('message_warning_missing_amount_added', 809, $missingAmount, $missingVatAmount);
        } else {
            // Due to lack of information, we cannot add a missing line, even though
            // we know we are missing something: just add a warning.
            if (array_key_exists(Meta::LinesAmount, $this->lineTotalsStates['differ'])) {
                $missing = $this->lineTotalsStates['differ'][Meta::LinesAmount];
                $missingField = $this->t('amount_ex');
            }
            if (array_key_exists(Meta::LinesAmountInc, $this->lineTotalsStates['differ'])) {
                $missing = $this->lineTotalsStates['differ'][Meta::LinesAmountInc];
                $missingField = $this->t('amount_inc');
            }
            if (array_key_exists(Meta::LinesVatAmount, $this->lineTotalsStates['differ'])) {
                $missing = $this->lineTotalsStates['differ'][Meta::LinesVatAmount];
                $missingField = $this->t('amount_vat');
            }
            /** @noinspection PhpUndefinedVariableInspection */
            $this->changeInvoiceToConcept('message_warning_missing_amount_not_added', 810, $missingField, $missing);
        }
    }

    /**
     * Determines the vattype of the invoice.
     *
     * This method (and class) is aware of:
     * - The setting digitalServices.
     * - The country of the client.
     * - Whether the client is a company.
     * - The actual VAT rates on the day of the order.
     * - Whether there are margin products in the order.
     *
     * So to start with, any list of (possible) vat types is based on the above.
     * Furthermore this method is aware of:
     * - The fact that orders do not have to be split over different vat types,
     *   but that invoices should be split if both national and foreign VAT
     *   rates appear on the order.
     * - The fact that the vat type may be indeterminable if EU countries have
     *   VAT rates in common with NL and the settings indicate that this shop
     *   sells products in both vat type categories.
     *
     * If multiple vat types are possible, the invoice is sent as concept, so
     * that it may be corrected in Acumulus.
     */
    protected function completeVatType()
    {
        // If shop specific code or an event handler has already set the vat type,
        // we don't change it.
        if (empty($this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType])) {

            $possibleVatTypes = $this->getPossibleVatTypesByCorrectVatRates();
            $metaPossibleVatTypes = $this->possibleVatTypes;
            if (empty($possibleVatTypes)) {
                // Pick the first vat type that we thought was possible, but ...
                $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType] = reset($this->possibleVatTypes);
                // We must check as no vat type allows the actual vat rates.
                $message = 'message_warning_no_vattype';
                $code = 804;
            } elseif (count($possibleVatTypes) === 1) {
                // Pick the first and only (and therefore correct) vat type.
                $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType] = reset($possibleVatTypes);
                $message = '';
                $code = 805;
            } else {
                // Get the intersection of possible vat types per line.
                $vatTypesOnAllLines = $this->getVatTypesAppearingOnAllLines();
                if (empty($vatTypesOnAllLines)) {
                    // Pick the first and hopefully a correct vat type, but ...
                    $metaPossibleVatTypes = $possibleVatTypes;
                    $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType] = reset($possibleVatTypes);
                    // We must split.
                    $message = 'message_warning_multiple_vattype_must_split';
                    $code = 806;
                } else {
                    // Pick the first vat type that appears on all lines, but ...
                    $metaPossibleVatTypes = $vatTypesOnAllLines;
                    $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType] = reset($vatTypesOnAllLines);
                    // We may have to split.
                    $message = 'message_warning_multiple_vattype_may_split';
                    $code = 807;
                }

            }

            if (!empty($message)) {
                // Make the invoice a concept, so it can be changed in Acumulus
                // and add message and meta info.
                $this->changeInvoiceToConcept($message, $code);
                $this->invoice[Tag::Customer][Tag::Invoice][Meta::VatTypesPossible] = implode(',', $metaPossibleVatTypes);
            }
        }
    }

    /**
     * Returns a list of possible vat types based on possible vat types for all
     * lines with a "correct" vat rate.
     *
     * If that results in 1 vat type, that will be the vat type for the invoice,
     * otherwise a warning will be issued.
     *
     * This method may return multiple vat types because:
     * - If vat types share equal vat rates we cannot make a choice (e.g. NL and
     *   BE high VAT rates are equal).
     * - If the invoice ought to be split into multiple invoices because
     *   multiple vat regimes apply (digital services and normal goods) (e.g.
     *   both the FR 20% high rate and the NL 21% high rate appear on the
     *   invoice).
     *
     * @return int[]
     *   List of possible vat type for this invoice (keyed by the vat types).
     */
    protected function getPossibleVatTypesByCorrectVatRates()
    {
        // We only want to process correct vat rates.
        // Define vat types that do know a zero rate.
        $zeroRateVatTypes = array(
            Api::VatType_National,
            Api::VatType_NationalReversed,
            Api::VatType_EuReversed,
            Api::VatType_RestOfWorld,
        );

        // We keep track of vat types found per appearing vat rate.
        // The intersection of these sets should result in the new, hopefully
        // smaller list, of possible vat types.
        $invoiceVatTypes = array();
        foreach ($this->invoice[Tag::Customer][Tag::Invoice][Tag::Line] as &$line) {
            if ($this->isCorrectVatRate($line[Meta::VatRateSource])) {
                // We ignore "0" vat rates (0 and -1).
                if ($line[Tag::VatRate] > 0) {
                    $lineVatTypes = array();
                    foreach ($this->possibleVatRates as $vatRateInfo) {
                        if ($vatRateInfo[Tag::VatRate] == $line[Tag::VatRate]) {
                            // Add the value also as key to ensure uniqueness.
                            $invoiceVatTypes[$vatRateInfo[Tag::VatType]] = $vatRateInfo[Tag::VatType];
                            $lineVatTypes[$vatRateInfo[Tag::VatType]] = $vatRateInfo[Tag::VatType];
                        }
                    }
                } else {
                    // Reduce the vat types to those that have a zero rate.
                    $lineVatTypes = array_intersect($this->possibleVatTypes, $zeroRateVatTypes);
                    foreach ($lineVatTypes AS $lineVatType) {
                        $invoiceVatTypes[$lineVatType] = $lineVatType;
                    }
                }
                $line[Meta::VatTypesPossible] = implode(',', $lineVatTypes);
            }
        }

        return $invoiceVatTypes;
    }


    /**
     * Returns a list of vat types that are possible for all lines of the invoice.
     *
     * @return int[]
     */
    protected function getVatTypesAppearingOnAllLines()
    {
        $result = null;
        foreach ($this->invoice[Tag::Customer][Tag::Invoice][Tag::Line] as $line) {
            if (isset($line[Meta::VatTypesPossible])) {
                $lineVatTypes = explode(',', $line[Meta::VatTypesPossible]);
                if ($result === null) {
                    // 1st line.
                    $result = $lineVatTypes;
                } else {
                    $result = array_intersect($result, $lineVatTypes);
                }
            }
        }
        return $result;
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
        if (isset($this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType]) && $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType] == Api::VatType_MarginScheme) {
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
                $line[Meta::UnitPriceOld] = $line[Tag::UnitPrice];
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
     * Acumulus distinguishes between 0% vat (vatrate = 0) and vat free
     * (vatrate = -1). 0% vat should be used with reversed vat invoices and
     * for products invoiced outside the EU. Vat free should be used for vat
     * free products and services (e.g. care, education) and services invoiced
     * outside the EU.
     *
     * Note: To do this perfectly, we should be able to distinguish between services and products. Therefore the nature
     * field should be filled in or the shop should only sell products or only services, but that is currently not a setting.
     * If not, we act as if the line invoices a product.
     * @todo: add new setting: only sell products, only sell services.
     *
     * Precondition: The shop does sell vat free products/services (or that
     * setting was not filled in).
     *
     * @see https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/tarieven_en_vrijstellingen/
     * @see https://wiki.acumulus.nl/index.php?page=facturen-naar-het-buitenland:
     * vat type = 2, 3 (reversed vat), or 4 (products outside EU): vat = 0%.
     * vat type = 1 (normal invoice), 5 (margin) or 6 (digital services outside
     *   EU): vat = -1 (vat-free)
     */
    protected function correct0VatToVatFree()
    {
        $vatTypesAllowingVatFree = array(Api::VatType_National, Api::VatType_ForeignVat, Api::VatType_MarginScheme);
        $vatType = $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType];
        if (in_array($vatType, $vatTypesAllowingVatFree)) {
            foreach ($this->invoice[Tag::Customer][Tag::Invoice][Tag::Line] as &$line) {
                if ($this->lineHas0VatRate($line)) {
                    $line[Tag::VatRate] = Api::VatFree;
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
     * Returns whether the invoice has at least 1 line with a 0% or vat free vat rate.
     *
     * The invoice lines are expected to be flattened when we arrive here.
     *
     * @param array $lines
     *   The lines to determine the vat situation for.
     * @param bool $includeDiscountLines
     *   Discount lines representing a partial payment should be VAT free and
     *   should not always trigger a return value of true. This parameter can be
     *   used to indicate what to do with VAT free discount lines.
     *
     * @return bool
     */
    protected function getVatSituation(array $lines, $includeDiscountLines = false)
    {
        $result = 0;
        foreach ($lines as $line) {
            if ($line[Meta::LineType] !== Creator::LineType_Discount || $includeDiscountLines) {
                $result |= $this->getLineVatSituation($line);
            }
            if (!empty($line[Meta::ChildrenLines])) {
                $result |= $this->getVatSituation($line[Meta::ChildrenLines], $includeDiscountLines);
            }
        }
        return $result;
    }

    /**
     * Returns the vat situation of the given line.
     *
     * @param array $line
     *
     * @return int
     *   Either:
     *   - static::Vat_HasVat: line has a positive vat rate.
     *   - static::Vat_Has0Vat: line has a 0% or vat free vat rate.
     *   - static::Vat_Unknown: it is unknown whether the line has vat or not.
     *   The latter will be the case with free products and a webshop that does
     *   not store vat rates with order lines.
     */
    protected function getLineVatSituation(array $line)
    {
        $result = static::Vat_Unknown;
        if ($this->lineHasVatRate($line)) {
            $result = static::Vat_HasVat;
        }
        if ($this->lineHas0VatRate($line)) {
            $result = static::Vat_Has0Vat;
        }
        return $result;
    }

    /**
     * Returns whether the line has a positive vat rate.
     *
     * @param array $line
     *   The invoice line.
     *
     * @return bool
     *   True if the line has a positive vat rate, false otherwise.
     */
    protected function lineHasVatRate(array $line)
    {
        $result = false;
        if (isset($line[Tag::VatRate]) && (float) $line[Tag::VatRate] > 0.0) {
            $result = true;
        } elseif (isset($line[Meta::VatAmount]) && !Number::isZero($line[Meta::VatAmount])) {
            $result = true;
        } elseif (isset($line[Meta::LineVatAmount]) && !Number::isZero($line[Meta::LineVatAmount])) {
            $result = true;
        }
        return $result;
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
        if (isset($line[Tag::VatRate]) && (Number::isZero($line[Tag::VatRate]) || Number::floatsAreEqual($line[Tag::VatRate], -1.0))) {
            $result = true;
        }
        return $result;
    }

    /**
     * Returns whether the invoice has at least 1 line with a costprice set.
     *
     * @param array $lines
     *
     * @return bool
     */
    protected function invoiceHasLineWithCostPrice(array $lines)
    {
        $hasLineWithCostPrice = false;
        foreach ($lines as $line) {
            if (isset($line[Tag::CostPrice])) {
                $hasLineWithCostPrice = true;
                break;
            } elseif (!empty($line[Meta::ChildrenLines]) && $this->invoiceHasLineWithCostPrice($line[Meta::ChildrenLines])) {
                $hasLineWithCostPrice = true;
                break;
            }
        }
        return $hasLineWithCostPrice;
    }

    /**
     * Helper method to get the vat rates for the current invoice.
     *
     * - This method contacts the Acumulus server.
     * - The vat rates returned reflect those as they were at the invoice date.
     * - No zero vat rates are returned.
     *
     * @param string $countryCode
     *   The country to fetch the vat rates for.
     *
     * @return float[]
     *   Actual type will be string[] containing strings representing floats.
     *
     * @see \Siel\Acumulus\Web\Service::getVatInfo().
     */
    protected function getVatRates($countryCode)
    {
        $date = $this->getInvoiceDate();
        $result = $this->service->getVatInfo($countryCode, $date);
        if ($result->hasMessages()) {
            $this->result->mergeMessages($result);
        }
        $vatInfo = $result->getResponse();
        // PHP5.5: array_column($vatInfo, Tag::VatRate);
        $vatInfo = array_unique(array_map(function ($vatInfo1) {
            return $vatInfo1[Tag::VatRate];
        }, $vatInfo));
        return $vatInfo;
    }

    /**
     * Returns the invoice date in the iso yyyy-mm-dd format.
     *
     * @return string
     */
    protected function getInvoiceDate()
    {
        $date = !empty($this->invoice[Tag::Customer][Tag::Invoice][Tag::IssueDate]) ? $this->invoice[Tag::Customer][Tag::Invoice][Tag::IssueDate] : date(API::DateFormat_Iso);
        return $date;
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
     * Wrapper around Countries::isEu().
     *
     * @return bool
     */
    protected function isEu()
    {
        return $this->countries->isEu($this->invoice[Tag::Customer][Tag::CountryCode]);
    }

    /**
     * Returns whether the client is located outside the EU.
     *
     * @return bool
     */
    protected function isOutsideEu()
    {
        return !$this->isNl() && !$this->isEu();
    }

    /**
     * Returns whether the client is a company with a vat number.
     *
     * @return bool
     */
    protected function isCompany()
    {
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
     *   The vat rate source.
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
     * Makes the invoice a concept invoice and optionally adds a warning.
     *
     * @param string $messageKey
     *   The key of the message to add as warning,  or the empty string if no
     *   warning has to be added.
     * @param int $code
     *   The code for this message.
     */
    protected function changeInvoiceToConcept($messageKey, $code)
    {
        if ($messageKey !== '') {
            $message = $this->t($messageKey);
            if (func_num_args() > 2) {
                $args = func_get_args();
                $message = vsprintf($message, array_slice($args, 2));
            }
            $emailAsPdfSettings = $this->config->getEmailAsPdfSettings();
            if ($emailAsPdfSettings['emailAsPdf']) {
                $message .= ' ' . $this->t('message_warning_no_pdf');
            }
            $this->result->addWarning($code, '', $message);
        }
        $this->invoice[Tag::Customer][Tag::Invoice][Tag::Concept] = Api::Concept_Yes;
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
