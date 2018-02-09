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


        // Complete lines as far as they can be completed om their own.
        $this->initPossibleVatTypes();
        $this->initPossibleVatRates();
        $this->convertToEuro();
        $this->invoice = $this->LineCompletor->complete($this->invoice, $this->possibleVatTypes, $this->possibleVatRates);

        // Check if we are missing an amount and, if so, add a line for it.
        $this->completeLineTotals();
        $areTotalsEqual = $this->areTotalsEqual();
        if (!$areTotalsEqual) {
            $this->addMissingAmountLine($areTotalsEqual);
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
        if (in_array($this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType], array(Api::VatType_National, Api::VatType_ForeignVat, Api::VatType_MarginScheme))
            && $this->invoiceHasLineWith0VatRate())
        {
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
            if ($this->invoiceHasLineWithVat($this->invoice[Tag::Customer][Tag::Invoice][Tag::Line])) {
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
            } else {
                // No VAT at all: National/EU reversed vat, only vat free
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
                    // for now I disabled the warning as it appeared on
                    // 0-amount invoices that could be corrected after all, plus
                    // that it often (always) appeared in combination with other
                    // warnings.
                    $possibleVatTypes[] = Api::VatType_National;
                    $possibleVatTypes[] = $this->isNl() ? Api::VatType_NationalReversed : Api::VatType_EuReversed;
                    $this->changeInvoiceToConcept($this->result->hasMessages() ? '' : 'message_warning_no_vat', 803);
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
        if ($this->hasOtherCurrency($this->invoice)) {
            // Convert all amounts at the invoice level.
            $invoice = &$this->invoice[Tag::Customer][Tag::Invoice];
            $this->convertAmount($invoice, Meta::InvoiceAmount, $invoice[Meta::CurrencyRate]);
            $this->convertAmount($invoice, Meta::InvoiceAmountInc, $invoice[Meta::CurrencyRate]);
            $this->convertAmount($invoice, Meta::InvoiceVatAmount, $invoice[Meta::CurrencyRate]);
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
        $this->incompleteValues = array();

        $invoiceLines = $this->invoice[Tag::Customer][Tag::Invoice][Tag::Line];
        foreach ($invoiceLines as $line) {
            if (isset($line[Meta::LineAmount])) {
                $linesAmount += $line[Meta::LineAmount];
            } elseif (isset($line[Tag::UnitPrice])) {
                $linesAmount += $line[Tag::Quantity] * $line[Tag::UnitPrice];
            } else {
                $this->incompleteValues[Meta::LinesAmount ] = Meta::LinesAmount ;
            }

            if (isset($line[Meta::LineAmountInc])) {
                $linesAmountInc += $line[Meta::LineAmountInc];
            } elseif (isset($line[Meta::UnitPriceInc])) {
                $linesAmountInc += $line[Tag::Quantity] * $line[Meta::UnitPriceInc];
            } else {
                $this->incompleteValues[Meta::LinesAmountInc] = Meta::LinesAmountInc;
            }

            if (isset($line[Meta::LineVatAmount])) {
                $linesVatAmount += $line[Meta::LineVatAmount];
            } elseif (isset($line[Meta::VatAmount])) {
                $linesVatAmount += $line[Tag::Quantity] * $line[Meta::VatAmount];
            } else {
                $this->incompleteValues[Meta::LinesVatAmount] = Meta::LinesVatAmount;
            }
        }

        $this->invoice[Tag::Customer][Tag::Invoice][Meta::LinesAmount ] = $linesAmount;
        $this->invoice[Tag::Customer][Tag::Invoice][Meta::LinesAmountInc] = $linesAmountInc;
        $this->invoice[Tag::Customer][Tag::Invoice][Meta::LinesVatAmount] = $linesVatAmount;
        if (!empty($this->incompleteValues)) {
            sort($this->incompleteValues);
            $this->invoice[Tag::Customer][Tag::Invoice][Meta::LinesIncomplete] = implode(',', $this->incompleteValues);
        }
    }

    /**
     * Compares the invoice totals metadata with the line totals metadata.
     *
     * If any of the 3 values are equal we do consider the totals to be equal
     * (except for a 0 VAT amount (for reversed VAT invoices)). This because in
     * many cases 1 or 2 of the 3 values are either incomplete or incorrect.
     *
     * @todo: if 1 is correct but other not, that would be an indication of an error: warn?
     * @return bool|null
     *   True if the totals are equal, false if not equal, null if undecided (all
     *   3 values are incomplete).
     */
    protected function areTotalsEqual()
    {
        $invoice = $this->invoice[Tag::Customer][Tag::Invoice];
        if (!in_array(Meta::LinesAmount , $this->incompleteValues) && Number::floatsAreEqual($invoice[Meta::InvoiceAmount], $invoice[Meta::LinesAmount ], 0.05)) {
            return true;
        }
        if (!in_array(Meta::LinesAmountInc, $this->incompleteValues) && Number::floatsAreEqual($invoice[Meta::InvoiceAmountInc], $invoice[Meta::LinesAmountInc], 0.05)) {
            return true;
        }
        if (!in_array(Meta::LinesVatAmount, $this->incompleteValues)
            && Number::floatsAreEqual($invoice[Meta::InvoiceVatAmount], $invoice[Meta::LinesVatAmount], 0.05)
            && !Number::isZero($invoice[Meta::InvoiceVatAmount])
        ) {
            return true;
        }
        return count($this->incompleteValues) === 3 ? null : false;
    }

    /**
     * Adds an invoice line if the total amount (meta-invoice-amount) is not
     * matching the total amount of the lines.
     *
     * This can happen if:
     * - we missed a fee that is stored in custom fields
     * - a manual adjustment
     * - an error in the logic or data as provided by the webshop.
     *
     * However, we can only add this line if we have at least 2 complete values,
     * that is, there are no strategy lines,
     *
     * @param bool|null $areTotalsEqualResult
     *   Result of areTotalsEqual() (false or null)
     */
    protected function addMissingAmountLine($areTotalsEqualResult)
    {
        $invoice = &$this->invoice[Tag::Customer][Tag::Invoice];
        if (!in_array(Meta::LinesAmount , $this->incompleteValues)) {
            $missingAmount = $invoice[Meta::InvoiceAmount] - $invoice[Meta::LinesAmount ];
        }
        if (!in_array(Meta::LinesAmountInc, $this->incompleteValues)) {
            $missingAmountInc = $invoice[Meta::InvoiceAmountInc] - $invoice[Meta::LinesAmountInc];
        }
        if (!in_array(Meta::LinesVatAmount, $this->incompleteValues)) {
            $missingVatAmount = $invoice[Meta::InvoiceVatAmount] - $invoice[Meta::LinesVatAmount];
        }

        if (count($this->incompleteValues) <= 1) {
            if (!isset($missingAmount)) {
                /** @noinspection PhpUndefinedVariableInspection */
                $missingAmount = $missingAmountInc - $missingVatAmount;
            }
            if (!isset($missingVatAmount)) {
                /** @noinspection PhpUndefinedVariableInspection */
                $missingVatAmount = $missingAmountInc - $missingAmount;
            }

            $settings = $this->config->getInvoiceSettings();
            if ($settings['addMissingAmountLine']) {
                if ($this->source->getType() === Source::CreditNote) {
                    $product = $this->t('refund_adjustment');
                } elseif ($missingAmount < 0.0) {
                    $product = $this->t('discount_adjustment');
                } else {
                    $product = $this->t('fee_adjustment');
                }
                $countLines = count($invoice[Tag::Line]);
                $line = array(
                        Tag::Product => $product,
                        Tag::Quantity => 1,
                        Tag::UnitPrice => $missingAmount,
                        Meta::VatAmount => $missingVatAmount,
                    ) + Creator::getVatRangeTags($missingVatAmount, $missingAmount, $countLines * 0.02, $countLines * 0.02)
                    + array(
                        Meta::LineType => Creator::LineType_Corrector,
                    );
                // Correct and add this line.
                if ($line[Meta::VatRateSource] === Creator::VatRateSource_Calculated) {
                    $line = $this->LineCompletor->correctVatRateByRange($line);
                }
                $invoice[Tag::Line][] = $line;
            } else {
                // Add some diagnostic info to the message sent.
                // @todo: this could/should be turned into a warning (after some testing).
                /** @noinspection PhpUndefinedVariableInspection */
                $invoice[Meta::MissingAmount] = "Ex: $missingAmount, Inc: $missingAmountInc, VAT: $missingVatAmount";
            }
        } else {
            if ($areTotalsEqualResult === false) {
                // Due to lack of information, we cannot add a missing line, even though
                // we know we are missing something ($areTotalsEqualResult is false, not
                // null). Add some diagnostic info to the message sent.
                // @todo: this could/should be turned into a warning (after some testing).
                $invoice[Meta::MissingAmount] = array();
                if (isset($missingAmount)) {
                    $invoice[Meta::MissingAmount][] = "Ex: $missingAmount";
                }
                if (isset($missingAmountInc)) {
                    $invoice[Meta::MissingAmount][] = "Inc: $missingAmountInc";
                }
                if (isset($missingVatAmount)) {
                    $invoice[Meta::MissingAmount][] = "VAT: $missingVatAmount";
                }
                $invoice[Meta::MissingAmount] = implode(', ', $invoice[Meta::MissingAmount]);
            }
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
    protected function correctMarginInvoice() {
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
     * Returns whether the invoice has at least 1 line with a non-0 vat rate.
     *
     * 0 (VAT free/reversed VAT) and -1 (no VAT) are valid 0-vat rates.
     * As vatrate may be null, the vatamount value is also checked.
     *
     * @param array $lines
     *
     * @return bool
     */
    protected function invoiceHasLineWithVat(array $lines)
    {
        $hasLineWithVat = false;
        foreach ($lines as $line) {
            if (!empty($line[Tag::VatRate])) {
                if (!Number::isZero($line[Tag::VatRate]) && !Number::floatsAreEqual($line[Tag::VatRate], -1.0)) {
                    $hasLineWithVat = true;
                    break;
                }
            } elseif (!empty($line[Meta::VatAmount]) && !Number::isZero($line[Meta::VatAmount])) {
                $hasLineWithVat = true;
                break;
            } elseif (!empty($line[Meta::LineVatAmount]) && !Number::isZero($line[Meta::LineVatAmount])) {
                $hasLineWithVat = true;
                break;
            } elseif (!empty($line[Meta::ChildrenLines]) && $this->invoiceHasLineWithVat($line[Meta::ChildrenLines])) {
                $hasLineWithVat = true;
                break;
            }
        }
        return $hasLineWithVat;
    }

    /**
     * Returns whether the invoice has at least 1 line with a 0% or vat free vat rate.
     *
     * The invoice lines are expected to be flattened when we arrive here.
     *
     * @param bool $includeDiscountLines
     *   Discount lines representing a partial payment should be VAT free and
     *   should not always trigger a return value of true. This parameter can be
     *   used to indicate what to do with VAT free discount lines.
     *
     * @return bool
     */
    protected function invoiceHasLineWith0VatRate($includeDiscountLines = false)
    {
        $hasLineWith0Vat = false;
        foreach ($this->invoice[Tag::Customer][Tag::Invoice][Tag::Line] as $line) {
            if ($this->lineHas0VatRate($line) && ($line[Meta::LineType] !== Creator::LineType_Discount || $includeDiscountLines)) {
                $hasLineWith0Vat = true;
                break;
            }
        }
        return $hasLineWith0Vat;
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
        return isset($line[Tag::VatRate]) &&
               Number::isZero($line[Tag::VatRate]) || Number::floatsAreEqual($line[Tag::VatRate], -1.0);
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
        $vatInfo = $this->service->getVatInfo($countryCode, $date)->getResponse();
        // PHP5.5: array_column($vatInfo, Tag::VatRate);
        $result = array_unique(array_map(function ($vatInfo1) {
            return $vatInfo1[Tag::VatRate];
        }, $vatInfo));
        return $result;
    }

    /**
     * Returns the invoice date in the iso yyyy-mm-dd format.
     *
     * @return string
     */
    protected function getInvoiceDate()
    {
        $date = !empty($this->invoice[Tag::Customer][Tag::Invoice][Tag::IssueDate]) ? $this->invoice[Tag::Customer][Tag::Invoice][Tag::IssueDate] : date('Y-m-d');
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
     * Returns whether the invoice uses another currency.
     *
     * @param array $invoice
     *
     * @return bool
     *   True if the invoice uses another currency, false otherwise.
     */
    public function hasOtherCurrency(array $invoice)
    {
        $invoice = $invoice[Tag::Customer][Tag::Invoice];
        return !empty($invoice[Meta::Currency]) && !empty($invoice[Meta::CurrencyRate]) && (float) $invoice[Meta::CurrencyRate] != 1.0;
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
            $array[$key] = (float) $array[$key] * (float) $conversionRate;
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
            $emailAsPdfSettings = $this->config->getEmailAsPdfSettings();
            if ($emailAsPdfSettings['emailAsPdf']) {
                $message .= ' ' . $this->t('message_warning_no_pdf');
            }
            $this->result->addWarning($code, '', $message);
        }
        $this->invoice[Tag::Customer][Tag::Invoice][Tag::Concept] = Api::Concept_Yes;
    }
}
