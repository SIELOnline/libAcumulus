<?php
namespace Siel\Acumulus\Web;

use Siel\Acumulus\Config\ConfigInterface;
use Siel\Acumulus\Helpers\TranslatorInterface;

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
 * The Web API call wrappers return their information as a keyed array, which is
 * a simplified version of the call specific part of the response structure.
 */
class Service
{
    /** @var \Siel\Acumulus\Config\ConfigInterface */
    protected $config;

    /** @var \Siel\Acumulus\Helpers\TranslatorInterface */
    protected $translator;

    /** @var CommunicatorInterface */
    protected $communicator;

    /**
     * Constructor.
     *
     * @param CommunicatorInterface $communicator
     * @param \Siel\Acumulus\Config\ConfigInterface $config
     * @param \Siel\Acumulus\Helpers\TranslatorInterface $translator
     */
    public function __construct(CommunicatorInterface $communicator, ConfigInterface $config, TranslatorInterface $translator)
    {
        $this->config = $config;
        $this->communicator = $communicator;

        $this->translator = $translator;
        $webServiceTranslations = new Translations();
        $this->translator->add($webServiceTranslations);
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
     * Retrieves a list of accounts.
     *
     * @return \Siel\Acumulus\Web\Result
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
     * @return \Siel\Acumulus\Web\Result
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
     * @return \Siel\Acumulus\Web\Result
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
     * @return \Siel\Acumulus\Web\Result
     *   The result of the webservice call. The structured response will contain
     *   a non-keyed array of "costheading" arrays, each "costheading" array being
     *   a keyed array with keys:
     *   - costheadingid
     *   - costheadingname
     *
     * @see https://www.siel.nl/acumulus/API/Picklists/Cost_Headings/
     */
    public function getPicklistCostHeadings()
    {
        return $this->getPicklist('costheadings');
    }

    /**
     * Retrieves a list of invoice templates.
     *
     * @return \Siel\Acumulus\Web\Result
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
     * @return \Siel\Acumulus\Web\Result
     *   The result of the webservice call. The structured response will contain
     *   a non-keyed array of "vattype" arrays, each "vattype" array being a
     *   keyed array with keys:
     *   - 'vattypeid'
     *   - 'vattypename'
     *
     * @see https://www.siel.nl/acumulus/API/Picklists/VAT_Types/
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
     * @return \Siel\Acumulus\Web\Result
     *   The result of the webservice call. The structured response will contain
     *   a non-keyed array of "picklist" arrays, each 'picklist' array being a
     *   keyed array with keys that depend on the requested picklist.
     */
    protected function getPicklist($picklist)
    {
        // For picklists, the main result is found under the name of the
        // picklist but in singular form, i.e. without the s at the end.
        return $this->communicator->callApiFunction("picklists/picklist_$picklist", array())->setMainResponseKey($picklist, true);
    }

    /**
     * Retrieves a list of VAT rates for the given country at the given date.
     *
     * @param string $countryCode
     *   Country code of the country to retrieve the VAT info for.
     * @param string $date
     *   ISO date string (yyyy-mm-dd) for the date to retrieve the VAT info for.
     *
     * @return \Siel\Acumulus\Web\Result
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
            $date = date('Y-m-d');
        }
        $message = array(
            'vatdate' => $date,
            'vatcountry' => $countryCode,
        );
        return $this->communicator->callApiFunction("lookups/lookup_vatinfo", $message)->setMainResponseKey('vatinfo', true);
    }

    /**
     * Sends an invoice to Acumulus.
     *
     * @param array $invoice
     *   The invoice to send.
     * @param \Siel\Acumulus\Web\Result|null $result
     *   It is possible to already create a Result object before calling the Web
     *   Service to store local messages. By passing this Result object these
     *   local messages will be merged with any remote messages in the returned
     *   Result object.
     *
     * @return \Siel\Acumulus\Web\Result
     * The Result of the webservice call. A successful call will contain a
     * response array with key:
     * - invoice: an array of information about the created invoice, being an
     *   array with keys:
     *   - invoicenumber
     *   - token
     *   - entryid
     *
     * @see https://www.siel.nl/acumulus/API/Invoicing/Add_Invoice/ for more
     *   information about the contents of the returned array.
     */
    public function invoiceAdd(array $invoice, Result $result = null)
    {
        return $this->communicator->callApiFunction("invoices/invoice_add", $invoice, $result)->setMainResponseKey('invoice');
    }

    /**
     * Moves the entry in, or out the trashbin.
     *
     * @param int $entryId
     *   The id of the entry.
     * @param int $deleteStatus
     *   The delete action to perform: 0 to undelete, 1 to delete.
     *
     * @return \Siel\Acumulus\Web\Result
     *   The result of the webservice call. The structured response will contain
     *   1 "entry" array, being a keyed array with keys:
     *   - entryid
     *   - entryproc: new delete status)
     *
     * @see https://siel.nl/acumulus/API/Entry/Set_Delete_Status/
     *   for more information about the contents of the returned array.
     */
    public function setDeleteStatus($entryId, $deleteStatus)
    {
        $message = array(
            'entryid' => (int) $entryId,
            'entrydeletestatus' => (int) $deleteStatus,
        );
        return $this->communicator->callApiFunction("entry/entry_deletestatus_set", $message)->setMainResponseKey('entry');
    }
}
