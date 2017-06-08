<?php
namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Config\ConfigInterface;
use Siel\Acumulus\Helpers\Countries;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Helpers\Token;
use Siel\Acumulus\Helpers\TranslatorInterface;
use Siel\Acumulus\Config\Config;

/**
 * Creator creates a raw invoice similar to the Acumulus invoice structure.
 *
 * Allows to create arrays in the Acumulus invoice structure from a web shop
 * order or credit note. This array can than be completed and sent to Acumulus
 * using the Invoice:Add Acumulus API call.
 *
 * See https://apidoc.sielsystems.nl/content/invoice-add for the structure. In
 * addition to the scheme as defined over there, additional keys or values are
 * accepted:
 * - all keys starting with meta- are used for debugging purposes.
 * - other keys are used to complete and correct the invoice in the completor
 *   stage.
 * - (PHP) null values indicates the absence of a value that should be there but
 *   could not be (easily) retrieved from the invoice source. These should be
 *   replaced with actual values in the completor stage as well.
 *
 * This base class:
 * - Implements the basic break down into smaller actions that web shops should
 *   subsequently implement.
 * - Provides helper methods for some recurring functionality.
 */
abstract class Creator
{
    const VatRateSource_Exact = 'exact';
    const VatRateSource_Exact0 = 'exact-0';
    const VatRateSource_Calculated = 'calculated';
    const VatRateSource_Completor = 'completor';
    const VatRateSource_Strategy = 'strategy';

    const LineType_Shipping = 'shipping';
    const LineType_PaymentFee = 'payment';
    const LineType_GiftWrapping = 'gift';
    const LineType_Manual = 'manual';
    const LineType_Order = 'product';
    const LineType_Discount = 'discount';
    const LineType_Voucher = 'voucher';
    const LineType_Other = 'other';
    const LineType_Corrector = 'missing-amount-corrector';

    const Line_Children = 'children';

    /** @var \Siel\Acumulus\Config\Config */
    protected $config;

    /** @var \Siel\Acumulus\Helpers\Token */
    protected $token;

    /** @var \Siel\Acumulus\Helpers\TranslatorInterface */
    protected $translator;

    /** @var \Siel\Acumulus\Helpers\Log */
    protected $log;

    /** @var \Siel\Acumulus\Helpers\Countries */
    protected $countries;

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
     * @param \Siel\Acumulus\Helpers\ContainerInterface|\Siel\Acumulus\Config\Config $config
     * @param \Siel\Acumulus\Helpers\Token $token
     * @param \Siel\Acumulus\Helpers\TranslatorInterface $translator
     * @param \Siel\Acumulus\Helpers\Log $log
     */
    public function __construct(Config $config, Token $token, TranslatorInterface $translator, Log $log)
    {
        $this->log = $log;
        $this->config = $config;
        $this->token = $token;
        $this->translator = $translator;
        $invoiceHelperTranslations = new Translations();
        $this->translator->add($invoiceHelperTranslations);

        $this->countries = new Countries();
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
    protected function addPropertySource($name, $property)
    {
        $this->propertySources = array($name => $property) + $this->propertySources;
    }

    /**
     * Removes an object as property source.
     *
     * @param string $name
     *   The name of the source to remove.
     */
    protected function removePropertySource($name)
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
        $this->invoice['customer'] = $this->getCustomer();
        $this->invoice['customer']['invoice'] = $this->getInvoice();
        $this->invoice['customer']['invoice']['line'] = $this->getInvoiceLines();
        $emailAsPdf = $this->getEmailAsPdf(!empty($this->invoice['customer']['email']) ? $this->invoice['customer']['email'] : '');
        if (!empty($emailAsPdf)) {
            $this->invoice['customer']['invoice']['emailaspdf'] = $emailAsPdf;
        }
        return $this->invoice;
    }

    /**
     * Returns the 'customer' part of the invoice add structure.
     *
     * The following keys are allowed/expected by the API:
     * - type: not needed, will be filled by the Completor
     * - contactid: not required: Acumulus id for this customer, in the absence
     *     of this value, the API uses the email address as identifying value.
     * - contactyourid: shop customer id
     * - contactstatus
     * - companyname1
     * - companyname2
     * - fullname
     * - salutation
     * - address1
     * - address2
     * - postalcode
     * - city
     * - country
     * - countrycode
     * - vatnumber
     * - telephone
     * - fax
     * - email: used to identify clients.
     * - overwriteifexists: not needed, will be filled by the Completor
     * - bankaccountnumber: not used for now: no webshop software stores this.
     * - mark
     *
     * @return array
     *   A keyed array with the customer data.
     */
    protected function getCustomer()
    {
        $customer = array();
        $customerSettings = $this->config->getCustomerSettings();
        $this->addDefault($customer, 'type', $customerSettings['defaultCustomerType']);
        $this->addTokenDefault($customer, 'contactyourid', $customerSettings['contactYourId']);
        $this->addDefaultEmpty($customer, 'contactstatus', $customerSettings['contactStatus']);
        $this->addTokenDefault($customer, 'companyname1', $customerSettings['companyName1']);
        $this->addTokenDefault($customer, 'companyname2', $customerSettings['companyName2']);
        $this->addTokenDefault($customer, 'vatnumber', $customerSettings['vatNumber']);
        $this->addTokenDefault($customer, 'fullname', $customerSettings['fullName']);
        $this->addTokenDefault($customer, 'salutation', $customerSettings['salutation']);
        $this->addTokenDefault($customer, 'address1', $customerSettings['address1']);
        $this->addTokenDefault($customer, 'address2', $customerSettings['address2']);
        $this->addTokenDefault($customer, 'postalcode', $customerSettings['postalCode']);
        $this->addTokenDefault($customer, 'city', $customerSettings['city']);
        $customer['countrycode'] = $this->countries->convertEuCountryCode($this->getCountryCode());
        $this->addDefault($customer, 'country', $this->countries->getCountryName($customer['countrycode']));
        $this->addTokenDefault($customer, 'telephone', $customerSettings['telephone']);
        $this->addTokenDefault($customer, 'fax', $customerSettings['fax']);
        $this->addTokenDefault($customer, 'email', $customerSettings['email']);
        $this->addDefaultEmpty($customer, 'overwriteifexists', $customerSettings['overwriteIfExists'] ? Api::OverwriteIfExists_Yes : Api::OverwriteIfExists_No);
        $this->addTokenDefault($customer, 'mark', $customerSettings['mark']);
        return $customer;
    }

    /**
     * Returns the country code for the order.
     *
     * @return string
     *   The 2 letter country code for the current order or the empty string if
     *   not set.
     */
    abstract protected function getCountryCode();

    /**
     * Returns the 'invoice' part of the invoice add structure.
     *
     * The following keys are allowed/expected by the API:
     * - concept: may be overridden by the Completor to make it a concept.
     * - [concepttype] : not (yet) used.
     * - number
     * - vattype: not needed, will be filled by the Completor
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
     * Additional keys (not recognised by the API but used later on by the
     * Creator or Completor or for support and debugging purposes):
     * - meta-payment-method: an id of the payment method used.
     * - meta-invoice-amount: the total invoice amount excluding VAT.
     * - meta-invoice-amountinc: the total invoice amount including VAT.
     * - meta-invoice-vatamount: the total vat amount for the invoice.
     * - meta-lines-amount: the total invoice amount excluding VAT.
     * - meta-lines-vatamount: the total vat amount for the invoice.
     * - meta-parent-index: defines an id (1-based index)) for a parent line.
     * - meta-children: indicates how many child lines the line has.
     * - meta-children-merged: indicates how many child lines the line had.
     * - meta-parent: indicates that a line is a child of parent line with the
     *   given value as meta-parent-index.
     *   children.
     *
     * Extending classes should normally not have to override this method, but
     * should instead implement getInvoiceNumber(), getInvoiceDate(),
     * getPaymentState(), getPaymentDate(), and, optionally, getDescription().
     *
     * @return array
     *   A keyed array with the invoice data (without the invoice lines).
     */
    protected function getInvoice()
    {
        $invoice = array();

        $shopSettings = $this->config->getShopSettings();
        $invoiceSettings = $this->config->getInvoiceSettings();

        // Invoice type.
        $concept = $invoiceSettings['concept'];
        if ($concept == ConfigInterface::Concept_Plugin) {
            $concept = Api::Concept_No;
        }
        $this->addDefaultEmpty($invoice, 'concept', $concept);

        // Invoice number and date.
        $sourceToUse = $shopSettings['invoiceNrSource'];
        if ($sourceToUse != ConfigInterface::InvoiceNrSource_Acumulus) {
            $invoice['number'] = $this->getInvoiceNumber($sourceToUse);
        }
        $dateToUse = $shopSettings['dateToUse'];
        if ($dateToUse != ConfigInterface::InvoiceDate_Transfer) {
            $invoice['issuedate'] = $this->getInvoiceDate($dateToUse);
        }

        // Bookkeeping (account number, cost center).
        $paymentMethod = $this->getPaymentMethod();
        if (!empty($paymentMethod)) {
            if (!empty($invoiceSettings['paymentMethodAccountNumber'][$paymentMethod])) {
                $invoice['accountnumber'] = $invoiceSettings['paymentMethodAccountNumber'][$paymentMethod];
            }
            if (!empty($invoiceSettings['paymentMethodCostCenter'][$paymentMethod])) {
                $invoice['costcenter'] = $invoiceSettings['paymentMethodCostCenter'][$paymentMethod];
            }
        }
        $this->addDefault($invoice, 'costcenter', $invoiceSettings['defaultCostCenter']);
        $this->addDefault($invoice, 'accountnumber', $invoiceSettings['defaultAccountNumber']);

        // Payment info.
        $invoice['paymentstatus'] = $this->getPaymentState();
        if ($invoice['paymentstatus'] === Api::PaymentStatus_Paid) {
            $this->addIfNotEmpty($invoice, 'paymentdate', $this->getPaymentDate());
        }

        // Additional descriptive info.
        $this->addTokenDefault($invoice, 'description', $invoiceSettings['description']);
        $this->addTokenDefault($invoice, 'descriptiontext', $invoiceSettings['descriptionText']);
        // Change newlines to the literal \n, tabs are not supported.
        if (!empty($invoice['descriptiontext'])) {
            $invoice['descriptiontext'] = str_replace(array("\r\n", "\r", "\n"), '\n', $invoice['descriptiontext']);
        }

        // Acumulus invoice template to use.
        // @todo: should this be done after the first event handler (invoice_created) has been triggered?
        if (isset($invoice['paymentstatus'])
            && $invoice['paymentstatus'] == Api::PaymentStatus_Paid
            // 0 = empty = use same invoice template as for non paid invoices.
            && $invoiceSettings['defaultInvoicePaidTemplate'] != 0
        ) {
            $this->addDefault($invoice, 'template', $invoiceSettings['defaultInvoicePaidTemplate']);
        } else {
            $this->addDefault($invoice, 'template', $invoiceSettings['defaultInvoiceTemplate']);
        }

        // Invoice notes.
        $this->addTokenDefault($invoice, 'invoicenotes', $invoiceSettings['invoiceNotes']);
        // Change newlines to the literal \n and tabs to \t.
        if (!empty($invoice['invoicenotes'])) {
            $invoice['invoicenotes'] = str_replace(array("\r\n", "\r", "\n", "\t"), array('\n', '\n', '\n', '\t'), $invoice['invoicenotes']);
        }

        // Meta data.
        $this->addIfNotEmpty($invoice, 'meta-payment-method', $paymentMethod);
        $invoice += $this->addInvoiceTotals($invoice);

        return $invoice;
    }

    /**
     * Returns the number to use as invoice number.
     *
     * @param int $invoiceNumberSource
     *   \Siel\Acumulus\Invoice\ConfigInterface\InvoiceNrSource_ShopInvoice or
     *   \Siel\Acumulus\Invoice\ConfigInterface\InvoiceNrSource_ShopOrder.
     *
     * @return string
     *   The number to use as invoice "number" on the Acumulus invoice, may
     *   contain a prefix.
     */
    protected function getInvoiceNumber($invoiceNumberSource) {
        $result = $this->invoiceSource->getInvoiceReference();
        if ($invoiceNumberSource != ConfigInterface::InvoiceNrSource_ShopInvoice || empty($result)) {
            $result = $this->invoiceSource->getReference();
        }
        return $result;
    }

    /**
     * Returns the date to use as invoice date.
     *
     * @param int $dateToUse
     *   \Siel\Acumulus\Invoice\ConfigInterface\InvoiceDate_InvoiceCreate or
     *   \Siel\Acumulus\Invoice\ConfigInterface\InvoiceDate_OrderCreate
     *
     * @return string
     *   Date to use as invoice date on the Acumulus invoice: yyyy-mm-dd.
     */
    protected function getInvoiceDate($dateToUse)
    {
        $result = $this->invoiceSource->getInvoiceDate();
        if ($dateToUse != ConfigInterface::InvoiceDate_OrderCreate || empty($result)) {
            $result = $this->invoiceSource->getDate();
        }
        return $result;
    }

    /**
     * Returns the payment method used.
     *
     * This default implementation returns an empty payment method.
     *
     * If no payment method is stored for credit notes, it is expected to be the
     * same as for its order, as this will normally indeed be the case.
     *
     * @return int|string|null
     *   A value identifying the payment method or null if unknown.
     */
    protected function getPaymentMethod()
    {
        return null;
    }

    /**
     * Returns whether the order has been paid or not.
     *
     * @return int
     *   \Siel\Acumulus\Invoice\ConfigInterface::PaymentStatus_Paid or
     *   \Siel\Acumulus\Invoice\ConfigInterface::PaymentStatus_Due
     */
    protected function getPaymentState()
    {
        return $this->callSourceTypeSpecificMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Returns the payment date.
     *
     * The payment date is defined as the date on which the status changed from a
     * non-paid state to a paid state. If there are multiple state changes, the
     * last one is taken.
     *
     * @return string|null
     *   The payment date (yyyy-mm-dd) or null if the order has not been paid yet.
     */
    protected function getPaymentDate()
    {
        return $this->callSourceTypeSpecificMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Adds metadata about invoice totals to the invoice.
     *
     * @param array $invoice
     *   The invoice to complete with totals meta data.
     *
     * @return array
     *   The invoice completed with invoice totals meta data.
     */
    protected function addInvoiceTotals(array $invoice)
    {
        $invoice += $this->getInvoiceTotals();
        $invoice = $this->completeInvoiceTotals($invoice);
        return $invoice;
    }

    /**
     * Returns an array with the totals fields.
     *
     * All total fields are optional but may be used or even expected by the
     * Completor or are used for support and debugging purposes.
     *
     * This default implementation returns an empty array. Override to provide the
     * values.
     *
     * @return array
     *   An array with the following possible keys:
     *   - meta-invoice-amount: the total invoice amount excluding VAT.
     *   - meta-invoice-amountinc: the total invoice amount including VAT.
     *   - meta-invoice-vatamount: the total vat amount for the invoice.
     *   - meta-invoice-vat: a vat breakdown per vat rate.
     */
    abstract protected function getInvoiceTotals();

    /**
     * Completes the set of invoice totals as set by getInvoiceTotals.
     *
     * Most shops only provide 2 out of these 3 in their data, so we calculate the
     * 3rd.
     *
     * @param array $invoice
     *   The invoice to complete with missing totals.
     *
     * @return array
     *   The invoice with completed invoice totals.
     */
    protected function completeInvoiceTotals(array $invoice)
    {
        if (!isset($invoice['meta-invoice-amount'])) {
            $invoice['meta-invoice-amount'] = $invoice['meta-invoice-amountinc'] - $invoice['meta-invoice-vatamount'];
            $invoice['meta-invoice-calculated'] = 'meta-invoice-amount';
        }
        if (!isset($invoice['meta-invoice-amountinc'])) {
            $invoice['meta-invoice-amountinc'] = $invoice['meta-invoice-amount'] + $invoice['meta-invoice-vatamount'];
            $invoice['meta-invoice-calculated'] = 'meta-invoice-amountinc';
        }
        if (!isset($invoice['meta-invoice-vatamount'])) {
            $invoice['meta-invoice-vatamount'] = $invoice['meta-invoice-amountinc'] - $invoice['meta-invoice-amount'];
            $invoice['meta-invoice-calculated'] = 'meta-invoice-vatamount';
        }
        return $invoice;
    }

    /**
     * Returns the 'invoice''line' part of the invoice add structure.
     *
     * Each invoice line is a keyed array.
     * The following keys are allowed/expected by the API:
     * -itemnumber
     * -product
     * -unitprice
     * -vatrate
     * -quantity
     * -costprice: optional, only for margin products
     *
     * Additional keys (not recognised by the API but used by the Completor or
     * for support and debugging purposes):
     * - unitpriceinc: the price of the item per unit including VAT.
     * - vatamount: the amount of vat per unit.
     * - meta-vatrate-source: the source for the vatrate value. Can be one of:
     *   - exact: should be an existing VAT rate.
     *   - calculated: should be close to an existing VAT rate, but may contain a
     *       rounding error.
     *   - completor: to be filled in by the completor.
     *   - strategy: to be filled in by a tax divide strategy. This may lead to
     *       this line being split into multiple lines.
     * - meta-vatrate-min: the minimum value for the vat rate, based on the
     *     precision of the numbers used to calculate the vat rate.
     * - meta-vatrate-max: the maximum value for the vat rate, based on the
     *     precision of the numbers used to calculate the vat rate.
     * - meta-strategy-split: boolean that indicates if this line may be split
     *     into multiple lines to divide vat.
     * - meta-line-price: the total price for this line excluding VAT.
     * - meta-line-priceinc: the total price for this line including VAT.
     * - meta-line-vatamount: the amount of VAT for the whole line.
     * - meta-line-type: the type of line (order, shipping, discount, etc.)
     * (*) = these are not yet used.
     *
     * These keys can be used to complete missing values or to assist in
     * correcting rounding errors in values that are present.
     *
     * The base CompletorInvoiceLines expects;
     * - meta-vatrate-source to be filled
     * - If meta-vatrate-source = exact: no other keys expected
     * - If meta-vatrate-source = calculated: meta-vatrate-min and
     *   meta-vatrate-max are expected to be filled. These values should come
     *   from calling the helper method getDivisionRange() with the values used to
     *   calculate the vat rate and their precision.
     * - If meta-vatrate-source = completor: vatrate should be null and unitprice
     *   should be 0. The completor will typically fill vatrate with the highest
     *   or most appearing vat rate, looking at the exact and calculated (after
     *   correcting them for rounding errors) vat rates.
     * - If meta-vatrate-source = strategy: vat rate should be null and either
     *   unitprice or unitpriceinc should be filled wit a non-0 amount (typically
     *   a negative amount as this is mostly used for spreading discounts over tax
     *   rates). Moreover, on the invoice level meta-invoice-amount and
     *   meta-invoice-vatamount should be filled in. The completor will use a tax
     *   divide strategy to arrive at valid values for the missing fields.
     *
     * Extending classes should normally not have to override this method, but
     * should instead implement getItemLines(), getManualLines(),
     * getGiftWrappingLine(), getShippingLine(), getPaymentFeeLine(),
     * and getDiscountLines().
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
     * @return array[]
     *  Array of item line arrays.
     */
    protected function getItemLines()
    {
        return $this->callSourceTypeSpecificMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Returns all the fee lines for the order.
     *
     * @return array[]
     *  An array of fee line arrays
     */
    protected function getFeeLines()
    {
        $result = array();

        $line = $this->getGiftWrappingLine();
        if ($line) {
            $line['meta-line-type'] = static::LineType_GiftWrapping;
            $result[] = $line;
        }

        $shippingLines = $this->getShippingLines();
        if ($shippingLines) {
            $shippingLines = $this->addLineType($shippingLines, static::LineType_Manual);
            $result = array_merge($result, $shippingLines);
        }

        $line = $this->getPaymentFeeLine();
        if ($line) {
            $line['meta-line-type'] = static::LineType_PaymentFee;
            $result[] = $line;
        }

        return $result;
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
     * Returns the shipping costs lines.
     *
     * This default implementation assumes there will be at most one shipping
     * line and as such calls the getShippingLine() method. Override if the shop
     * allows for multiple shipping lines
     *
     * @return array[]
     *   An array of line arrays, empty if there are no shipping fee lines.
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
     * This method should be overridden by webshops if they can provide a more
     * detailed name of the shipping method used.
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
     * Returns any applied discounts and partial payments.
     *
     * @return array[]
     *   An array of discount line arrays.
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
     *  An array of manual line arrays, may be empty.
     */
    protected function getManualLines()
    {
        return array();
    }

    /**
     * Constructs and returns the emailaspdf section (if enabled).
     *
     * @param string $fallbackEmailTo
     *   An email address yto use as fallback when the emailTo setting is empty.
     *
     * @return array The emailAsPdf section, possibly empty.
     * The emailAsPdf section, possibly empty.
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
        $invoiceSettings = $this->config->getInvoiceSettings();
        return $invoiceSettings['useMargin'];
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
                return TRUE;
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
     * Helper method to add a value from an array or object only if it is set and
     * not empty.
     *
     * @param array $targetArray
     * @param string $targetKey
     * @param array|object $source
     * @param string $sourceKey
     *
     * @return bool
     *   Whether the array value or object property is set and not empty and thus
     *   has been added.
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
     *   True if the value was not empty and thus has been added, false otherwise,
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
     *   default has been added.,
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
     * Adds a meta-line-type tag to the lines.
     *
     * @param array[] $lines
     * @param string $lineType
     *
     * @return array
     *
     */
    protected function addLineType(array $lines, $lineType)
    {
        foreach ($lines as &$line) {
            $line['meta-line-type'] = $lineType;
            if (isset($line[Creator::Line_Children])) {
                foreach ($line[Creator::Line_Children] as &$childLine) {
                    $childLine['meta-line-type'] = $lineType;
                }
            }
        }
        return $lines;
    }

    /**
     * Adds vat and vat range related tags to the invoice line.
     *
     * These tags include:
     * - vatrate: a vat rate based on dividing the vatamoumt by the unitprice
     * - vatamount (? = optional): if not already filled in.
     * - meta-vatrate-min (?): the minimum vat rate given the maximum rounding
     *     errors for the vatamount and the unitprice.
     * - meta-vatrate-max (?): the maximum vat rate given the maximum rounding
     *     errors for the vatamount and the unitprice.
     * - meta-vatrate-source: VatRateSource_Completor, VatRateSource_Exact0, or
     *     VatRateSource_Calculated.

     *
     * @param array $line
     * @param float $precisionNumerator
     * @param float $precisionDenominator
     */
    protected function addVatRangeTags(array &$line, $precisionNumerator = 0.01, $precisionDenominator = 0.01)
    {
        $numerator = isset($line['vatamount']) ? $line['vatamount'] : $line['unitpriceinc'] - $line['unitprice'];
        $denominator = $line['unitprice'];
        if (isset($line['costprice'])) {
            $denominator -= $line['costprice'];
        }
        $line += $this->getVatRangeTags($numerator, $denominator, $precisionNumerator, $precisionDenominator);
    }

    /**
     * Returns the range in which the vat rate will lie.
     *
     * If a webshop does not store the vat rates used in the order, we must
     * calculate them using a (product) price and the vat on it. But as webshops
     * often store these numbers rounded to cents, the vat rate calculation
     * becomes imprecise. Therefore we compute the range in which it will lie and
     * will let the Completor do a comparison with the actual vat rates that an
     * order can have (one of the Dutch or, for electronic services, other EU
     * country VAT rates).
     *
     * - If $denominator = 0 the vatrate will be set to NULL and the Completor may
     *   try to get this line listed under the correct vat rate.
     * - If $numerator = 0 the vatrate will be set to 0 and be treated as if it
     *   is an exact vat rate, not a vat range
     *
     * @param float $numerator
     * @param float $denominator
     * @param float $precisionNumerator
     * @param float $precisionDenominator
     *
     * @return array
     *   Array with keys vatrate, meta-vatrate-min, meta-vatrate-max, and
     *   meta-vatrate-source.
     */
    public static function getVatRangeTags($numerator, $denominator, $precisionNumerator = 0.01, $precisionDenominator = 0.01)
    {
        if (Number::isZero($denominator, 0.0001)) {
            return array(
                'vatrate' => null,
                'meta-vatrate-source' => static::VatRateSource_Completor,
            );
        } elseif (Number::isZero($numerator, 0.0001)) {
            return array(
                'vatrate' => 0,
                'vatamount' => $numerator,
                'meta-vatrate-source' => static::VatRateSource_Exact0,
            );
        } else {
            $range = Number::getDivisionRange($numerator, $denominator, $precisionNumerator, $precisionDenominator);
            return array(
                'vatrate' => 100.0 * $range['calculated'],
                'vatamount' => $numerator,
                'meta-vatrate-min' => 100.0 * $range['min'],
                'meta-vatrate-max' => 100.0 * $range['max'],
                'meta-vatrate-source' => static::VatRateSource_Calculated,
            );
        }
    }

    /**
     * Returns the sign to use for amounts that are always defined as a positive
     * number, also on credit notes.
     *
     * @return float
     *   1 for orders, -1 for credit notes.
     */
    protected function getSign()
    {
        return (float) ($this->invoiceSource->getType() === Source::CreditNote ? -1 : 1);
    }

    /**
     * Calls a method constructed of the method name and the source type.
     *
     * If the implementation/override of a method depends on the type of invoice
     * source it might be better to implement 1 method per source type. This
     * method calls such a method assuming it is named {method}{source-type}.
     * Example: getLineItem($line) would be very different for an order versus a
     * credit note: do not override the base method but implement 2 new methods
     * getLineItemOrder($line) and getLineItemCreditNote($line).
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
