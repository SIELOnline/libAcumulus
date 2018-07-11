<?php
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
use Siel\Acumulus\PluginConfig;
use Siel\Acumulus\Tag;

/**
 * Creates a raw version of the Acumulus invoice based on a {@see Source}.
 *
 * Introduction
 * ------------
 * The Acumulus invoice structure is specified on:
 * {@link https://www.siel.nl/acumulus/API/Invoicing/Add_Invoice/ }
 *
 * In addition to the scheme as defined over there, additional meta data tags
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
 * - Implements the basic break down into smaller actions that webshops should
 *   subsequently implement.
 * - Provides helper methods for some recurring functionality.
 * - Documents the expectations of each method to be implemented by a webshop's
 *   Creator class.
 * - Documents the meta tags expected or suggested.
 *
 * A raw invoice:
 * - Contains all customer tags (as far as they should or can be set), even if
 *   the use configured to not send customer data to Acumulus.
 * - Contains most invoice tags (as far as they should or can be set), except
 *   vattype and concept.
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
 * meta-vatrate-source to Creator::VatRateSource_Parent. Copy the quantity from
 * the parent to the child. Price info is probably on the parent line only,
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
 *
 */
abstract class Creator
{
    const VatRateSource_Exact = 'exact';
    const VatRateSource_Exact0 = 'exact-0';
    const VatRateSource_Calculated = 'calculated';
    const VatRateSource_Completor = 'completor';
    const VatRateSource_Strategy = 'strategy';
    const VatRateSource_Parent = 'parent';

    const LineType_Shipping = 'shipping';
    const LineType_PaymentFee = 'payment';
    const LineType_GiftWrapping = 'gift';
    const LineType_Manual = 'manual';
    const LineType_Order = 'product';
    const LineType_Discount = 'discount';
    const LineType_Voucher = 'voucher';
    const LineType_Other = 'other';
    const LineType_Corrector = 'missing-amount-corrector';

    /** @var \Siel\Acumulus\Config\Config */
    protected $config;

    /** @var \Siel\Acumulus\Config\ShopCapabilities */
    protected $shopCapabilities;

    /** @var \Siel\Acumulus\Helpers\Token */
    protected $token;

    /** @var \Siel\Acumulus\Helpers\Translator */
    protected $translator;

    /** @var \Siel\Acumulus\Helpers\Log */
    protected $log;

    /** @var \Siel\Acumulus\Helpers\Countries */
    protected $countries;

    /** @var \Siel\Acumulus\Helpers\Container*/
    protected $container;

    /** @var array Resulting Acumulus invoice */
    protected $invoice = array();

    /** @var Source */
    protected $invoiceSource;

    /**
     * The list of sources to search for properties.
     *
     * @var array
     */
    protected $propertySources;

    /**
     * Constructor.
     *
     * @param \Siel\Acumulus\Helpers\Token $token
     * @param \Siel\Acumulus\Helpers\Countries $countries
     * @param \Siel\Acumulus\Config\ShopCapabilities $shopCapabilities
     * @param \Siel\Acumulus\Helpers\Container $container
     * @param \Siel\Acumulus\Config\Config $config
     * @param \Siel\Acumulus\Helpers\Translator $translator
     * @param \Siel\Acumulus\Helpers\Log $log
     */
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
    protected function t($key)
    {
        return $this->translator->get($key);
    }

    /**
     * Sets the source to create the invoice for.
     *
     * @param Source $invoiceSource
     */
    protected function setInvoiceSource($invoiceSource)
    {
        $this->invoiceSource = $invoiceSource;
        if (!in_array($invoiceSource->getType(), array(Source::Order, Source::CreditNote))) {
            $this->log->error('Creator::setSource(): unknown source type %s', $this->invoiceSource->getType());
        };
    }

    /**
     * Sets the list of sources to search for a property when expanding tokens.
     */
    protected function setPropertySources()
    {
        $this->propertySources = array(
            'invoiceSource' => $this->invoiceSource,
            'source' => $this->invoiceSource->getSource(),
        );
        if (in_array(Source::CreditNote, $this->shopCapabilities->getSupportedInvoiceSourceTypes())) {
            if ($this->invoiceSource->getType() === Source::CreditNote) {
                $this->propertySources['refund'] = $this->invoiceSource->getSource();
            }
            $this->propertySources['order'] = $this->invoiceSource->getOrder();
        }
    }

    /**
     * Adds an object as property source.
     *
     * The object is added to the start of the array. thus upon token expansion,
     * it will be searched before other (already added) property sources.
     *
     * @param string $name
     *   The name to use for the source
     * @param object|array $property
     *   The source object to add.
     */
    public function addPropertySource($name, $property)
    {
        $this->propertySources = array($name => $property) + $this->propertySources;
    }

    /**
     * Removes an object as property source.
     *
     * @param string $name
     *   The name of the source to remove.
     */
    public function removePropertySource($name)
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
    public function create($source)
    {
        $this->setInvoiceSource($source);
        $this->setPropertySources();
        $this->invoice = array();
        $this->invoice[Tag::Customer] = $this->getCustomer();
        $this->invoice[Tag::Customer][Tag::Invoice] = $this->getInvoice();
        $this->invoice[Tag::Customer][Tag::Invoice][Tag::Line] = $this->getInvoiceLines();
        $emailAsPdf = $this->getEmailAsPdf(!empty($this->invoice[Tag::Customer][Tag::Email]) ? $this->invoice[Tag::Customer][Tag::Email] : '');
        if (!empty($emailAsPdf)) {
            $this->invoice[Tag::Customer][Tag::Invoice][Tag::EmailAsPdf] = $emailAsPdf;
        }
        return $this->invoice;
    }

    /**
     * Returns the 'customer' part of the invoice add structure.
     *
     * The following keys are allowed/expected by the API:
     * - type
     * - contactid: will not be set. Acumulus id for this customer, in the
     *   absence of this value, the API uses the email address as identifying
     *   value.
     * - contactyourid: webshop customer id.
     * - contactstatus
     * - companyname1
     * - companyname2
     * - vatnumber
     * - fullname
     * - salutation
     * - address1
     * - address2
     * - postalcode
     * - city
     * - countrycode
     * - country
     * - telephone
     * - fax
     * - email: used to identify clients.
     * - overwriteifexists
     * - bankaccountnumber: will not be set: no webshop software provides this.
     * - mark
     * - disableduplicates: not (yet) supported.
     *
     * At the customer level no meta tags are defined.
     *
     * Extending classes should normally not have to override this method as all
     * values are fetched via configurable settings that may contain tokens
     * (veldverwijzingen (dutch)) that refer to properties of objects in the
     * webshop (property sources). The exception is the (ISO) country code which
     * may not be easy to fetch via a property source as this might include a
     * database lookup to a "Countries" table. This is fetched via
     * Source::getCountryCode().
     *
     * @return array
     *   A keyed array with the customer data.
     */
    protected function getCustomer()
    {
        $customer = array();
        $customerSettings = $this->config->getCustomerSettings();
        $this->addDefault($customer, Tag::Type, $customerSettings['defaultCustomerType']);
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
     * - concept: in case of errors or warnings this may be overridden by the
     *   Completor to make it a concept.
     * - concepttype : not (yet) used.
     * - number
     * - vattype: will be filled by the Completor.
     * - issuedate
     * - costcenter
     * - accountnumber
     * - paymentstatus
     * - paymentdate
     * - description
     * - descriptiontext
     * - template
     * - invoicenotes
     *
     * Meta data (not recognised by the API but used later on by the
     * Creator or Completor, or for support and debugging purposes):
     * - See {@see Source::getCurrency()}:
     *     - currency
     *     - currency-rate
     *     - currency-do-convert
     * - See {@see Source::getPaymentMethod()}:
     *     - meta-payment-method: an id of the payment method used.
     * - See {@see Source::getTotals()}:
     *     - meta-invoice-amount: the total invoice amount excluding VAT.
     *     - meta-invoice-amountinc: the total invoice amount including VAT.
     *     - meta-invoice-vatamount: the total vat amount for the invoice.
     * - These will be added in the completor phase by
     *   {@see Completor::completeLineTotals()}:
     *     - meta-lines-amount
     *     - meta-lines-amountinc
     *     - meta-lines-vatamount
     *
     * Extending classes should normally not have to override this method, but
     * should instead implement getInvoiceNumber(), getInvoiceDate(),
     * getPaymentStatus(), getPaymentDate(), and, optionally, getDescription().
     *
     * @return array
     *   A keyed array with the invoice data (without the invoice lines and the
     *   emailaspdf tag).
     */
    protected function getInvoice()
    {
        $invoice = array();

        $shopSettings = $this->config->getShopSettings();
        $invoiceSettings = $this->config->getInvoiceSettings();

        // Invoice type.
        $concept = $invoiceSettings['concept'];
        if ($concept == PluginConfig::Concept_Plugin) {
            $concept = Api::Concept_No;
        }
        $this->addDefaultEmpty($invoice, Tag::Concept, $concept);

        // Invoice number and date.
        $sourceToUse = $shopSettings['invoiceNrSource'];
        if ($sourceToUse != PluginConfig::InvoiceNrSource_Acumulus) {
            $invoice[Tag::Number] = $this->getInvoiceNumber($sourceToUse);
        }
        $dateToUse = $shopSettings['dateToUse'];
        if ($dateToUse != PluginConfig::InvoiceDate_Transfer) {
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
            $invoice[Tag::DescriptionText] = str_replace(array("\r\n", "\r", "\n"), '\n', $invoice[Tag::DescriptionText]);
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
            $invoice[Tag::InvoiceNotes] = str_replace(array("\r\n", "\r", "\n", "\t"), array('\n', '\n', '\n', '\t'), $invoice[Tag::InvoiceNotes]);
        }

        // Meta data.
        $invoice += $this->invoiceSource->getCurrency();
        $this->addIfNotEmpty($invoice, Meta::paymentMethod, $paymentMethod);
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
     * @return int
     *   The number to use as invoice "number" on the Acumulus invoice. Note
     *   that Acumulus expects a number and does not accept string prefixes or
     *   such.
     */
    protected function getInvoiceNumber($invoiceNumberSource)
    {
        $result = $invoiceNumberSource === PluginConfig::InvoiceNrSource_ShopInvoice ? $this->invoiceSource->getInvoiceReference() : null;
        if (empty($result)) {
            $result = $this->invoiceSource->getReference();
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
    protected function getInvoiceDate($dateToUse)
    {
        $result = $this->invoiceSource->getInvoiceDate();
        if ($dateToUse != PluginConfig::InvoiceDate_OrderCreate || empty($result)) {
            $result = $this->invoiceSource->getDate();
        }
        return $result;
    }

    /**
     * Returns the 'invoice''line' parts of the invoice add structure.
     *
     * Each invoice line is a keyed array.
     * The following keys are allowed or expected (*) by the API:
     * - itemnumber
     * - product
     * - nature
     * * unitprice
     * * vatrate
     * * quantity
     * - costprice: optional, this triggers margin invoices.
     *
     * Meta data (not recognised by the API but used later on by the Creator or
     * Completor, or for support and debugging purposes), see
     * {@see \Siel\Acumulus\Meta}:
     * - Complementary  to amount and vatrate:
     *     - unitpriceinc
     *     - vatamount
     * * meta-line-type: type of line: 1 of the LineType_... constants.
     * - vat rate related:
     *     * meta-vatrate-source, 1 of the VatRateSource_... constants:
     *         - exact: should exactly equal an existing VAT rate.
     *         - exact-0: should exactly equal the 0 VAT rate.
     *         - calculated: based on dividing vatamount and unitprice which
     *           both may have a limited precision and therefore probably will
     *           not exactly match an existing vat rate.
     *         - completor: to be filled in by the completor.
     *         - strategy: to be completed in by a tax divide strategy. This may
     *           lead to this line being split into multiple lines.
     *         - parent: copied from the parent.
     *     * meta-vatrate-min: required if meta-vatrate-source is calculated.
     *       The minimum value for the vat rate, based on the precision of the
     *       vatamount and unitprice.
     *     * meta-vatrate-max: required if meta-vatrate-source is calculated.
     *       The maximum value for the vat rate, based on the precision of the
     *       vatamount and unitprice.
     * - meta-strategy-split: true or false (absent = false)
     * - Totals per line:
     *     - meta-line-price
     *     - meta-line-priceinc
     *     - meta-line-vatamount
     * - Parent - children line meta data:
     *     - meta-children
     *     - meta-children-count
     *     - meta-parent-index
     *     - meta-children-merged
     *     - meta-children-not-shown
     *     - meta-parent
     * -
     * These keys can be used to complete missing values or to assist in
     * correcting rounding errors in the values that are present.
     *
     * The base CompletorInvoiceLines expects:
     * - meta-vatrate-source to be filled
     * - If meta-vatrate-source = exact: no other keys expected
     * - If meta-vatrate-source = calculated: meta-vatrate-min and
     *   meta-vatrate-max are expected to be filled. These values should come
     *   from calling the helper method getVatRangeTags() with the values used
     *   to calculate the vat rate and their precision.
     * - If meta-vatrate-source = completor: vatrate should be null and
     *   unitprice should be 0. The completor will typically fill vatrate with
     *   the highest or most appearing vat rate, looking at the exact and
     *   calculated (after correcting them for rounding errors) vat rates.
     * - If meta-vatrate-source = strategy: vat rate should be null and either
     *   unitprice or unitpriceinc should be filled wit a non-0 amount
     *   (typically a negative amount as this is mostly used for spreading
     *   discounts over tax rates). Moreover, on the invoice level
     *   meta-invoice-amount and meta-invoice-vatamount should be filled in.
     *   The completor will use a tax divide strategy to arrive at valid values
     *   for the missing fields.
     *
     * Extending classes should normally not have to override this method, but
     * should instead implement getItemLines(), getShippingLine(),
     * getPaymentFeeLine(), getGiftWrappingLine(), getDiscountLines(), and
     * getManualLines().
     *
     * @return array[]
     *   A non keyed array with all invoice lines.
     */
    protected function getInvoiceLines()
    {
        $itemLines = $this->getItemLines();
        $itemLines = $this->addLineType($itemLines, static::LineType_Order);

        $feeLines = $this->getFeeLines();

        $discountLines = $this->getDiscountLines();
        $discountLines = $this->addLineType($discountLines, static::LineType_Discount);

        $manualLines = $this->getManualLines();
        $manualLines = $this->addLineType($manualLines, static::LineType_Manual);

        $result = array_merge($itemLines, $feeLines, $discountLines, $manualLines);
        return $result;
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
    protected function getItemLines()
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
     *
     * @param array $line
     */
    protected function addProductInfo(array &$line)
    {
        $invoiceSettings = $this->config->getInvoiceSettings();
        $this->addTokenDefault($line, Tag::ItemNumber, $invoiceSettings['itemNumber']);
        $this->addTokenDefault($line, Tag::Product, $invoiceSettings['productName']);
        $this->addNature($line);
        if (!empty($invoiceSettings['costPrice'])) {
            $value = $this->getTokenizedValue($invoiceSettings['costPrice']);
            if (!Number::isZero($value)) {
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
     * @param array $line
     */
    protected function addNature(array &$line)
    {
        if (empty($line[Tag::Nature])) {
            $shopSettings = $this->config->getShopSettings();
            switch ($shopSettings['nature_shop']) {
                case PluginConfig::Nature_Products:
                    $line[Tag::Nature] = Api::Nature_Product;
                    break;
                case PluginConfig::Nature_Services:
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
     * If you do so, you are responsible for adding the line Meta::LineType meta
     * data. Otherwise, override the methods getShippingLines() (or
     * getShippingLine()), getPaymentFeeLine() (if applicable), and
     * getGiftWrappingLine() (if available).
     *
     * @return array[]
     *   A, possibly empty, array of fee line arrays.
     */
    protected function getFeeLines()
    {
        $result = array();

        $shippingLines = $this->getShippingLines();
        if ($shippingLines) {
            $shippingLines = $this->addLineType($shippingLines, static::LineType_Shipping, Api::Nature_Service);
            $result = array_merge($result, $shippingLines);
        }

        $line = $this->getPaymentFeeLine();
        if ($line) {
            $line = $this->addLineType($line,static::LineType_PaymentFee, Api::Nature_Service);
            $result[] = $line;
        }

        $line = $this->getGiftWrappingLine();
        if ($line) {
            $line = $this->addLineType($line,static::LineType_GiftWrapping, Api::Nature_Service);
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
    protected function getShippingLines()
    {
        $result = array();
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
    abstract protected function getShippingLine();

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
    protected function getShippingMethodName()
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
    protected function getPaymentFeeLine()
    {
        return array();
    }

    /**
     * Returns the gift wrapping costs line.
     *
     * This base implementation return an empty array: no gift wrapping.
     *
     * @return array
     *   A line array, empty if there is no gift wrapping fee line.
     */
    protected function getGiftWrappingLine()
    {
        return array();
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
     *   information as 1 total and you can only return 1 line.
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
    protected function getDiscountLines()
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
    protected function getManualLines()
    {
        return array();
    }

    /**
     * Constructs and returns the emailaspdf section (if enabled).
     *
     * @param string $fallbackEmailTo
     *   An email address to use as fallback when the emailTo setting is empty.
     *
     * @return array
     *   The emailAsPdf section, possibly empty.
     */
    protected function getEmailAsPdf($fallbackEmailTo)
    {
        $emailAsPdf = array();
        $emailAsPdfSettings = $this->config->getEmailAsPdfSettings();
        if ($emailAsPdfSettings['emailAsPdf']) {
            $emailTo = !empty($emailAsPdfSettings['emailTo']) ? $this->getTokenizedValue($emailAsPdfSettings['emailTo']) : $fallbackEmailTo;
            if (!empty($emailTo)) {
                $emailAsPdf['emailto'] = $emailTo;
                $this->addTokenDefault($emailAsPdf, 'emailbcc', $emailAsPdfSettings['emailBcc']);
                $this->addTokenDefault($emailAsPdf, 'emailfrom', $emailAsPdfSettings['emailFrom']);
                $this->addTokenDefault($emailAsPdf, 'subject', $emailAsPdfSettings['subject']);
                $emailAsPdf['confirmreading'] = $emailAsPdfSettings['confirmReading'] ? Api::ConfirmReading_Yes : Api::ConfirmReading_No;
            }
        }
        return $emailAsPdf;
    }

    /**
     * Returns whether the margin scheme may be used.
     *
     * @return bool
     */
    protected function allowMarginScheme()
    {
        $shopSettings = $this->config->getShopSettings();
        return $shopSettings['marginProducts'] !== PluginConfig::MarginProducts_No;
    }

    /**
     * Helper method to add a non-empty possibly tokenized value to an array.
     *
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
    protected function addTokenDefault(array &$array, $key, $token)
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
    protected function getTokenizedValue($pattern)
    {
        return $this->token->expand($pattern, $this->propertySources);
    }

    /**
     * Helper method to add a value from an array or object only if it is set
     * and not empty.
     *
     * @param array $targetArray
     * @param string $targetKey
     * @param array|object $source
     * @param string $sourceKey
     *
     * @return bool
     *   Whether the array value or object property is set and not empty and
     *   thus has been added.
     */
    protected function addIfSetAndNotEmpty(array &$targetArray, $targetKey, $source, $sourceKey)
    {
        if (is_array($source)) {
            if (!empty($source[$sourceKey])) {
                $targetArray[$targetKey] = $source[$sourceKey];
                return true;
            }
        } else {
            if (!empty($source->$sourceKey)) {
                $targetArray[$targetKey] = $source->$sourceKey;
                return true;
            }
        }
        return false;
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
    protected function addIfNotEmpty(array &$array, $key, $value)
    {
        if (!empty($value)) {
            $array[$key] = $value;
            return true;
        }
        return false;
    }

    /**
     * Helper method to add a value to an array even if it is empty.
     *
     * @param array $array
     * @param string $key
     * @param mixed $value
     * @param mixed $default
     *
     * @return bool
     *   True if the value was not empty and thus has been added, false if the
     *   default has been added.
     */
    protected function addEmpty(array &$array, $key, $value, $default = '')
    {
        if (!empty($value)) {
            $array[$key] = $value;
            return true;
        } else {
            $array[$key] = $default;
            return false;
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

    /**
     * Helper method to add a possibly empty default value to an array.
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
    protected function addDefaultEmpty(array &$array, $key, $value)
    {
        if (!isset($array[$key])) {
            $array[$key] = $value;
            return true;
        }
        return false;
    }

    /**
     * Adds a meta-line-type tag to the line(s) and its children, if any.
     *
     * @param array|array[] $lines
     *   May be a single line not placed in an array.
     * @param string $lineType
     *   The line type to add to the line.
     * @param string $nature
     *   Optional: the nature to add to the line.
     *
     * @return array|array[]
     *   The line(s) with the line type meta tag added.
     */
    protected function addLineType(array $lines, $lineType, $nature = '')
    {
        if (!empty($lines)) {
            // reset(), so key() does not return null if the array is not empty.
            reset($lines);
            if (is_numeric(key($lines))) {
                // Numeric index: array of lines.
                foreach ($lines as &$line) {
                    $this->addDefault($line, Meta::LineType, $lineType);
                    $this->addDefault($line, Tag::Nature, $nature);
                    if (isset($line[Meta::ChildrenLines])) {
                        $line[Meta::ChildrenLines] = $this->addLineType($line[Meta::ChildrenLines], $lineType, $nature);
                    }
                }
            } else {
                // String key: single line.
                $this->addDefault($lines, Meta::LineType, $lineType);
                $this->addDefault($lines, Tag::Nature, $nature);
                if (isset($lines[Meta::ChildrenLines])) {
                    $lines[Meta::ChildrenLines] = $this->addLineType($lines[Meta::ChildrenLines], $lineType, $nature);
                }
            }
        }
        return $lines;
    }

    /**
     * Returns the range in which the vat rate will lie.
     *
     * If a webshop does not store the vat rates used in the order, we must
     * calculate them using a (product) price and the vat on it. But as web
     * shops often store these numbers rounded to cents, the vat rate
     * calculation becomes imprecise. Therefore we compute the range in which
     * it will lie and will let the Completor do a comparison with the actual
     * vat rates that an order can have (one of the Dutch or, for electronic
     * services, other EU country VAT rates).
     *
     * - If $denominator = 0 (free product), the vatrate will be set to null
     *   and the Completor will try to get this line listed under the correct
     *   vat rate.
     * - If $numerator = 0 the vatrate will be set to 0 and be treated as if it
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
     *   Array with keys (noy all keys will always be available):
     *   - vatrate
     *   - vatamount
     *   - meta-vatrate-min
     *   - meta-vatrate-max
     *   - meta-vatamount-precision
     *   - meta-vatrate-source
     */
    public static function getVatRangeTags($numerator, $denominator, $numeratorPrecision = 0.01, $denominatorPrecision = 0.01)
    {
        if (Number::isZero($denominator, 0.0001)) {
            $result = array(
                Tag::VatRate => null,
                Meta::VatAmount => $numerator,
                Meta::VatRateSource => static::VatRateSource_Completor,
            );
        } elseif (Number::isZero($numerator, 0.0001)) {
            $result = array(
                Tag::VatRate => 0,
                Meta::VatAmount => $numerator,
                Meta::VatRateSource => static::VatRateSource_Exact0,
            );
        } else {
            $range = Number::getDivisionRange($numerator, $denominator, $numeratorPrecision, $denominatorPrecision);
            $result = array(
                Tag::VatRate => 100.0 * $range['calculated'],
                Meta::VatRateMin => 100.0 * $range['min'],
                Meta::VatRateMax => 100.0 * $range['max'],
                Meta::VatAmount => $numerator,
                Meta::PrecisionVatAmount => $numeratorPrecision,
                Meta::VatRateSource => static::VatRateSource_Calculated,
            );
        }
        return $result;
    }

    /**
     * Calls a method constructed of the method name and the source type.
     *
     * If the implementation/override of a method depends on the type of invoice
     * source it might be better to implement 1 method per source type. This
     * method calls such a method assuming it is named {method}{source-type}.
     * Example: if getLineItem($line) would be very different for an order
     * versus a credit note: do not override the base method but implement 2 new
     * methods getLineItemOrder($line) and getLineItemCreditNote($line).
     *
     * @param string $method
     * @param array $args
     *
     * @return mixed
     */
    protected function callSourceTypeSpecificMethod($method, $args = array())
    {
        $method .= $this->invoiceSource->getType();
        return call_user_func_array(array($this, $method), $args);
    }
}
