<?php
namespace Siel\Acumulus\ApiClient;

use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Helpers\Translator;

/**
 * Provides an easy interface towards the different API calls of the Acumulus
 * web API.
 *
 * This class simplifies the communication so that the different web shop
 * specific interfaces can be more rapidly developed.
 *
 * More info:
 * - https://www.siel.nl/acumulus/API/
 * - http://www.siel.nl/acumulus/koppelingen/
 *
 * The ApiClient API call wrappers return their information as a keyed array, which is
 * a simplified version of the call specific part of the response structure.
 */
class Acumulus
{
    /** @var \Siel\Acumulus\Config\Config */
    protected $config;

    /** @var \Siel\Acumulus\Helpers\Translator */
    protected $translator;

    /** @var ApiCommunicator */
    protected $apiCommunicator;

    /**
     * Constructor.
     *
     * @param ApiCommunicator $apiCommunicator
     * @param \Siel\Acumulus\Config\Config $config
     * @param \Siel\Acumulus\Helpers\Translator $translator
     */
    public function __construct(ApiCommunicator $apiCommunicator, Config $config, Translator $translator)
    {
        $this->config = $config;
        $this->apiCommunicator = $apiCommunicator;
        $this->translator = $translator;
        $this->translator->add(new Translations());
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
     * Retrieve the about information.
     *
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
     *
     * @see https://www.siel.nl/acumulus/API/Misc/About/
     *   for more information about the contents of the returned array.
     */
    public function getAbout()
    {
        return $this->apiCommunicator->callApiFunction('general/general_about', array())->setMainResponseKey('general', false);
    }

    /**
     * Retrieves a list of accounts.
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the webservice call. The structured response will contain
     *   a non-keyed array of "account" arrays, each "account" array being a
     *   keyed array with keys:
     *   - accountid
     *   - accountnumber
     *   - accountdescription
     *
     * @see https://www.siel.nl/acumulus/API/Accounts/List_Accounts/
     */
    public function getPicklistAccounts()
    {
        return $this->getPicklist('accounts');
    }

    /**
     * Retrieves a list of contact types.
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the webservice call. The structured response will contain
     *   a non-keyed array of "contacttype" arrays, each "contacttype" array
     *   being a keyed array with keys:
     *   - contacttypeid
     *   - contacttypename
     *
     * @see https://www.siel.nl/acumulus/API/Picklists/Contact_Types/
     */
    public function getPicklistContactTypes()
    {
        return $this->getPicklist('contacttypes');
    }

    /**
     * Retrieves a list of cost centers.
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the webservice call. The structured response will contain
     *   a non-keyed array of "costcenter" arrays, each "costcenter" array being
     *   a keyed array with keys:
     *   - costcenterid
     *   - costcentername
     *
     * @see https://www.siel.nl/acumulus/API/Picklists/Cost_Centers/
     */
    public function getPicklistCostCenters()
    {
        return $this->getPicklist('costcenters');
    }

    /**
     * Retrieves a list of cost headings.
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the webservice call. The structured response will contain
     *   a non-keyed array of "costheading" arrays, each "costheading" array being
     *   a keyed array with keys:
     *   - costheadingid
     *   - costheadingname
     *
     * @see https://www.siel.nl/acumulus/API/Picklists/Cost_Headings/
     *
     * @noinspection PhpUnused
     */
    public function getPicklistCostHeadings()
    {
        return $this->getPicklist('costheadings');
    }

    /**
     * Retrieves a list of invoice templates.
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the webservice call. The structured response will contain
     *   a non-keyed array of "invoicetemplate" arrays, each "invoicetemplate"
     *   array being a keyed array with keys:
     *   - invoicetemplateid
     *   - invoicetemplatename
     *
     * @see https://www.siel.nl/acumulus/API/Invoicing/Invoice_Templates/
     */
    public function getPicklistInvoiceTemplates()
    {
        return $this->getPicklist('invoicetemplates');
    }

    /**
     * Retrieves a list of VAT types.
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the webservice call. The structured response will contain
     *   a non-keyed array of "vattype" arrays, each "vattype" array being a
     *   keyed array with keys:
     *   - 'vattypeid'
     *   - 'vattypename'
     *
     * @see https://www.siel.nl/acumulus/API/Picklists/VAT_Types/
     *
     * @noinspection PhpUnused
     */
    public function getPicklistVatTypes()
    {
        return $this->getPicklist('vattypes');
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
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the webservice call. The structured response will contain
     *   a non-keyed array of "picklist" arrays, each 'picklist' array being a
     *   keyed array with keys that depend on the requested picklist.
     */
    protected function getPicklist($picklist)
    {
        // For picklists, the main result is found under the name of the
        // picklist but in singular form, i.e. without the s at the end.
        return $this->apiCommunicator->callApiFunction("picklists/picklist_$picklist", array())->setMainResponseKey($picklist, true);
    }

    /**
     * Retrieves a list of VAT rates for the given country at the given date.
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
     *
     * @see https://www.siel.nl/acumulus/API/Picklists/VAT_Info/
     *   for more information about the contents of the returned array.
     */
    public function getVatInfo($countryCode, $date = '')
    {
        if (empty($date)) {
            $date = date(API::DateFormat_Iso);
        }
        $message = array(
            'vatdate' => $date,
            'vatcountry' => $countryCode,
        );
        return $this->apiCommunicator->callApiFunction('lookups/lookup_vatinfo', $message, true)->setMainResponseKey('vatinfo', true);
    }

    /**
     * Sends an invoice to Acumulus.
     *
     * @param array $invoice
     *   The invoice to send.
     * @param \Siel\Acumulus\ApiClient\Result|null $result
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
     *
     * @see https://www.siel.nl/acumulus/API/Invoicing/Add_Invoice/
     */
    public function invoiceAdd(array $invoice, Result $result = null)
    {
        return $this->apiCommunicator->callApiFunction('invoices/invoice_add', $invoice, true, $result)->setMainResponseKey('invoice');
    }

    /**
     * Retrieves information about a concept.
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
     *   - FGYBSN040: Requested invoice for concept $concepId not found: No
     *     definitive invoice has yet been created for this concept.
     *   - FGYBSN048: Information not available for $conceptId older then 127466.
     *   -
     *
     * @see https://www.siel.nl/acumulus/API/Invoicing/Concept_Info/
     */
    public function getConceptInfo($conceptId)
    {
        $message = array(
            'conceptid' => (int) $conceptId,
        );
        return $this->apiCommunicator->callApiFunction('invoices/invoice_concept_info', $message)->setMainResponseKey('concept');
    }

    /**
     * Retrieves Entry (Boeking) Details.
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
     *   - XGYBSN000: Requested invoice for entry $entryId not found": $entryId
     *     does not exist.
     *
     * @see https://siel.nl/acumulus/API/Entry/Get_Entry_Details/
     */
    public function getEntry($entryId)
    {
        $message = array(
            'entryid' => (int) $entryId,
        );
        return $this->apiCommunicator->callApiFunction('entry/entry_info', $message)->setMainResponseKey('entry');
    }

    /**
     * Moves the entry into or out of the trashbin.
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
     *   - entryproc: (description new status): 'removed' or '???'
     *   Possible errors:
     *   - XCM7ELO12: Invalid entrydeletestatus value supplied": $deleteStatus
     *     is not one of the indicated constants.
     *   - XCM7ELO14: Invalid entrydeletestatus value supplied": $deleteStatus
     *     is not one of the indicated constants.
     *   - P2XFELO12: Requested for entryid: $entryId not found or forbidden":
     *     $entryId does not exist or already has requested status.
     *
     * @see https://siel.nl/acumulus/API/Entry/Set_Delete_Status/
     */
    public function setDeleteStatus($entryId, $deleteStatus)
    {
        $message = array(
            'entryid' => (int) $entryId,
            'entrydeletestatus' => (int) $deleteStatus,
        );
        return $this->apiCommunicator->callApiFunction('entry/entry_deletestatus_set', $message)->setMainResponseKey('entry');
    }

    /**
     * Retrieves the payment status for an invoice.
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
     *   - XGYTTNF04: Requested invoice for $token not found": $token does not
     *     exist.
     *
     * @see https://www.siel.nl/acumulus/API/Invoicing/Payment_Get_Status/
     *
     * @noinspection PhpUnused
     */
    public function getPaymentStatus($token)
    {
        $message = array(
            'token' => (string) $token,
        );
        return $this->apiCommunicator->callApiFunction('invoices/invoice_paymentstatus_get', $message)->setMainResponseKey('invoice');
    }

    /**
     * Sets the payment status for an invoice.
     *
     * @param string $token
     *   The token for the invoice.
     * @param int $paymentStatus
     *   The new payment status, 1 of the API::PaymentStatus_Paid or
     *   API::PaymentStatus_Due constants.
     * @param string $paymentDate
     *   ISO date string (yyyy-mm-dd) for the date to set as payment date, may
     *   be empty for today or if the payment sattus is API::PaymentStatus_Due.
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
     *
     * @see https://www.siel.nl/acumulus/API/Invoicing/Payment_Set_Status/
     */
    public function setPaymentStatus($token, $paymentStatus, $paymentDate = '')
    {
        if (empty($paymentDate)) {
            $paymentDate = date(API::DateFormat_Iso);
        }
        $message = array(
            'token' => (string) $token,
            'paymentstatus' => (int) $paymentStatus,
            'paymentdate' => (string) $paymentDate,
        );
        return $this->apiCommunicator->callApiFunction('invoices/invoice_paymentstatus_set', $message)->setMainResponseKey('invoice');
    }

    /**
     * Sends out an invoice or reminder as PDF.
     *
     * @param string $token
     *   The token for the invoice.
     * @param int $invoiceType
     *   One of the constants API::Email_Normal or API::Email_Reminder.
     * @param array $emailAsPdf
     *   An array with the fields:
     *   - emailto
     *   - emailbcc
     *   - emailfrom
     *   - subject
     *   - message
     *   - confirmreading
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
     * @see https://siel.nl/acumulus/API/Invoicing/Email/
     *
     * @noinspection PhpUnused
     */
    public function emailInvoiceAsPdf($token, $invoiceType, array $emailAsPdf, $invoiceNotes = '')
    {
        $message = array(
            'token' => (string) $token,
            'invoicetype' => (int) $invoiceType,
            'emailaspdf' => $emailAsPdf,
        );
        if (!empty($invoiceNotes)) {
            $message['invoicenotes'] = (string) $invoiceNotes;
        }
        return $this->apiCommunicator->callApiFunction('invoices/invoice_mail', $message)->setMainResponseKey('invoice');
    }

    /**
     * Returns the uri to download the invoice PDF.
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
     *   - PDFATNF04: Requested invoice for $token not found": $token does not
     *     exist.
     *
     * @see https://siel.nl/acumulus/API/Invoicing/Get_PDF_Invoice/
     */
    public function getInvoicePdfUri($token, $applyGraphics = true)
    {
        $uri = $this->apiCommunicator->getUri('invoices/invoice_get_pdf');
        $uri .= "?token=$token";
        if (!$applyGraphics) {
            $uri .= '&gfx=0';
        }
        return $uri;
    }

    /**
     * Returns the uri to download the packing slip PDF.
     *
     * @param string $token
     *   The token for the invoice to get the packing slip for.
     *
     * @return string
     *   The uri to download the packing slip PDF.
     *   Possible errors (in download, not in return value):
     *   - ZKFATNF04: Requested packing slip for $token not found or no longer
     *     available."
     *
     * @see https://siel.nl/acumulus/API/Delivery/Get_PDF_Packing_Slip/
     */
    public function getPackingSlipUri($token)
    {
        $uri = $this->apiCommunicator->getUri('delivery/packing_slip_get_pdf');
        $uri .= "?token=$token";
        return $uri;
    }

    /**
     * Signs up for a 30 day trial and receive credentials.
     *
     * @param array $signup
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
     *   - notes Notes or remarks which you would like to be part of the sign up
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
     *
     * @see https://www.siel.nl/acumulus/API/Sign_Up/Sign_Up/
     *
     * @noinspection PhpUnused
     */
    public function signUp(array $signup)
    {
        $message = array(
            'signup' => $signup,
        );
        return $this->apiCommunicator->callApiFunction('signup/signup.php', $message, false)->setMainResponseKey('signup');
    }
}
