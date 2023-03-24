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
 * @noinspection DuplicatedCode  During the transition to Collectors duplicate
 *   code will exist.
 */

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Countries;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Helpers\Token;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

use function array_key_exists;
use function func_get_args;
use function in_array;
use function is_array;
use function is_int;
use function is_string;

/**
 * Creates a raw version of the Acumulus invoice from a {@see Source}.
 *
 * Introduction
 * ------------
 * The Acumulus invoice structure is specified on:
 * {@link https://www.siel.nl/acumulus/API/Invoicing/Add_Invoice/ }
 *
 * In addition to the scheme as defined over there, additional metadata tags
 * are expected to be added. Some of these are required for the completor phase,
 * while others are optional and are merely used for support (deducing where
 * some values came from, or what path was taken to get a value).
 *
 * In some cases,the tags may get a PHP null value, indicating the absence of a
 * value that should be there but could not be (easily) retrieved from the
 * invoice source. These will be replaced with actual values in the completor
 * stage.
 *
 * This base class:
 * - Implements the basic break down into smaller actions that web shops should
 *   subsequently implement.
 * - Provides helper methods for some recurring functionality.
 * - Documents the expectations of each method to be implemented by a web shop's
 *   Creator class.
 * - Documents the meta tags expected or suggested.
 *
 * A raw invoice:
 * - Contains all customer tags (as far as they should or can be set), even if
 *   the use configured to not send customer data to Acumulus.
 * - Contains most invoice tags (as far as they should or can be set), except
 *   'vattype' and 'concept'.
 * - Contains all invoice lines (based on order data), but:
 *     - Possibly hierarchically structured.
 *     - Does not have to be complete or correct.
 *     - In the used currency, not necessarily Euro.
 *
 * Hierarchically structured invoice lines
 * ---------------------------------------
 * If your shop supports:
 * 1 options or variants, like size, color, etc.
 * 2 bundles or composed products
 * Then you should create hierarchical lines for these product types.
 *
 * ad 1)
 * For each option or variant you add a child line. Set the meta tag
 * 'meta-vatrate-source' to Creator::VatRateSource_Parent. Copy the quantity
 * from the parent to the child. Price info is probably on the parent line only,
 * unless your shop administers additional or reduced costs for a given option
 * on the child lines.
 *
 * ad 2)
 * For each product that is part of the bundle add a child line. As this may be
 * a bundle/composed product on its own, you may create multiple levels, there
 * is no maximum depth on child lines.
 *
 * Price info may be on the child lines, but may also be on the parent line,
 * especially so, if the bundle is cheaper that its separate parts. The child
 * lines may have their own vat rates, so depending on your situation fetch the
 * vat info from the child line objects itself or copy it from the parent. When
 * left empty, it is copied from the parent in the Completor phase.
 *
 * Hierarchical lines are "corrected" in the Completor phase, see
 * {@see FlattenerInvoiceLines}
 */
abstract class Creator
{
    public const VatRateSource_Exact = 'exact';
    public const VatRateSource_Exact0 = 'exact-0';
    public const VatRateSource_Calculated = 'calculated';
    public const VatRateSource_Completor = 'completor';
    public const VatRateSource_Strategy = 'strategy';
    public const VatRateSource_Parent = 'parent';
    public const VatRateSource_Child = 'child';
    public const VatRateSource_Creator_Lookup = 'creator-lookup';
    public const VatRateSource_Creator_Missing_Amount = 'creator-missing-amount';

    public const LineType_OrderItem = 'order-item';
    public const LineType_Shipping = 'shipping';
    public const LineType_PaymentFee = 'payment';
    public const LineType_GiftWrapping = 'gift';
    public const LineType_Manual = 'manual';
    public const LineType_Discount = 'discount';
    public const LineType_Voucher = 'voucher';
    public const LineType_Other = 'other';
    public const LineType_Corrector = 'missing-amount-corrector';

    protected Config $config;
    protected ShopCapabilities $shopCapabilities;
    protected Token $token;
    protected Translator $translator;
    protected Log $log;
    protected Countries $countries;
    protected Container $container;
    /**
     * @var array
     *   Resulting Acumulus invoice.
     *
     * @todo: This really should become an object that can be passed around like
     *   Source. We could add a lot of simple query methods to this object. And
     *   if we are going to extract groups of methods into separate "knowledge"
     *   classes, it will be easier to pass it around. To keep current code
     *   compatible, it should be of a type that extends \ArrayAccess. The other
     *   array like interfaces (Countable, Iterator, Traversable) are probably
     *   not needed.
     */
    protected array $invoice = [];
    protected Source $invoiceSource;
    /**
     * The list of sources to search for properties.
     */
    protected array $propertySources;

    public function __construct(Token $token, Countries $countries, ShopCapabilities $shopCapabilities, Container $container, Config $config, Translator $translator, Log $log)
    {
        $this->token = $token;
        $this->countries = $countries;
        $this->container = $container;
        $this->shopCapabilities = $shopCapabilities;
        $this->config = $config;
        $this->log = $log;
        $this->translator = $translator;
        $invoiceHelperTranslations = new Translations();
        $this->translator->add($invoiceHelperTranslations);
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
     * Sets the source to create the invoice for.
     *
     * @param Source $invoiceSource
     */
    protected function setInvoiceSource(Source $invoiceSource): void
    {
        $this->invoiceSource = $invoiceSource;
        if (!in_array($invoiceSource->getType(), [Source::Order, Source::CreditNote], true)) {
            $this->log->error('Creator::setSource(): unknown source type %s', $this->invoiceSource->getType());
        }
    }

    /**
     * Sets the list of sources to search for a property when expanding tokens.
     */
    protected function setPropertySources(): void
    {
        $this->propertySources = [];
        $this->propertySources['invoiceSource'] = $this->invoiceSource;
        $this->propertySources['invoiceSourceType'] = ['label' => $this->t($this->propertySources['invoiceSource']->getType())];

        if (array_key_exists(Source::CreditNote, $this->shopCapabilities->getSupportedInvoiceSourceTypes())) {
            $this->propertySources['originalInvoiceSource'] = $this->invoiceSource->getOrder()->getSource();
            $this->propertySources['originalInvoiceSourceType'] =
                ['label' => $this->t($this->propertySources['originalInvoiceSource']->getType())];
        }
        $this->propertySources['source'] = $this->invoiceSource->getSource();
        if (array_key_exists(Source::CreditNote, $this->shopCapabilities->getSupportedInvoiceSourceTypes())) {
            if ($this->invoiceSource->getType() === Source::CreditNote) {
                $this->propertySources['refund'] = $this->invoiceSource->getSource();
            }
            $this->propertySources['order'] = $this->invoiceSource->getOrder()->getSource();
            if ($this->invoiceSource->getType() === Source::CreditNote) {
                $this->propertySources['refundedInvoiceSource'] = $this->invoiceSource->getOrder()->getSource();
                $this->propertySources['refundedInvoiceSourceType'] =
                    ['label' => $this->t($this->propertySources['refundedInvoiceSource']->getType())];
                $this->propertySources['refundedOrder'] = $this->invoiceSource->getOrder()->getSource();
            }
        }
    }

    /**
     * Adds an object as property source.
     *
     * The object is added to the start of the array. So, upon token expansion,
     * it will be searched before other (already added) property sources.
     *
     * @param string $name
     *   The name to use for the source
     * @param object|array $property
     *   The source object to add.
     */
    public function addPropertySource(string $name, $property): void
    {
        $this->propertySources = [$name => $property] + $this->propertySources;
    }

    /**
     * Removes an object as property source.
     *
     * @param string $name
     *   The name of the source to remove.
     */
    public function removePropertySource(string $name): void
    {
        unset($this->propertySources[$name]);
    }

    /**
     * Creates an Acumulus invoice from an order or credit note.
     *
     * @param Source $source
     *  The web shop order.
     *
     * @return array
     *   The acumulus invoice for this order.
     */
    public function create(Source $source): array
    {
        $this->setInvoiceSource($source);
        $this->setPropertySources();
        $this->invoice = [];
        $this->invoice[Tag::Customer] = $this->getCustomer();
        $this->invoice[Tag::Customer][Tag::Invoice] = $this->getInvoice();
        $this->invoice[Tag::Customer][Tag::Invoice][Tag::Line] = $this->getInvoiceLines();
        $emailAsPdfSettings = $this->config->getEmailAsPdfSettings();
        if ($emailAsPdfSettings['emailAsPdf']) {
            $emailTo = !empty($this->invoice[Tag::Customer][Tag::Email]) ? $this->invoice[Tag::Customer][Tag::Email] : '';
            $emailAsPdf = $this->getEmailAsPdf($emailTo);
        }
        if (!empty($emailAsPdf)) {
            $this->invoice[Tag::Customer][Tag::Invoice][Tag::EmailAsPdf] = $emailAsPdf;
        }
        return $this->invoice;
    }

    /**
     * Creates an Acumulus emailAsPdf structure from an order or credit note.
     *
     * NOTE: This is a temporary function, added in 7.4.0, to allow the new
     * mail invoice buttons (on the order list or detail page) to also use token
     * expansion. For 8.0, we are already working on new "Collectors" that will
     * replace this Creator and are more fine-grained, so we wil have a ready to
     * use separate emailAsPdf Collector.
     *
     * @param Source $source
     *  The web shop order.
     * @param bool $forInvoice
     *   True if we want the emailAsPdf section for mailing an invoice, false if
     *   we want to email the packing slip.
     *
     * @return array
     *   The acumulus emailAsPdf structure for this order.
     */
    public function createEmailAsPdf(Source $source, bool $forInvoice = true): array
    {
        $this->setInvoiceSource($source);
        $this->setPropertySources();
        $emailTo = '';
        if ($forInvoice) {
            $customerSettings = $this->config->getCustomerSettings();
            if (!empty($customerSettings['email'])) {
                $value = $this->getTokenizedValue($customerSettings['email']);
                if (!empty($value)) {
                    $emailTo = $value;
                }
            }
        }
        return $this->getEmailAsPdf($emailTo, $forInvoice);
    }

    /**
     * Returns the 'customer' part of the invoice add structure.
     *
     * The following keys are allowed/expected by the API:
     * - 'type'
     * - 'contactid': will not be set. Acumulus id for this customer, in the
     *   absence of this value, the API uses the email address as identifying
     *   value.
     * - 'contactyourid': web shop customer id.
     * - 'contactstatus'
     * - 'companyname1'
     * - 'companyname2'
     * - 'vatnumber'
     * - 'fullname'
     * - 'salutation'
     * - 'address1'
     * - 'address2'
     * - 'postalcode'
     * - 'city'
     * - 'countrycode'
     * - 'country'
     * - 'telephone'
     * - 'fax'
     * - 'email': used to identify clients.
     * - 'overwriteifexists'
     * - 'bankaccountnumber': will not be set: no web shop software provides
     *    this.
     * - 'mark'
     * - 'disableduplicates': not (yet) supported.
     *
     * At the customer level no meta tags are defined.
     *
     * Extending classes should normally not have to override this method as all
     * values are fetched via configurable settings that may contain tokens
     * (Dutch: veldverwijzingen) that refer to properties of objects in the web
     * shop (property sources). The exception is the (ISO) country code which
     * may not be easy to fetch via a property source as this might include a
     * database lookup to a "Countries" table. This is fetched via
     * Source::getCountryCode().
     *
     * @return array
     *   A keyed array with the customer data.
     */
    protected function getCustomer(): array
    {
        $customer = [];
        $customerSettings = $this->config->getCustomerSettings();
        $this->addDefault($customer, Tag::Type, $customerSettings['defaultCustomerType']);
        // Add an empty value to have it rendered high up in the xml message.
        // I know, I am neurotic...
        $customer[Tag::VatTypeId] = '';
        $this->addTokenDefault($customer, Tag::ContactYourId, $customerSettings['contactYourId']);
        $this->addDefaultEmpty($customer, Tag::ContactStatus, $customerSettings['contactStatus']);
        $this->addTokenDefault($customer, Tag::CompanyName1, $customerSettings['companyName1']);
        $this->addTokenDefault($customer, Tag::CompanyName2, $customerSettings['companyName2']);
        $this->addTokenDefault($customer, Tag::VatNumber, $customerSettings['vatNumber']);
        $this->addTokenDefault($customer, Tag::FullName, $customerSettings['fullName']);
        $this->addTokenDefault($customer, Tag::Salutation, $customerSettings['salutation']);
        $this->addTokenDefault($customer, Tag::Address1, $customerSettings['address1']);
        $this->addTokenDefault($customer, Tag::Address2, $customerSettings['address2']);
        $this->addTokenDefault($customer, Tag::PostalCode, $customerSettings['postalCode']);
        $this->addTokenDefault($customer, Tag::City, $customerSettings['city']);
        $customer[Tag::CountryCode] = $this->countries->convertEuCountryCode($this->invoiceSource->getCountryCode());
        // Add 'nl' as default country code. As other methods in the creator may
        // depend on this value being filled, e.g. looking up product vat rates,
        // we cannot wait until the completion phase, so we add it here.
        $this->addDefault($customer, Tag::CountryCode, 'nl');
        $this->addDefault($customer, Tag::Country, $this->countries->getCountryName($customer[Tag::CountryCode]));
        $this->addTokenDefault($customer, Tag::Telephone, $customerSettings['telephone']);
        $this->addTokenDefault($customer, Tag::Fax, $customerSettings['fax']);
        $this->addTokenDefault($customer, Tag::Email, $customerSettings['email']);
        $this->addDefaultEmpty($customer, Tag::OverwriteIfExists, $customerSettings['overwriteIfExists'] ? Api::OverwriteIfExists_Yes : Api::OverwriteIfExists_No);
        $this->addTokenDefault($customer, Tag::Mark, $customerSettings['mark']);
        return $customer;
    }

    /**
     * Returns the 'invoice' part of the invoice add structure.
     *
     * The following keys are allowed/expected by the API:
     * - 'concept': in case of errors or warnings this may be overridden by the
     *   Completor to make it a concept.
     * - 'concepttype' : not (yet) used.
     * - 'number'
     * - 'vattype': will be filled by the Completor.
     * - 'issuedate'
     * - 'costcenter'
     * - 'accountnumber'
     * - 'paymentstatus'
     * - 'paymentdate'
     * - 'description'
     * - 'descriptiontext'
     * - 'template'
     * - 'invoicenotes'
     *
     * Metadata (not recognised by the API but used later on by the Creator or
     * Completor, or for support and debugging purposes):
     * - See {@see Source::getCurrency()}:
     *     - 'currency'
     *     - 'currency-rate'
     *     - 'currency-do-convert'
     * - See {@see Source::getPaymentMethod()}:
     *     - 'meta-payment-method': an id of the payment method used.
     * - See {@see Source::getTotals()}:
     *     - 'meta-invoice-amount': the total invoice amount excluding VAT.
     *     - 'meta-invoice-amountinc': the total invoice amount including VAT.
     *     - 'meta-invoice-vatamount': the total vat amount for the invoice.
     * - These will be added in the completor phase by
     *   {@see Completor::completeLineTotals()}:
     *     - 'meta-lines-amount'
     *     - 'meta-lines-amountinc'
     *     - 'meta-lines-vatamount'
     *
     * Extending classes should normally not have to override this method, but
     * should instead implement {@see getInvoiceNumber()},
     * {@see getInvoiceDate()}, {@see getPaymentStatus()},
     * {@see getPaymentDate()}, and, optionally, {@see getDescription()}.
     *
     * @return array
     *   A keyed array with the invoice data (without the invoice lines and the
     *   'emailaspdf' tag).
     */
    protected function getInvoice(): array
    {
        $invoice = [];

        $shopSettings = $this->config->getShopSettings();
        $invoiceSettings = $this->config->getInvoiceSettings();

        // Invoice type.
        $concept = $invoiceSettings['concept'];
        if ($concept === Config::Concept_Plugin) {
            $concept = Api::Concept_No;
        }
        $this->addDefaultEmpty($invoice, Tag::Concept, $concept);

        // Meta info: internal order/refund id
        $invoice[Meta::Id] = $this->invoiceSource->getId();

        // Invoice number and date.
        $sourceToUse = $shopSettings['invoiceNrSource'];
        if ($sourceToUse !== Config::InvoiceNrSource_Acumulus) {
            $invoice[Tag::Number] = $this->getInvoiceNumber($sourceToUse);
        }
        $dateToUse = $shopSettings['dateToUse'];
        if ($dateToUse !== Config::InvoiceDate_Transfer) {
            $invoice[Tag::IssueDate] = $this->getInvoiceDate($dateToUse);
        }

        // Bookkeeping (account number, cost center).
        $paymentMethod = $this->invoiceSource->getPaymentMethod();
        if (!empty($paymentMethod)) {
            if (!empty($invoiceSettings['paymentMethodCostCenter'][$paymentMethod])) {
                $invoice[Tag::CostCenter] = $invoiceSettings['paymentMethodCostCenter'][$paymentMethod];
            }
            if (!empty($invoiceSettings['paymentMethodAccountNumber'][$paymentMethod])) {
                $invoice[Tag::AccountNumber] = $invoiceSettings['paymentMethodAccountNumber'][$paymentMethod];
            }
        }
        $this->addDefault($invoice, Tag::CostCenter, $invoiceSettings['defaultCostCenter']);
        $this->addDefault($invoice, Tag::AccountNumber, $invoiceSettings['defaultAccountNumber']);

        // Payment info.
        $invoice[Tag::PaymentStatus] = $this->invoiceSource->getPaymentStatus();
        if ($invoice[Tag::PaymentStatus] === Api::PaymentStatus_Paid) {
            $this->addIfNotEmpty($invoice, Tag::PaymentDate, $this->invoiceSource->getPaymentDate());
        }

        // Additional descriptive info.
        $this->addTokenDefault($invoice, Tag::Description, $invoiceSettings['description']);
        $this->addTokenDefault($invoice, Tag::DescriptionText, $invoiceSettings['descriptionText']);
        // Change newlines to the literal \n, tabs are not supported.
        if (!empty($invoice[Tag::DescriptionText])) {
            $invoice[Tag::DescriptionText] = str_replace(["\r\n", "\r", "\n"], '\n', $invoice[Tag::DescriptionText]);
        }

        // Acumulus invoice template to use: this depends on the payment status
        // which in some shops is notoriously hard to get right. So, we postpone
        // this to after the invoice_created event when the payment status may
        // be assumed to be correct.
        $invoice[Tag::Template] = '';

        // Invoice notes.
        $this->addTokenDefault($invoice, Tag::InvoiceNotes, $invoiceSettings['invoiceNotes']);
        // Change newlines to the literal \n and tabs to \t.
        if (!empty($invoice[Tag::InvoiceNotes])) {
            $invoice[Tag::InvoiceNotes] = str_replace(["\r\n", "\r", "\n", "\t"], ['\n', '\n', '\n', '\t'], $invoice[Tag::InvoiceNotes]);
        }

        // Meta data.
        $invoice += $this->invoiceSource->getCurrency();
        $this->addIfNotEmpty($invoice, Meta::PaymentMethod, $paymentMethod);
        $invoice += $this->invoiceSource->getTotals();

        return $invoice;
    }

    /**
     * Returns the number to use as invoice number.
     *
     * @param int $invoiceNumberSource
     *   \Siel\Acumulus\PluginConfig::InvoiceNrSource_ShopInvoice or
     *   \Siel\Acumulus\PluginConfig::InvoiceNrSource_ShopOrder.
     *
     * @return int|null
     *   The number to use as invoice "number" on the Acumulus invoice. Note
     *   that Acumulus expects a number and does not accept string prefixes or
     *   such.
     */
    protected function getInvoiceNumber(int $invoiceNumberSource): ?int
    {
        $result = $invoiceNumberSource === Config::InvoiceNrSource_ShopInvoice ? $this->invoiceSource->getInvoiceReference() : null;
        if (empty($result)) {
            $result = $this->invoiceSource->getReference();
        }
        if (is_string($result)) {
            // Filter to an int. Just casting to an int would result in 0 with
            // any invoice number that has a prefix.
            $result = preg_replace('/\D/', '', $result);
        }
        return $result;
    }

    /**
     * Returns the date to use as invoice date.
     *
     * @param int $dateToUse
     *   \Siel\Acumulus\PluginConfig::InvoiceDate_InvoiceCreate or
     *   \Siel\Acumulus\PluginConfig::InvoiceDate_OrderCreate
     *
     * @return string
     *   Date to use as invoice date on the Acumulus invoice: yyyy-mm-dd.
     */
    protected function getInvoiceDate(int $dateToUse): string
    {
        $result = $this->invoiceSource->getInvoiceDate();
        /** @noinspection TypeUnsafeComparisonInspection */
        if ($dateToUse != Config::InvoiceDate_InvoiceCreate || empty($result)) {
            $result = $this->invoiceSource->getDate();
        }
        return $result;
    }

    /**
     * Returns the 'invoice''line' parts of the invoice add structure.
     *
     * Each invoice line is a keyed array.
     * The following keys are allowed or even required (*) by the API:
     * - 'itemnumber'
     * - 'product'
     * - 'nature'
     * - 'unitprice' (*)
     * - 'vatrate' (*)
     * - 'quantity' (*)
     * - 'costprice': optional, this triggers margin invoices.
     *
     * Metadata (not recognised by the API but used later on by the Creator or
     * {@see Completor}, or for support and debugging purposes), see
     * {@see \Siel\Acumulus\Meta}:
     * - Complementary to 'amount' and 'vatrate':
     *     - 'unitpriceinc'
     *     - 'vatamount'
     * - 'meta-line-type': type of line: 1 of the LineType_... constants.
     * - vat rate related:
     *     * 'meta-vatrate-source', 1 of the VatRateSource_... constants:
     *         - exact: should exactly equal an existing VAT rate.
     *         - exact-0: should exactly equal the 0 VAT rate.
     *         - calculated: based on dividing vat amount and unit price which
     *           both may have a limited precision and therefore probably will
     *           not exactly match an existing vat rate.
     *         - completor: to be filled in by the completor.
     *         - strategy: to be completed in by a tax divide strategy. This may
     *           lead to this line being split into multiple lines.
     *         - parent: copied from the parent.
     *     * 'meta-vatrate-min': required if 'meta-vatrate-source' is
     *       calculated. The minimum value for the vat rate, based on the
     *       precision of the 'vatamount' and 'unitprice'.
     *     * 'meta-vatrate-max': required if 'meta-vatrate-source' is
     *       calculated. The maximum value for the vat rate, based on the
     *       precision of the 'vatamount' and 'unitprice'.
     * - 'meta-strategy-split': true or false (absent = false)
     * - Totals per line:
     *     - 'meta-line-price'
     *     - 'meta-line-priceinc'
     *     - 'meta-line-vatamount'
     * - Parent - children line metadata:
     *     - 'meta-children'
     *     - 'meta-children-count'
     *     - 'meta-parent-index'
     *     - 'meta-children-merged'
     *     - 'meta-children-not-shown'
     *     - 'meta-parent'
     * -
     * These keys can be used to complete missing values or to assist in
     * correcting rounding errors in the values that are present.
     *
     * The base CompletorInvoiceLines expects:
     * - 'meta-vatrate-source' to be filled
     * - If 'meta-vatrate-source' = exact: no other keys expected.
     * - If 'meta-vatrate-source' = calculated: 'meta-vatrate-min' and
     *   'meta-vatrate-ma'x are expected to be filled. These values should come
     *   from calling the helper method getVatRangeTags() with the values used
     *   to calculate the vat rate and their precision.
     * - If 'meta-vatrate-source' = completor: vat rate should be null and
     *   unit price should be 0. The completor will typically fill vat rate with
     *   the highest or most appearing vat rate, looking at the exact and
     *   calculated (after correcting them for rounding errors) vat rates.
     * - If 'meta-vatrate-source' = strategy: vat rate should be null and either
     *   unit price or unit price inc should be filled wit a non-0 amount
     *   (typically a negative amount as this is mostly used for spreading
     *   discounts over tax rates). Moreover, on the invoice level,
     *   'meta-invoice-amount' and 'meta-invoice-vatamount' should be filled in.
     *   The completor will use a tax divide strategy to arrive at valid values
     *   for the missing fields.
     *
     * Extending classes should normally not have to override this method, but
     * should instead implement {@see getItemLines()}, {@see getShippingLine()},
     * {@see getPaymentFeeLine()}, {@see getGiftWrappingLine()},
     * {@see getDiscountLines()}, and {@see getManualLines()}.
     *
     * @return array[]
     *   A non keyed array with all invoice lines.
     */
    protected function getInvoiceLines(): array
    {
        $itemLines = $this->getItemLines();
        $itemLines = $this->addLineType($itemLines, static::LineType_OrderItem);

        $feeLines = $this->getFeeLines();

        $discountLines = $this->getDiscountLines();
        $discountLines = $this->addLineType($discountLines, static::LineType_Discount);

        $manualLines = $this->getManualLines();
        $manualLines = $this->addLineType($manualLines, static::LineType_Manual);

        return array_merge($itemLines, $feeLines, $discountLines, $manualLines);
    }

    /**
     * Returns the item/product lines of the order.
     *
     * Override this method or implement both getItemLinesOrder() and
     * getItemLinesCreditMote().
     *
     * @return array[]
     *   An array of item line arrays.
     */
    protected function getItemLines(): array
    {
        return $this->callSourceTypeSpecificMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Adds the product based tags to a line.
     *
     * The product based tags are:
     * - item number
     * - product name
     * - nature
     * - cost price
     *
     * @param array $line
     */
    protected function addProductInfo(array &$line): void
    {
        $invoiceSettings = $this->config->getInvoiceSettings();
        $this->addTokenDefault($line, Tag::ItemNumber, $invoiceSettings['itemNumber']);
        $this->addTokenDefault($line, Tag::Product, $invoiceSettings['productName']);
        $this->addNature($line);
        if (!empty($invoiceSettings['costPrice'])) {
            $value = $this->getTokenizedValue($invoiceSettings['costPrice']);
            if (!empty($value) && !Number::isZero($value)) {
                // If we have a cost price we add it, even if this is no margin
                // invoice.
                $line[Tag::CostPrice] = $value;
            }
        }
    }

    /**
     * Adds the nature tag to the line.
     *
     * The nature tag indicates the nature of the article for which the line is
     * being constructed. It can be Product or Service.
     *
     * The nature can come from the:
     * - Shop settings: the nature_shop setting.
     * - Invoice settings: The nature field reference.
     *
     * It will be left undefined when no value can be given to it based on these
     * settings.
     *
     * @param array $line
     */
    protected function addNature(array &$line): void
    {
        if (empty($line[Tag::Nature])) {
            $shopSettings = $this->config->getShopSettings();
            switch ($shopSettings['nature_shop']) {
                case Config::Nature_Products:
                    $line[Tag::Nature] = Api::Nature_Product;
                    break;
                case Config::Nature_Services:
                    $line[Tag::Nature] = Api::Nature_Service;
                    break;
                default:
                    $invoiceSettings = $this->config->getInvoiceSettings();
                    $this->addTokenDefault($line, Tag::Nature, $invoiceSettings['nature']);
                    break;
            }
        }
    }

    /**
     * Returns all the fee lines for the order.
     *
     * Override this method if it is easier to return all fee lines at once.
     * If you do so, you are responsible for adding the line Meta::LineType
     * metadata. Otherwise, override the methods getShippingLines() (or
     * getShippingLine()), getPaymentFeeLine() (if applicable), and
     * getGiftWrappingLine() (if available).
     *
     * @return array[]
     *   A, possibly empty, array of fee line arrays.
     */
    protected function getFeeLines(): array
    {
        $result = [];

        $shippingLines = $this->getShippingLines();
        if ($shippingLines) {
            $shippingLines = $this->addLineType($shippingLines, static::LineType_Shipping);
            $result = array_merge($result, $shippingLines);
        }

        $line = $this->getPaymentFeeLine();
        if ($line) {
            $line = $this->addLineType($line,static::LineType_PaymentFee);
            $result[] = $line;
        }

        $line = $this->getGiftWrappingLine();
        if ($line) {
            $line = $this->addLineType($line,static::LineType_GiftWrapping);
            $result[] = $line;
        }

        return $result;
    }

    /**
     * Returns the shipping costs lines.
     *
     * This default implementation assumes there will be at most one shipping
     * line and as such calls the getShippingLine() method.
     *
     * Override if the shop allows for multiple shipping lines.
     *
     * @return array[]
     *   A, possibly empty, array of shipping line arrays.
     */
    protected function getShippingLines(): array
    {
        $result = [];
        $line = $this->getShippingLine();
        if ($line) {
            $result[] = $line;
        }
        return $result;
    }

    /**
     * Returns the shipping costs line.
     *
     * To be able to produce a packing slip, a shipping line should normally be
     * added, even for free shipping.
     *
     * @return array
     *   A line array, empty if there is no shipping fee line.
     */
    abstract protected function getShippingLine(): array;

    /**
     * Returns the shipment method name.
     *
     * This method should be overridden by web shops to provide a more detailed
     * name of the shipping method used.
     *
     * This base implementation returns the translated "Shipping costs" string.
     *
     * @return string
     *   The name of the shipping method used for the current order.
     */
    protected function getShippingMethodName(): string
    {
        return $this->t('shipping_costs');
    }

    /**
     * Returns the payment fee line.
     *
     * This base implementation returns an empty array: no payment fee line.
     *
     * @return array
     *   A line array, empty if there is no payment fee line.
     */
    protected function getPaymentFeeLine(): array
    {
        return [];
    }

    /**
     * Returns the gift wrapping costs line.
     *
     * This base implementation return an empty array: no gift wrapping.
     *
     * @return array
     *   A line array, empty if there is no gift wrapping fee line.
     */
    protected function getGiftWrappingLine(): array
    {
        return [];
    }

    /**
     * Returns any applied discounts and partial payments (gift vouchers).
     *
     * Override this method or implement both getDiscountLinesOrder() and
     * getDiscountLinesCreditNote().
     *
     * Notes:
     * - In all cases you have to return an array of line arrays, even if your
     *   shop only allows 1 discount per order or stores all discount
     *   information as 1 total, and you can only return 1 line.
     * - if your shop already divided the discount amount over the eligible
     *   products, it is better to still return a separate discount line
     *   describing the discount code applied and the discount amount, but
     *   with a 0 amount tag. This allows e.g. to explain the lower than
     *   expected product prices on the item lines and/or the free shipping
     *   line.
     *
     * @return array[]
     *   A, possibly empty, array of discount line arrays.
     */
    protected function getDiscountLines(): array
    {
        return $this->callSourceTypeSpecificMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Returns any manual lines.
     *
     * Manual lines may appear on credit notes to overrule amounts as calculated
     * by the system. E.g. discounts applied on items should be taken into
     * account when refunding (while the system did not or does not know if the
     * discount also applied to that product), shipping costs may be returned
     * except for the handling costs, etc.
     *
     * @return array[]
     *   A, possibly empty, array of manual line arrays.
     */
    protected function getManualLines(): array
    {
        return [];
    }

    /**
     * Constructs and returns the 'emailaspdf' section (if enabled).
     *
     * @param string $fallbackEmailTo
     *   An email address to use as fallback when the emailTo setting is empty.
     * @param bool $forInvoice
     *   True if we want the emailAsPdf section for mailing an invoice, false if
     *   we want to email the packing slip.
     *
     * @return array
     *   The emailAsPdf section, possibly empty.
     */
    protected function getEmailAsPdf(string $fallbackEmailTo, bool $forInvoice = true): array
    {
        $emailAsPdf = [];
        $emailAsPdfSettings = $this->config->getEmailAsPdfSettings();
        if ($forInvoice) {
            $emailTo = !empty($emailAsPdfSettings['emailTo'])
                ? $this->getTokenizedValue($emailAsPdfSettings['emailTo'])
                : $fallbackEmailTo;
        } else {
            $emailTo = $this->getTokenizedValue($emailAsPdfSettings['packingSlipEmailTo']);
        }
        if (!empty($emailTo)) {
            $emailAsPdf[Tag::EmailTo] = $emailTo;
            if ($forInvoice) {
                $this->addTokenDefault($emailAsPdf, Tag::EmailBcc, $emailAsPdfSettings['emailBcc']);
                $this->addTokenDefault($emailAsPdf, Tag::EmailFrom, $emailAsPdfSettings['emailFrom']);
                $this->addTokenDefault($emailAsPdf, Tag::Subject, $emailAsPdfSettings['subject']);
                $emailAsPdf[Tag::ConfirmReading] = $emailAsPdfSettings['confirmReading'] ? Api::ConfirmReading_Yes : Api::ConfirmReading_No;
            } else {
                $this->addTokenDefault($emailAsPdf, Tag::EmailBcc, $emailAsPdfSettings['packingSlipEmailBcc']);
                $this->addTokenDefault($emailAsPdf, Tag::EmailFrom, $emailAsPdfSettings['packingSlipEmailFrom']);
                $this->addTokenDefault($emailAsPdf, Tag::Subject, $emailAsPdfSettings['packingSlipSubject']);
                $emailAsPdf[Tag::ConfirmReading] = $emailAsPdfSettings['packingSlipConfirmReading']
                    ? Api::ConfirmReading_Yes
                    : Api::ConfirmReading_No;
            }
        }
        return $emailAsPdf;
    }

    /**
     * Returns whether the margin scheme may be used.
     *
     * @return bool
     *
     * @todo: Remove margin scheme handling from (plugin specific) creators and
     *   move it to the completor phase. This will aid in simplifying the
     *   creators towards raw data collectors.
     */
    protected function allowMarginScheme(): bool
    {
        $shopSettings = $this->config->getShopSettings();
        return $shopSettings['marginProducts'] !== Config::MarginProducts_No;
    }

    /**
     * Helper method to add a non-empty possibly tokenized value to an array.
     * This method will not overwrite existing values.
     *
     * @param array $array
     * @param string $key
     * @param string $token
     *   String value that may contain token definitions.
     *
     * @return bool
     *   Whether the default was added.
     */
    protected function addTokenDefault(array &$array, string $key, string $token): bool
    {
        if (empty($array[$key]) && !empty($token)) {
            $value = $this->getTokenizedValue($token);
            if (!empty($value)) {
                $array[$key] = $value;
                return true;
            }
        }
        return false;
    }

    /**
     * Wrapper method around Token::expand().
     *
     * @param string $pattern
     *
     * @return string
     *   The pattern with tokens expanded with their actual value.
     */
    protected function getTokenizedValue(string $pattern): string
    {
        return $this->token->expand($pattern, $this->propertySources);
    }

    /**
     * Helper method to add a non-empty value to an array.
     *
     * @param array $array
     * @param string $key
     * @param mixed $value
     *
     * @return bool
     *   True if the value was not empty and thus has been added, false
     *   otherwise.
     */
    protected function addIfNotEmpty(array &$array, string $key, $value): bool
    {
        if (!empty($value)) {
            $array[$key] = $value;
            return true;
        }
        return false;
    }

    /**
     * Helper method to add a default non-empty value to an array.
     * This method will not overwrite existing values.
     *
     * @param array $array
     * @param string $key
     * @param mixed $value
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
     * Helper method to add a possibly empty default value to an array.
     * This method will not overwrite existing values.
     *
     * @param array $array
     * @param string $key
     * @param mixed $value
     *
     * @return bool
     *   Whether the default was added.
     */
    protected function addDefaultEmpty(array &$array, string $key, $value): bool
    {
        if (!isset($array[$key])) {
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
     *
     * @param array $array
     * @param string $warning
     */
    protected function addWarning(array &$array, string $warning): void
    {
        if (!isset($array[Meta::Warning])) {
            $array[Meta::Warning] = $warning;
        } else {
            if (!is_array($array[Meta::Warning])) {
                $array[Meta::Warning] = (array) $array[Meta::Warning];
            }
            $array[Meta::Warning][] = $warning;
        }
    }

    /**
     * Adds a meta-line-type tag to the line(s) and its children, if any.
     *
     * @param array|array[] $lines
     *   This may be a single line not placed in an array.
     * @param string $lineType
     *   The line type to add to the line.
     *
     * @return array|array[]
     *   The line(s) with the line type meta tag added.
     */
    protected function addLineType(array $lines, string $lineType): array
    {
        if (!empty($lines)) {
            // reset(), so key() does not return null if the array is not empty.
            reset($lines);
            if (is_int(key($lines))) {
                // Numeric index: array of lines.
                foreach ($lines as &$line) {
                    $line = $this->addLineType($line, $lineType);
                }
            } else {
                // String key: single line.
                $this->addDefault($lines, Meta::LineType, $lineType);
                if (isset($lines[Meta::ChildrenLines])) {
                    $lines[Meta::ChildrenLines] = $this->addLineType($lines[Meta::ChildrenLines], $lineType);
                }
            }
        }
        return $lines;
    }

    /**
     * Returns the range in which the vat rate will lie.
     * If a web shop does not store the vat rates used in the order, we must
     * calculate them using a (product) price and the vat on it. But as web
     * shops often store these numbers rounded to cents, the vat rate
     * calculation becomes imprecise. Therefore, we compute the range in which
     * it will lie and will let the Completor do a comparison with the actual
     * vat rates that an order can have (one of the Dutch or, for electronic
     * services, other EU country VAT rates).
     * - If $denominator = 0 (free product), the vat rate will be set to null
     *   and the Completor will try to get this line listed under the correct
     *   vat rate.
     * - If $numerator = 0 the vat rate will be set to 0 and be treated as if it
     *   is an exact vat rate, not a vat range.
     *
     * @param float $numerator
          *   The amount of VAT as received from the web shop.
     * @param float $denominator
     *   The price of a product excluding VAT as received from the web shop.
     * @param float $numeratorPrecision
     *   The precision used when rounding the number. This means that the
     *   original numerator will not differ more than half of this.
     * @param float $denominatorPrecision
     *   The precision used when rounding the number. This means that the
     *   original denominator will not differ more than half of this.
     *
     * @return array
     *   Array with keys (not all keys will always be available):
     *   - 'vatrate'
     *   - 'vatamount'
     *   - 'meta-vatrate-min'
     *   - 'meta-vatrate-max'
     *   - 'meta-vatamount-precision'
     *   - 'meta-vatrate-source'
     * @todo: Can we move this from the (plugin specific) creators to the
     *   completor phase? This would aid in simplifying the creators towards raw
     *   data collectors.
     */
    public static function getVatRangeTags(
        float $numerator,
        float $denominator,
        float $numeratorPrecision = 0.01,
        float $denominatorPrecision = 0.01
    ): array {
        if (Number::isZero($denominator, 0.0001)) {
            $result = [
                Tag::VatRate => null,
                Meta::VatAmount => $numerator,
                Meta::VatRateSource => static::VatRateSource_Completor,
            ];
        } elseif (Number::isZero($numerator, 0.0001)) {
            $result = [
                Tag::VatRate => 0,
                Meta::VatAmount => $numerator,
                Meta::VatRateSource => static::VatRateSource_Exact0,
            ];
        } else {
            $range = Number::getDivisionRange($numerator, $denominator, $numeratorPrecision, $denominatorPrecision);
            $result = [
                Tag::VatRate => 100.0 * $range['calculated'],
                Meta::VatRateMin => 100.0 * $range['min'],
                Meta::VatRateMax => 100.0 * $range['max'],
                Meta::VatAmount => $numerator,
                Meta::PrecisionUnitPrice => $denominatorPrecision,
                Meta::PrecisionVatAmount => $numeratorPrecision,
                Meta::VatRateSource => static::VatRateSource_Calculated,
            ];
        }
        return $result;
    }

    /**
     * Calls a method constructed of the method name and the source type.
     * If the implementation/override of a method depends on the type of invoice
     * source it might be better to implement 1 method per source type. This
     * method calls such a method assuming it is named {method}{source-type}.
     * Example: if getLineItem($line) would be very different for an order
     * versus a credit note: do not override the base method but implement 2 new
     * methods getLineItemOrder($line) and getLineItemCreditNote($line).
     *
     * @param string $method
     *   The name of the base method for which to call the Source type specific
     *   variant.
     * @param array $args
     *   The arguments to pass to the method to call.
     *
     * @return mixed
     *   The return value of that method call, or null if the method does not
     *   exist.
     */
    protected function callSourceTypeSpecificMethod(string $method, array $args = [])
    {
        $method .= $this->invoiceSource->getType();
        if (method_exists($this, $method)) {
            return $this->$method(... $args);
        }
        return null;
    }
}
