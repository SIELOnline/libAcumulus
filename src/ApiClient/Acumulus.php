<?php
/**
 * @noinspection PhpMissingParamTypeInspection
 * @noinspection PhpMissingReturnTypeInspection
 * @noinspection PhpMissingFieldTypeInspection
 * @noinspection PhpClassHasTooManyDeclaredMembersInspection
 */

namespace Siel\Acumulus\ApiClient;

use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Helpers\Container;

/**
 * Provides an easy interface towards the different API calls of the Acumulus
 * web API.
 *
 * This class simplifies the communication so that the different web shop
 * specific interfaces can be more rapidly developed.
 *
 * More info:
 * - {@see https://www.siel.nl/acumulus/API/}
 * - {@see http://www.siel.nl/acumulus/koppelingen/}
 *
 * The ApiClient API call wrappers return their information as a keyed array,
 * which is a simplified version of the call specific part of the response
 * structure.
 */
class Acumulus
{
    /** @var \Siel\Acumulus\Config\Config */
    protected /*Config*/ $config;

    /** @var \Siel\Acumulus\Helpers\Container */
    protected /*Container*/ $container;

    /**
     * Constructor.
     *
     * @param \Siel\Acumulus\Helpers\Container $container
     * @param \Siel\Acumulus\Config\Config $config
     */
    public function __construct(Container $container, Config $config)
    {
        $this->config = $config;
        $this->container = $container;
    }

    /**
     * Retrieves the "about information".
     *
     * See {@see https://www.siel.nl/acumulus/API/Misc/About/}.

     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the webservice call. The structured response will contain
     *   1 "about" array, being a keyed array with keys:
     *   - about: General name for the API.
     *   - tree: stable, current, deprecated or closed.
     *   - role: Name of user role, current known roles: Beheerder, Gebruiker,
     *       Invoerder, API-beheerder, API-gebruiker, API-invoerder, API-open
     *       (not a real role, just to indicate the calls that are available
     *       without authentication).
     *   - roleid: Numeric identifier of user role.
     *   Possible errors:
     *   - 553 XUPR7NEC8: Warning: You are using a deprecated user role to
     *     connect to the Acumulus API. Please add another user with an
     *     API-compliant role or change the role for the current user.
     *   - 403 A8N403GCN: Forbidden - Insufficient credential level for
     *     general/general_about.php. Not authorized to perform request.
     */
    public function getAbout()
    {
        return $this->callApiFunction('general/general_about', [])->setMainResponseKey('general');
    }

    /**
     * Retrieves the "My Acumulus" information.
     *
     * See {@see https://www.siel.nl/acumulus/API/Misc/My_Acumulus/}
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the webservice call. The structured response will contain
     *   1 "mydata" array, being a keyed array with keys:
     *   - myaddress
     *   - mycity
     *   - mycompanyname
     *   - mycontactperson
     *   - mycontractcode
     *   - mycontractenddate
     *   - mydebt
     *   - myemail
     *   - myemailstatusid
     *   - myemailstatusreferenceid
     *   - myentries
     *   - myentriesleft
     *   - myiban
     *   - mymaxentries
     *   - mypostalcode
     *   - mysalutation
     *   - mysepamandatenr
     *   - mystatusid
     *   - mytelephone
     *   - myvatnumber
     *   Possible errors: todo.
     */
    public function getMyAcumulus()
    {
        return $this->callApiFunction('general/my_acumulus', [])->setMainResponseKey('mydata');
    }

    /**
     * Retrieves a list of accounts.
     *
     * @param bool $enabled
     *   Whether to retrieve enabled (true, default) or disabled (false)
     *   accounts.
     *
     * See {@see https://www.siel.nl/acumulus/API/Accounts/List_Accounts/}
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the webservice call. The structured response will contain
     *   a non-keyed array of "account" arrays, each "account" array being a
     *   keyed array with keys:
     *   - accountid
     *   - accountnumber
     *   - accountdescription
     *   - accountorderid
     *   - accountstatus
     *   - accounttypeid
     */
    public function getPicklistAccounts($enabled = true)
    {
        $filters = [
            'accountstatus' => $enabled ? 1 : 0,
        ];
        return $this->getPicklist('accounts', $filters);
    }

    /**
     * Retrieves a list of invoice templates.
     *
     * See {@see https://www.siel.nl/acumulus/API/Picklists/Company_Types/}.
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the webservice call. The structured response will contain
     *   a non-keyed array of "companytype" arrays, each "companytype"
     *   array being a keyed array with keys:
     *   - companytypeid
     *   - companytypename
     *   - companytypenamenl
     */
    public function getPicklistCompanyTypes()
    {
        return $this->getPicklist('companytypes', [], false);
    }

    /**
     * Retrieves a list of contact types.
     *
     * See {@see https://www.siel.nl/acumulus/API/Picklists/Contact_Types/}.
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the webservice call. The structured response will contain
     *   a non-keyed array of "contacttype" arrays, each "contacttype" array
     *   being a keyed array with keys:
     *   - contacttypeid
     *   - contacttypename
     */
    public function getPicklistContactTypes()
    {
        return $this->getPicklist('contacttypes');
    }

    /**
     * Retrieves a list of cost centers.
     *
     * See {@see https://www.siel.nl/acumulus/API/Picklists/Cost_Centers/}
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the webservice call. The structured response will contain
     *   a non-keyed array of "costcenter" arrays, each "costcenter" array being
     *   a keyed array with keys:
     *   - costcenterid
     *   - costcentername
     */
    public function getPicklistCostCenters()
    {
        return $this->getPicklist('costcenters');
    }

    /**
     * Retrieves a list of invoice templates.
     *
     * See {@see https://www.siel.nl/acumulus/API/Invoicing/Invoice_Templates/}
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the webservice call. The structured response will contain
     *   a non-keyed array of "invoicetemplate" arrays, each "invoicetemplate"
     *   array being a keyed array with keys:
     *   - invoicetemplateid
     *   - invoicetemplatename
     */
    public function getPicklistInvoiceTemplates()
    {
        return $this->getPicklist('invoicetemplates');
    }

    /**
     * Retrieves a list of products.
     *
     * See {@see https://www.siel.nl/acumulus/API/Products/List_Products/}
     *
     * @param string|null $filter
     *   Free search param, checks against the productid, description, type,
     *   SKU, EAN and price of the product.
     * @param int|null $productTagId
     *   To filter products by status:
     *   - null: All (DEFAULT)
     *   - -1: Vervallen (discontinued)
     *   - 0: Actief (active/available)
     *   - 1000: Favoriet (Favorite)
     * @param int|null $offset
     * @param int|null $rowcount
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the webservice call. The structured response will contain
     *   a non-keyed array of "product" arrays, each "product"
     *   array being a keyed array with keys:
     *   - productid
     *   - productnature
     *   - productdescription
     *   - producttagid
     *   - productcontactid
     *   - productprice
     *   - productvatrate
     *   - productsku
     *   - productstockamount
     *   - productean
     *   - producthash
     *   - productnotes
     *
     * @noinspection PhpUnused  Not yet used, but this is a library that,
     *   eventually, should cover all web services provided.
     */
    public function getPicklistProducts($filter = null, $productTagId = null, $offset = null, $rowcount = null)
    {
        $filters = [];
        if ($filter !== null) {
            $filters['filter'] = (string) $filter;
        }
        if ($productTagId !== null) {
            $filters['producttagid'] = (int) $productTagId;
        }
        if ($offset !== null) {
            $filters['offset'] = (int) $offset;
        }
        if ($rowcount !== null) {
            $filters['rowcount'] = (int) $rowcount;
        }
        return $this->getPicklist('products', $filters);
    }

    /**
     * A helper method to retrieve a given picklist.
     *
     * The Acumulus API for picklists is so well standardized, that it is
     * possible to use 1 general picklist retrieval function that can process
     * all picklist types.
     *
     * @param string $picklist
     *   The picklist to retrieve, specify in plural form: accounts,
     *   contacttypes, costcenters, etc.
     * @param array $filters
     *   A set of filters to filter the picklist. Currently, only the Products
     *   picklist supports filters.
     * @param bool $needContract
     *   Whether the contract part needs to be sent with the request.
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the webservice call. The structured response will contain
     *   a non-keyed array of "picklist" arrays, each 'picklist' array being a
     *   keyed array with keys that depend on the requested picklist.
     */
    protected function getPicklist($picklist, array $filters = [], $needContract = true)
    {
        // For picklists, the main result is found under the name of the
        // picklist but in singular form, i.e. without the s at the end.
        return $this->callApiFunction("picklists/picklist_$picklist", $filters, $needContract)->setMainResponseKey($picklist, true);
    }

    /**
     * Retrieves a list of VAT rates for the given country at the given date.
     *
     * See {@see https://www.siel.nl/acumulus/API/Picklists/VAT_Info/}
     *
     * @param string $countryCode
     *   Country code of the country to retrieve the VAT info for.
     * @param string $date
     *   ISO date string (yyyy-mm-dd) for the date to retrieve the VAT info for.
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the webservice call. The structured response will contain
     *   a non-keyed array of "vatinfo" arrays, each 'vatinfo' array being a
     *   keyed array with keys:
     *   - vattype
     *   - vatrate
     */
    public function getVatInfo($countryCode, $date = '')
    {
        if (empty($date)) {
            $date = date(Api::DateFormat_Iso);
        }
        $message = [
            'vatcountry' => $countryCode,
            'vatdate' => $date,
        ];
        return $this->callApiFunction('lookups/lookup_vatinfo', $message)->setMainResponseKey('vatinfo', true);
    }

    /**
     * Retrieves a report on the threshold for EU commerce.
     *
     * See {@see https://www.siel.nl/acumulus/API/Reports/EU_eCommerce_Threshold/}
     *
     * @param int $year
     *   The year to get a report for. Leave empty for the current year.
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the webservice call. The structured response will contain
     *   an array with keys:
     *   - year: int, Year to which information in response is applicable.
     *   - threshold: float, threshold value at which the EU e-Commerce
     *     directive applies.
     *   - nltaxed, float, Amount of turnover taxed using Dutch VAT against
     *     target customer set. Should ideally not gain when threshold reached.
     *   - reached: int, 0 when threshold not reached. 1 when so.
     *   Possible errors:
     *   - AAC37EAA: Ongeldig year. EU regelgeving van toepassing vanaf 2021.
     */
    public function reportThresholdEuCommerce($year = null)
    {
        $message = [];
        if (!empty($year)) {
            $message['year'] = (int) $year;
        }
        return $this->callApiFunction('reports/report_threshold_eu_ecommerce', $message)->setMainResponseKey('');
    }

    /**
     * Sends an invoice to Acumulus.
     *
     * See {@see https://www.siel.nl/acumulus/API/Invoicing/Add_Invoice/}
     *
     * @param array $invoice
     *   The invoice to send.
     * @param ?\Siel\Acumulus\ApiClient\Result $result
     *   It is possible to already create a Result object before calling this
     *   api-client to store local messages. By passing this Result object these
     *   local messages will be merged with any remote messages in the returned
     *   Result object.
     *
     * @return \Siel\Acumulus\ApiClient\Result
     * The Result of the webservice call. A successful call will contain a
     * response array with key:
     * - invoice: an array of information about the created invoice, being an
     *   array with keys:
     *   - invoicenumber
     *   - token
     *   - entryid
     *   - conceptid
     */
    public function invoiceAdd(array $invoice, Result $result = null)
    {
        return $this->callApiFunction('invoices/invoice_add', $invoice, true, $result)->setMainResponseKey('invoice');
    }

    /**
     * Retrieves information about a concept.
     *
     * See {@see https://www.siel.nl/acumulus/API/Invoicing/Concept_Info/}
     *
     * @param int $conceptId
     *   The id of the concept.
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the webservice call. The structured response will contain
     *   1 "concept" array, being a keyed array with keys:
     *   - conceptid: int
     *   - entryid: int|int[]
     *   Possible errors:
     *   - FGYBSN040: Requested invoice for concept $conceptId not found: No
     *     definitive invoice has yet been created for this concept.
     *   - FGYBSN048: Information not available for $conceptId older than 127466.
     *   - todo: others?
     */
    public function getConceptInfo($conceptId)
    {
        $message = [
            'conceptid' => (int) $conceptId,
        ];
        return $this->callApiFunction('invoices/invoice_concept_info', $message)->setMainResponseKey('concept');
    }

    /**
     * Retrieves Entry (Boeking) Details.
     *
     * See {@see https://siel.nl/acumulus/API/Entry/Get_Entry_Details/}
     *
     * @param int $entryId
     *   The id of the entry.
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the webservice call. The structured response will contain
     *   1 "entry" array, being a keyed array with keys:
     *   - entryid
     *   - entrydate
     *   - entrytype
     *   - entrydescription
     *   - entrynote
     *   - fiscaltype
     *   - vatreversecharge
     *   - foreigneu
     *   - foreignnoneu
     *   - marginscheme
     *   - foreignvat
     *   - contactid
     *   - accountnumber
     *   - costcenterid
     *   - costtypeid
     *   - invoicenumber
     *   - invoicenote
     *   - descriptiontext
     *   - invoicelayoutid
     *   - totalvalueexclvat
     *   - totalvalue
     *   - paymenttermdays
     *   - paymentdate
     *   - paymentstatus
     *   - deleted
     *   Possible errors:
     *   - XGYBSN000: Requested invoice for entry $entryId not found: $entryId
     *     does not exist.
     */
    public function getEntry($entryId)
    {
        $message = [
            'entryid' => (int) $entryId,
        ];
        return $this->callApiFunction('entry/entry_info', $message)->setMainResponseKey('entry');
    }

    /**
     * Moves the entry into or out of the trash bin.
     *
     * See {@see https://siel.nl/acumulus/API/Entry/Set_Delete_Status/}
     *
     * @param int $entryId
     *   The id of the entry.
     * @param int $deleteStatus
     *   The delete action to perform: one of the API::Entry_Delete or
     *   API::Entry_UnDelete constants. API::Entry_UnDelete does not work for
     *   now.
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the webservice call. The structured response will contain
     *   1 "entry" array, being a keyed array with keys:
     *   - entryid
     *   - entryproc: (description new status): 'removed', 'recovered' or 'no
     *     changes made'.
     *   Possible errors:
     *   - XCM7ELO12: Invalid entrydeletestatus value supplied: $deleteStatus
     *     is not one of the indicated constants.
     *   - XCM7ELO14: Invalid entrydeletestatus value supplied: $deleteStatus
     *     is not one of the indicated constants.
     *   - P2XFELO12: Requested for entryid: $entryId not found or forbidden:
     *     $entryId does not exist or already has requested status.
     */
    public function setDeleteStatus($entryId, $deleteStatus)
    {
        $message = [
            'entryid' => (int) $entryId,
            'entrydeletestatus' => (int) $deleteStatus,
        ];
        return $this->callApiFunction('entry/entry_deletestatus_set', $message)->setMainResponseKey('entry');
    }

    /**
     * Retrieves the payment status for an invoice.
     *
     * See {@see https://www.siel.nl/acumulus/API/Invoicing/Payment_Get_Status/}
     *
     * @param string $token
     *   The token for the invoice.
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the webservice call. The structured response will contain
     *   1 "invoice" array, being a keyed array with keys:
     *   - entryid
     *   - token
     *   - paymentstatus
     *   - paymentdate
     *   Possible errors:
     *   - XGYTTNF04: Requested invoice for $token not found: $token does not
     *     exist.
     *
     * @noinspection PhpUnused
     */
    public function getPaymentStatus($token)
    {
        $message = [
            'token' => (string) $token,
        ];
        return $this->callApiFunction('invoices/invoice_paymentstatus_get', $message)->setMainResponseKey('invoice');
    }

    /**
     * Sets the payment status for an invoice.
     *
     * See {@see https://www.siel.nl/acumulus/API/Invoicing/Payment_Set_Status/}
     *
     * @param string $token
     *   The token for the invoice.
     * @param int $paymentStatus
     *   The new payment status, 1 of the API::PaymentStatus_Paid or
     *   API::PaymentStatus_Due constants.
     * @param string $paymentDate
     *   ISO date string (yyyy-mm-dd) for the date to set as payment date, may
     *   be empty for today or if the payment status is API::PaymentStatus_Due.
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the webservice call. The structured response will contain
     *   1 "invoice" array, being a keyed array with keys:
     *   - entryid
     *   - token
     *   - paymentstatus
     *   - paymentdate
     *   Possible errors:
     *   - DATE590ZW: Missing mandatory paymentdate field. Unable to proceed."
     *   - DATE590ZW: Incorrect date range (2000-01-01 to 2099-12-31) or invalid
     *     date format (YYYY-MM-DD) used in paymentdate field. We received:
     *     $paymentDate. Unable to proceed."
     */
    public function setPaymentStatus($token, $paymentStatus, $paymentDate = '')
    {
        if (empty($paymentDate)) {
            $paymentDate = date(Api::DateFormat_Iso);
        }
        $message = [
            'token' => (string) $token,
            'paymentstatus' => (int) $paymentStatus,
            'paymentdate' => (string) $paymentDate,
        ];
        return $this->callApiFunction('invoices/invoice_paymentstatus_set', $message)->setMainResponseKey('invoice');
    }

    /**
     * Sends out an invoice or reminder as PDF.
     *
     * See {@see https://siel.nl/acumulus/API/Invoicing/Email/}
     *
     * @param string $token
     *   The token for the invoice.
     * @param array $emailAsPdf
     *   An array with the fields:
     *   - emailto
     *   - emailbcc
     *   - emailfrom
     *   - subject
     *   - message
     *   - confirmreading
     * @param int|null $invoiceType
     *   One of the constants API::Email_Normal (default) or API::Email_Reminder.
     * @param string $invoiceNotes
     *   Multiline field for additional remarks. Use \n for newlines and \t for
     *   tabs. Contents is placed in notes/comments section of the invoice.
     *   Content will not appear on the actual invoice or associated emails.
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the webservice call. The structured response will contain
     *   1 "invoice" array, being a keyed array with keys:
     *   - token
     *   - invoicetype
     *   Possible errors/warnings:
     *   - GK6FKHU52: Incorrect invoicetype value used (9) in invoicetype tag as
     *     part of invoice section in the XML. Using default value of 0 normal."
     *   - TNFE4035G: Requested token not found or invalid token supplied.
     *     Unable to proceed."
     *
     * See {@see https://siel.nl/acumulus/API/Invoicing/Email/}
     *
     * @noinspection PhpUnused
     */
    public function emailInvoiceAsPdf($token, array $emailAsPdf, $invoiceType = null, $invoiceNotes = '')
    {
        $message = [
            'token' => (string) $token,
            'emailaspdf' => $emailAsPdf,
        ];
        if ($invoiceType !== null) {
            $message['invoicetype'] = (int) $invoiceType;
        }
        if (!empty($invoiceNotes)) {
            $message['invoicenotes'] = (string) $invoiceNotes;
        }
        return $this->callApiFunction('invoices/invoice_mail', $message)->setMainResponseKey('invoice');
    }

    /**
     * Signs up for a 30-day trial and receive credentials.
     *
     * See {@see https://www.siel.nl/acumulus/API/Sign_Up/Sign_Up/}
     *
     * @param array $signUp
     *   An array with the fields:
     *   - companyname (mandatory) Name of company to sign up.
     *   - fullname (mandatory) Full name of person associated with company.
     *   - loginname (mandatory) Preferred login name to be used as credentials
     *     when logging in.
     *   - gender (mandatory) Indication of gender. Used to predefine some
     *     strings within Acumulus.
     *     - F Female
     *     - M Male
     *     - X Neutral
     *   - address (mandatory) Address including house number.
     *   - postalcode (mandatory)
     *   - city (mandatory)
     *   - telephone
     *   - bankaccount Preference is to use a valid IBAN-code so Acumulus can
     *     improve preparation of the (trial) sign up.
     *   - email (mandatory)
     *   - createapiuser Include the creation of an additional user specifically
     *     suited for API-usage.
     *     - 0 Do not create additional user (default)
     *     - 1 Generate additional user specifically suited for API-usage
     *   - Notes or remarks which you would like to be part of the sign-up
     *     request. If filled, a ticket will be opened with the notes as
     *     content, so can be used as a request for comment by customer support.
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the webservice call. The structured response will contain
     *   1 "signup" array, being a keyed array with keys:
     *   - contractcode
     *   - contractloginname
     *   - contractpassword
     *   - contractapiuserloginname
     *   - contractapiuserpassword
     *
     *   Possible errors/warnings:
     *   - AA7E10AA: Verplichte companyname ontbreekt
     *   - AAC8C3AA: Verplichte fullname ontbreekt
     *   - AAFA1AAA: Verplichte loginname ontbreekt
     *   - AAE9CDAA: Verplichte address ontbreekt
     *   - AAC34DAA: Verplichte postalcode ontbreekt
     *   - AA6894AA: Onjuiste postalcode
     *   - AABC1FAA: Verplichte city ontbreekt
     *
     * @noinspection PhpUnused
     */
    public function signUp(array $signUp)
    {
        $message = [
            'signup' => $signUp,
        ];
        return $this->callApiFunction('signup/signup', $message, false)->setMainResponseKey('signup');
    }

    /**
     * Updates the stock for a product.
     *
     * See {@see https://www.siel.nl/acumulus/API/Stock/Add_Stock_Transaction/}
     *
     * @param int $productId
     *   The id of the product for which to update the stock.
     * @param float $quantity
     *   The quantity to update the actual stock with. Use a positive number for
     *   an increase in stock (typically with a return), a negative number for a
     *   decrease of stock (typically with an order).
     * @param string $description
     *   The description to store with the stock update. Ideally, this field
     *   should identify the system and transaction that triggered the update
     *   In this context thus probably shop and order/refund number.
     * @param string $date
     *   ISO date string (yyyy-mm-dd) for the date to set as update date for the
     *   stock update.
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the webservice call. The structured response will contain
     *   1 "stock" array, being a keyed array with keys:
     *   - productid
     *   - stockamount (the new stock level for this product)
     *   Possible errors:
     */
    public function stockAdd($productId, $quantity, $description, $date = null)
    {
        if (empty($date)) {
            $date = date(Api::DateFormat_Iso);
        }
        $message = [
            'stock' => [
                'productid' => (int) $productId,
                'stockamount' => (float) $quantity,
                'stockdescription' => $description,
                'stockdate' => $date,
            ]
        ];
        return $this->callApiFunction('stock/stock_add', $message)->setMainResponseKey('stock');
    }

    /**
     * Returns the uri to download the invoice PDF.
     *
     * See {@see https://siel.nl/acumulus/API/Invoicing/Get_PDF_Invoice/}
     *
     * @param string $token
     *   The token for the invoice.
     * @param bool $applyGraphics
     *   False to prevent any embedded graphics from being applied to the
     *   document, true otherwise.
     *
     * @return string
     *   The uri to download the invoice PDF.
     *   Possible errors (in download, not in return value):
     *   - PDFATNF04: Requested invoice for $token not found: $token does not
     *     exist.
     */
    public function getInvoicePdfUri($token, $applyGraphics = true)
    {
        $uri = $this->constructUri('invoices/invoice_get_pdf');
        $uri .= "?token=$token";
        if (!$applyGraphics) {
            $uri .= '&gfx=0';
        }
        return $uri;
    }

    /**
     * Returns the uri to download the packing slip PDF.
     *
     * See {@see https://siel.nl/acumulus/API/Delivery/Get_PDF_Packing_Slip/}
     *
     * @param string $token
     *   The token for the invoice to get the packing slip for.
     *
     * @return string
     *   The uri to download the packing slip PDF.
     *   Possible errors (in download, not in return value):
     *   - ZKFATNF04: Requested packing slip for $token not found or no longer
     *     available.
     */
    public function getPackingSlipUri($token)
    {
        $uri = $this->constructUri('delivery/packing_slip_get_pdf');
        $uri .= "?token=$token";
        return $uri;
    }

    /**
     * Constructs and returns the uri for the requested API service.
     *
     * @param string $apiFunction
     *   The api service to get the uri for.
     *
     * @return string
     *   The uri to the requested API service.
     */
    protected function constructUri($apiFunction)
    {
        $environment = $this->config->getEnvironment();
        return $environment['baseUri'] . '/' . $environment['apiVersion'] . '/' . $apiFunction . '.php';
    }

    /**
     * Wrapper around
     * {@see \Siel\Acumulus\ApiClient\AcumulusRequest::callApiFunction()}.
     *
     * @param string $apiFunction
     *   The API function to invoke.
     * @param array $message
     *   The values to submit.
     * @param bool $needContract
     *   Indicates whether this api function needs the contract details. Most
     *   API functions do, do the default is true, but for some general listing
     *   functions, like vat info, it is optional, and for signUp, it is even
     *   not allowed.
     * @param \Siel\Acumulus\ApiClient\Result|null $result
     *   It is possible to already create a Result object before calling the
     *   api-client to store local messages. By passing this Result object these
     *   local messages will be merged with any remote messages in the returned
     *   Result object.
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   A Result object containing the results.
     */
    protected function callApiFunction($apiFunction, array $message, $needContract = true, Result $result = null)
    {
        $acumulusRequest = $this->container->getAcumulusRequest();
        $uri = $this->constructUri($apiFunction);
        return $acumulusRequest->execute($uri, $message, $needContract, $result);
    }
}
