<?php
/**
 * @noinspection SpellCheckingInspection : fields in the Acumulus API are in
 *   all lowercase with no underscore as separator. As many fields consists of
 *   joined words this leads to hundreds of spell error inspection problems.
 */

namespace Siel\Acumulus\ApiClient;

use RuntimeException;
use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Environment;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\Util;
/**
 * Provides an easy interface towards the different API calls of the Acumulus
 * web API.
 *
 * This class simplifies the communication so that the different web shop
 * specific interfaces can be more rapidly developed.
 *
 * More info:
 * - {@link https://www.siel.nl/acumulus/API/}
 * - {@link https://www.siel.nl/acumulus/koppelingen/}
 *
 * The ApiClient API call wrappers return their information as a keyed array,
 * which is a simplified version of the call specific part of the response
 * structure.
 *
 * @noinspection PhpClassHasTooManyDeclaredMembersInspection
 */
class Acumulus
{
    protected /*Environment*/ $environment;
    protected /*Util*/ $util;
    protected /*Log*/ $log;
    protected /*Container*/ $container;

    /**
     * Constructor.
     *
     * @param \Siel\Acumulus\Helpers\Container $container
     * @param \Siel\Acumulus\Config\Environment $environment
     */
    public function __construct(Container $container, Environment $environment, Util $util, Log $log)
    {
        $this->environment = $environment;
        $this->container = $container;
        $this->util = $util;
        $this->log = $log;
    }

    /**
     * Retrieves the "about information".
     *
     * See {@link https://www.siel.nl/acumulus/API/Misc/About/}.
     *
     * @return \Siel\Acumulus\ApiClient\AcumulusResult
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
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
     */
    public function getAbout(): AcumulusResult
    {
        return $this->callApiFunction('general/general_about', [])->setMainAcumulusResponseKey('general');
    }

    /**
     * Retrieves the "My Acumulus" information.
     *
     * See {@link https://www.siel.nl/acumulus/API/Misc/My_Acumulus/}
     *
     * @return \Siel\Acumulus\ApiClient\AcumulusResult
     *   The result of the webservice call. The structured response will contain
     *   1 "mydata" array, being a keyed array with keys:
     * - 'myaddress'
     * - 'mycity'
     * - 'mycompanyname'
     * - 'mycontactperson'
     * - 'mycontractcode'
     * - 'mycontractenddate'
     * - 'mydebt'
     * - 'myemail'
     * - 'myemailstatusid'
     * - 'myemailstatusreferenceid'
     * - 'myentries'
     * - 'myentriesleft'
     * - 'myiban'
     * - 'mymaxentries'
     * - 'mypostalcode'
     * - 'mysalutation'
     * - 'mysepamandatenr'
     * - 'mystatusid'
     * - 'mytelephone'
     * - 'myvatnumber'
     *   Possible errors: todo.
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
     */
    public function getMyAcumulus(): AcumulusResult
    {
        return $this->callApiFunction('general/my_acumulus', [])->setMainAcumulusResponseKey('mydata');
    }

    /**
     * Retrieves a list of accounts.
     *
     * @param bool $enabled
     *   Whether to retrieve enabled (true, default) or disabled (false)
     *   accounts.
     *
     * See {@link https://www.siel.nl/acumulus/API/Accounts/List_Accounts/}
     *
     * @return \Siel\Acumulus\ApiClient\AcumulusResult
     *   The result of the webservice call. The structured response will contain
     *   a non-keyed array of "account" arrays, each "account" array being a
     *   keyed array with keys:
     * - 'accountid'
     * - 'accountnumber'
     * - 'accountdescription'
     * - 'accountorderid'
     * - 'accountstatus'
     * - 'accounttypeid'
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
     */
    public function getPicklistAccounts(bool $enabled = true): AcumulusResult
    {
        $filters = [
            'accountstatus' => $enabled ? 1 : 0,
        ];
        return $this->getPicklist('accounts', $filters);
    }

    /**
     * Retrieves a list of invoice templates.
     *
     * See {@link https://www.siel.nl/acumulus/API/Picklists/Company_Types/}.
     *
     * @return \Siel\Acumulus\ApiClient\AcumulusResult
     *   The result of the webservice call. The structured response will contain
     *   a non-keyed array of "companytype" arrays, each "companytype"
     *   array being a keyed array with keys:
     * - 'companytypeid'
     * - 'companytypename'
     * - 'companytypenamenl'
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
     */
    public function getPicklistCompanyTypes(): AcumulusResult
    {
        return $this->getPicklist('companytypes', [], false);
    }

    /**
     * Retrieves a list of contact types.
     *
     * See {@link https://www.siel.nl/acumulus/API/Picklists/Contact_Types/}.
     *
     * @return \Siel\Acumulus\ApiClient\AcumulusResult
     *   The result of the webservice call. The structured response will contain
     *   a non-keyed array of "contacttype" arrays, each "contacttype" array
     *   being a keyed array with keys:
     * - 'contacttypeid'
     * - 'contacttypename'
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
     */
    public function getPicklistContactTypes(): AcumulusResult
    {
        return $this->getPicklist('contacttypes');
    }

    /**
     * Retrieves a list of cost centers.
     *
     * See {@link https://www.siel.nl/acumulus/API/Picklists/Cost_Centers/}
     *
     * @return \Siel\Acumulus\ApiClient\AcumulusResult
     *   The result of the webservice call. The structured response will contain
     *   a non-keyed array of "costcenter" arrays, each "costcenter" array being
     *   a keyed array with keys:
     * - 'costcenterid'
     * - 'costcentername'
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
     */
    public function getPicklistCostCenters(): AcumulusResult
    {
        return $this->getPicklist('costcenters');
    }

    /**
     * Retrieves a list of invoice templates.
     *
     * See {@link https://www.siel.nl/acumulus/API/Invoicing/Invoice_Templates/}
     *
     * @return \Siel\Acumulus\ApiClient\AcumulusResult
     *   The result of the webservice call. The structured response will contain
     *   a non-keyed array of "invoicetemplate" arrays, each "invoicetemplate"
     *   array being a keyed array with keys:
     * - 'invoicetemplateid'
     * - 'invoicetemplatename'
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
     */
    public function getPicklistInvoiceTemplates(): AcumulusResult
    {
        return $this->getPicklist('invoicetemplates');
    }

    /**
     * Retrieves a list of products.
     *
     * See {@link https://www.siel.nl/acumulus/API/Products/List_Products/}
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
     * @return \Siel\Acumulus\ApiClient\AcumulusResult
     *   The result of the webservice call. The structured response will contain
     *   a non-keyed array of "product" arrays, each "product"
     *   array being a keyed array with keys:
     * - 'productid'
     * - 'productnature'
     * - 'productdescription'
     * - 'producttagid'
     * - 'productcontactid'
     * - 'productprice'
     * - 'productvatrate'
     * - 'productsku'
     * - 'productstockamount'
     * - 'productean'
     * - 'producthash'
     * - 'productnotes'
     *
     * @noinspection PhpUnused  Not yet used, but this is a library that,
     *   eventually, should cover all web services provided.
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
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
     * @return \Siel\Acumulus\ApiClient\AcumulusResult
     *   The result of the webservice call. The structured response will contain
     *   a non-keyed array of "picklist" arrays, each 'picklist' array being a
     *   keyed array with keys that depend on the requested picklist.
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
     */
    protected function getPicklist(string $picklist, array $filters = [], bool $needContract = true): AcumulusResult
    {
        // For picklists, the main result is found under the name of the
        // picklist but in singular form, i.e. without the s at the end.
        return $this->callApiFunction("picklists/picklist_$picklist", $filters, $needContract)->setMainAcumulusResponseKey($picklist, true);
    }

    /**
     * Retrieves a list of VAT rates for the given country at the given date.
     *
     * See {@link https://www.siel.nl/acumulus/API/Picklists/VAT_Info/}
     *
     * @param string $countryCode
     *   Country code of the country to retrieve the VAT info for.
     * @param string $date
     *   ISO date string (yyyy-mm-dd) for the date to retrieve the VAT info for.
     *
     * @return \Siel\Acumulus\ApiClient\AcumulusResult
     *   The result of the webservice call. The structured response will contain
     *   a non-keyed array of "vatinfo" arrays, each "vatinfo" array being a
     *   keyed array with keys:
     *   - vattype: (string) name for the vat rate, most of the time something
     *     like 'normal' or 'reduced'.
     *   - vatrate: (float (as a string)) the vat rate (number between 0 and
     *     100)
     *   - countryregion: (int) one of the Api::Region_... constants.
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
     */
    public function getVatInfo(string $countryCode, string $date = ''): AcumulusResult
    {
        if (empty($date)) {
            $date = date(Api::DateFormat_Iso);
        }
        $message = [
            'vatcountry' => $countryCode,
            'vatdate' => $date,
        ];
        return $this->callApiFunction('lookups/lookup_vatinfo', $message)->setMainAcumulusResponseKey('vatinfo', true);
    }

    /**
     * Retrieves a report on the threshold for EU commerce.
     *
     * See {@link https://www.siel.nl/acumulus/API/Reports/EU_eCommerce_Threshold/}
     *
     * @param int|null $year
     *   The year to get a report for. Leave empty for the current year.
     *
     * @return \Siel\Acumulus\ApiClient\AcumulusResult
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
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
     */
    public function reportThresholdEuCommerce(int $year = null): AcumulusResult
    {
        $message = [];
        if (!empty($year)) {
            $message['year'] = $year;
        }
        return $this->callApiFunction('reports/report_threshold_eu_ecommerce', $message)->setMainAcumulusResponseKey('');
    }

    /**
     * Sends an invoice to Acumulus.
     *
     * See {@link https://www.siel.nl/acumulus/API/Invoicing/Add_Invoice/}
     *
     * @param array $invoice
     *   The invoice to send.
     *
     * @return \Siel\Acumulus\ApiClient\AcumulusResult
     * The Result of the webservice call. A successful call will contain a
     * response array with key:
     * - invoice: an array of information about the created invoice, being an
     *   array with keys:
     * - 'invoicenumber'
     * - 'token'
     * - 'entryid'
     * - 'conceptid'
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
     */
    public function invoiceAdd(array $invoice): AcumulusResult
    {
        return $this->callApiFunction('invoices/invoice_add', $invoice)->setMainAcumulusResponseKey('invoice');
    }

    /**
     * Retrieves information about a concept.
     *
     * See {@link https://www.siel.nl/acumulus/API/Invoicing/Concept_Info/}
     *
     * @param int $conceptId
     *   The id of the concept.
     *
     * @return \Siel\Acumulus\ApiClient\AcumulusResult
     *   The result of the webservice call. The structured response will contain
     *   1 "concept" array, being a keyed array with keys:
     *   - conceptid: int
     *   - entryid: int|int[]
     *   Possible errors:
     *   - FGYBSN040: Requested invoice for concept $conceptId not found: No
     *     definitive invoice has yet been created for this concept.
     *   - FGYBSN048: Information not available for $conceptId older than 127466.
     *   - todo: others?
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
     */
    public function getConceptInfo(int $conceptId): AcumulusResult
    {
        $message = [
            'conceptid' => $conceptId,
        ];
        return $this->callApiFunction('invoices/invoice_concept_info', $message)->setMainAcumulusResponseKey('concept');
    }

    /**
     * Retrieves Entry (Boeking) Details.
     *
     * See {@link https://siel.nl/acumulus/API/Entry/Get_Entry_Details/}
     *
     * @param int $entryId
     *   The id of the entry.
     *
     * @return \Siel\Acumulus\ApiClient\AcumulusResult
     *   The result of the webservice call. The structured response will contain
     *   1 "entry" array, being a keyed array with keys:
     * - 'entryid'
     * - 'entrydate'
     * - 'entrytype'
     * - 'entrydescription'
     * - 'entrynote'
     * - 'fiscaltype'
     * - 'vatreversecharge'
     * - 'foreigneu'
     * - 'foreignnoneu'
     * - 'marginscheme'
     * - 'foreignvat'
     * - 'contactid'
     * - 'accountnumber'
     * - 'costcenterid'
     * - 'costtypeid'
     * - 'invoicenumber'
     * - 'invoicenote'
     * - 'descriptiontext'
     * - 'invoicelayoutid'
     * - 'totalvalueexclvat'
     * - 'totalvalue'
     * - 'paymenttermdays'
     * - 'paymentdate'
     * - 'paymentstatus'
     * - 'deleted'
     *   Possible errors:
     *   - XGYBSN000: Requested invoice for entry $entryId not found: $entryId
     *     does not exist.
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
     */
    public function getEntry(int $entryId): AcumulusResult
    {
        $message = [
            'entryid' => $entryId,
        ];
        return $this->callApiFunction('entry/entry_info', $message)->setMainAcumulusResponseKey('entry');
    }

    /**
     * Moves the entry into or out of the trash bin.
     *
     * See {@link https://siel.nl/acumulus/API/Entry/Set_Delete_Status/}
     *
     * @param int $entryId
     *   The id of the entry.
     * @param int $deleteStatus
     *   The delete action to perform: one of the Api::Entry_Delete or
     *   Api::Entry_UnDelete constants. Api::Entry_UnDelete does not work for
     *   now.
     *
     * @return \Siel\Acumulus\ApiClient\AcumulusResult
     *   The result of the webservice call. The structured response will contain
     *   1 "entry" array, being a keyed array with keys:
     * - 'entryid'
     *   - entryproc: (description new status): 'removed', 'recovered' or 'no
     *     changes made'.
     *   Possible errors:
     *   - XCM7ELO12: Invalid entrydeletestatus value supplied: $deleteStatus
     *     is not one of the indicated constants.
     *   - XCM7ELO14: Invalid entrydeletestatus value supplied: $deleteStatus
     *     is not one of the indicated constants.
     *   - P2XFELO12: Requested for entryid: $entryId not found or forbidden:
     *     $entryId does not exist or already has requested status.
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
     */
    public function setDeleteStatus(int $entryId, int $deleteStatus): AcumulusResult
    {
        $message = [
            'entryid' => $entryId,
            'entrydeletestatus' => $deleteStatus,
        ];
        return $this->callApiFunction('entry/entry_deletestatus_set', $message)->setMainAcumulusResponseKey('entry');
    }

    /**
     * Retrieves the payment status for an invoice.
     *
     * See {@link https://www.siel.nl/acumulus/API/Invoicing/Payment_Get_Status/}
     *
     * @param string $token
     *   The token for the invoice.
     *
     * @return \Siel\Acumulus\ApiClient\AcumulusResult
     *   The result of the webservice call. The structured response will contain
     *   1 "invoice" array, being a keyed array with keys:
     * - 'entryid'
     * - 'token'
     * - 'paymentstatus'
     * - 'paymentdate'
     *   Possible errors:
     *   - XGYTTNF04: Requested invoice for $token not found: $token does not
     *     exist.
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
     *
     * @noinspection PhpUnused
     */
    public function getPaymentStatus(string $token): AcumulusResult
    {
        $message = [
            'token' => $token,
        ];
        return $this->callApiFunction('invoices/invoice_paymentstatus_get', $message)->setMainAcumulusResponseKey('invoice');
    }

    /**
     * Sets the payment status for an invoice.
     *
     * See {@link https://www.siel.nl/acumulus/API/Invoicing/Payment_Set_Status/}
     *
     * @param string $token
     *   The token for the invoice.
     * @param int $paymentStatus
     *   The new payment status, 1 of the Api::PaymentStatus_Paid or
     *   Api::PaymentStatus_Due constants.
     * @param string $paymentDate
     *   ISO date string (yyyy-mm-dd) for the date to set as payment date, may
     *   be empty for today or if the payment status is Api::PaymentStatus_Due.
     *
     * @return \Siel\Acumulus\ApiClient\AcumulusResult
     *   The result of the webservice call. The structured response will contain
     *   1 "invoice" array, being a keyed array with keys:
     * - 'entryid'
     * - 'token'
     * - 'paymentstatus'
     * - 'paymentdate'
     *   Possible errors:
     *   - DATE590ZW: Missing mandatory paymentdate field. Unable to proceed."
     *   - DATE590ZW: Incorrect date range (2000-01-01 to 2099-12-31) or invalid
     *     date format (YYYY-MM-DD) used in paymentdate field. We received:
     *     $paymentDate. Unable to proceed."
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
     */
    public function setPaymentStatus(string $token, int $paymentStatus, string $paymentDate = ''): AcumulusResult
    {
        if (empty($paymentDate)) {
            $paymentDate = date(Api::DateFormat_Iso);
        }
        $message = [
            'token' => $token,
            'paymentstatus' => $paymentStatus,
            'paymentdate' => (string) $paymentDate,
        ];
        return $this->callApiFunction('invoices/invoice_paymentstatus_set', $message)->setMainAcumulusResponseKey('invoice');
    }

    /**
     * Sends out an invoice or reminder as PDF.
     *
     * See {@link https://siel.nl/acumulus/API/Invoicing/Email/}
     *
     * @param string $token
     *   The token for the invoice.
     * @param array $emailAsPdf
     *   An array with the fields:
     * - 'emailto'
     * - 'emailbcc'
     * - 'emailfrom'
     * - 'subject'
     * - 'message'
     * - 'confirmreading'
     * @param int|null $invoiceType
     *   One of the constants Api::Email_Normal (default) or Api::Email_Reminder.
     * @param string $invoiceNotes
     *   Multiline field for additional remarks. Use \n for newlines and \t for
     *   tabs. Contents is placed in notes/comments section of the invoice.
     *   Content will not appear on the actual invoice or associated emails.
     *
     * @return \Siel\Acumulus\ApiClient\AcumulusResult
     *   The result of the webservice call. The structured response will contain
     *   1 "invoice" array, being a keyed array with keys:
     * - 'token'
     * - 'invoicetype'
     *   Possible errors/warnings:
     *   - GK6FKHU52: Incorrect invoicetype value used (9) in invoicetype tag as
     *     part of invoice section in the XML. Using default value of 0 normal."
     *   - TNFE4035G: Requested token not found or invalid token supplied.
     *     Unable to proceed."
     *
     * See {@link https://siel.nl/acumulus/API/Invoicing/Email/}
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
     *
     * @noinspection PhpUnused
     */
    public function emailInvoiceAsPdf(string $token, array $emailAsPdf, ?int $invoiceType = null, string $invoiceNotes = ''): AcumulusResult
    {
        $message = [
            'token' => $token,
            'emailaspdf' => $emailAsPdf,
        ];
        if ($invoiceType !== null) {
            $message['invoicetype'] = $invoiceType;
        }
        if (!empty($invoiceNotes)) {
            $message['invoicenotes'] = $invoiceNotes;
        }
        return $this->callApiFunction('invoices/invoice_mail', $message)->setMainAcumulusResponseKey('invoice');
    }

    /**
     * Signs up for a 30-day trial and receive credentials.
     *
     * See {@link https://www.siel.nl/acumulus/API/Sign_Up/Sign_Up/}
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
     * - 'telephone'
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
     * @return \Siel\Acumulus\ApiClient\AcumulusResult
     *   The result of the webservice call. The structured response will contain
     *   1 "signup" array, being a keyed array with keys:
     * - 'contractcode'
     * - 'contractloginname'
     * - 'contractpassword'
     * - 'contractapiuserloginname'
     * - 'contractapiuserpassword'
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
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
     */
    public function signUp(array $signUp): AcumulusResult
    {
        $message = [
            'signup' => $signUp,
        ];
        return $this->callApiFunction('signup/signup', $message, false)->setMainAcumulusResponseKey('signup');
    }

    /**
     * Updates the stock for a product.
     *
     * See {@link https://www.siel.nl/acumulus/API/Stock/Add_Stock_Transaction/}
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
     * @param string|null $date
     *   ISO date string (yyyy-mm-dd) for the date to set as update date for the
     *   stock update.
     *
     * @return \Siel\Acumulus\ApiClient\AcumulusResult
     *   The result of the webservice call. The structured response will contain
     *   1 "stock" array, being a keyed array with keys:
     * - 'productid'
     *   - stockamount (the new stock level for this product)
     *   Possible errors:
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
     */
    public function stockAdd(int $productId, float $quantity, string $description, string $date = null): AcumulusResult
    {
        if (empty($date)) {
            $date = date(Api::DateFormat_Iso);
        }
        $message = [
            'stock' => [
                'productid' => $productId,
                'stockamount' => $quantity,
                'stockdescription' => $description,
                'stockdate' => $date,
            ]
        ];
        return $this->callApiFunction('stock/stock_add', $message)->setMainAcumulusResponseKey('stock');
    }

    /**
     * Returns the uri to download the invoice PDF.
     *
     * See {@link https://siel.nl/acumulus/API/Invoicing/Get_PDF_Invoice/}
     *
     * @param string $token
     *   The token for the invoice.
     * @param ?bool $reminder
     *   False, null or absent to retrieve the normal invoice, true to retrieve
     *   a reminder invoice.
     * @param ?bool $applyGraphics
     *   False to prevent any embedded graphics from being applied to the
     *   document; true, null, or absent otherwise.
     *
     * @return string
     *   The uri to download the invoice PDF.
     *   Possible errors (in download from the retunned uri, not in this
     *   method's return value):
     *   - PDFATNF04: Requested invoice for $token not found: $token does not
     *     exist. @todo: check code tag.
     */
    public function getInvoicePdfUri(string $token, ?bool $reminder = null, ?bool $applyGraphics = null): string
    {
        $uri = $this->constructUri('invoices/invoice_get_pdf');
        $uri .= "?token=$token";
        if ($reminder !== null) {
            $uri .= '&invoicetype=' . ($reminder ? '1' : '0');
        }
        if ($applyGraphics !== null) {
            $uri .= '&gfx=' . ($applyGraphics ? '1' : '0');
        }
        return $uri;
    }

    /**
     * Returns the uri to download the packing slip PDF.
     *
     * See {@link https://siel.nl/acumulus/API/Delivery/Get_PDF_Packing_Slip/}
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
    public function getPackingSlipUri(string $token): string
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
    protected function constructUri(string $apiFunction): string
    {
        $environment = $this->environment->get();
        return $environment['baseUri'] . '/' . $environment['apiVersion'] . '/' . $apiFunction . '.php';
    }

    /**
     * Wrapper around
     * {@see \Siel\Acumulus\ApiClient\AcumulusRequest::execute()}.
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
     *
     * @return \Siel\Acumulus\ApiClient\AcumulusResult
     *   An AcumulusResult object containing the results.
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
     *   A communication level erorr occurred.
     *   - AcumulusRequest will be set;
     *   - HttpRequest will probably also be set;
     *   - HttpResponse might be set or not;
     *   - AcumulusResult will not be set.
     */
    protected function callApiFunction(string $apiFunction, array $message, bool $needContract = true): AcumulusResult
    {
        $acumulusRequest = $this->createAcumulusRequest();
        $uri = $this->constructUri($apiFunction);
        try {
            $acumulusResult = $acumulusRequest->execute($uri, $message, $needContract);
            $logLevel = $acumulusResult->getStatus();
            return $acumulusResult;
        } catch (AcumulusException $e) {
            // Situation 1 or 2. We will log the situation and rethrow.
            $exception = $e;
            throw $e;
        } finally {
            $logLevel = $logLevel ?? Severity::Exception;
            $this->log->log($logLevel, $acumulusRequest->getMaskedRequest());
            if (isset($acumulusResult)) {
                $this->log->log($logLevel, $acumulusResult->getMaskedResponse());
            }
            if (isset($exception)) {
                $this->log->exception($e);
            }
        }
    }

    /**
     * Wrapper around the factory method that creates an AcumulusRequest.
     *
     * @return \Siel\Acumulus\ApiClient\AcumulusRequest
     */
    protected function createAcumulusRequest(): AcumulusRequest
    {
        return $this->container->createAcumulusRequest();
    }
}
