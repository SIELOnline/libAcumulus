<?php
namespace Siel\Acumulus\Web;

use Siel\Acumulus\Helpers\TranslatorInterface;

/**
 * Provides an easy interface towards the different API calls of the Acumulus
 * web API.
 *
 * This class simplifies the communication so that the different web shop
 * specific interfaces can be more rapidly developed.
 *
 * More info:
 * - https://apidoc.sielsystems.nl/
 * - http://www.siel.nl/webkoppelingen/
 *
 * The Web API call wrappers return their information as a keyed array, which is
 * a simplified version of the call specific response structure and the exit
 * structure as described on
 * https://apidoc.sielsystems.nl/content/warning-error-and-status-response-section-most-api-calls.
 *
 * The general part is represented by the following keys in the result:
 * - status: int; 0 = success; 1 = Failed, Errors found; 2 = Success with
 *   warnings; 3 = Exception, Please contact Acumulus technical support.
 * - errors: an array of errors, an error is an array with the following keys:
 *   - code: int, see https://apidoc.sielsystems.nl/content/exit-and-warning-codes
 *   - codetag: string, a special code tag. Use this as a reference when
 *        communicating with Acumulus technical support.
 *   - message: string, a message describing the warning or error.
 * - warnings: an array of warning arrays, these have the same keys as an error.
 */
class Service
{
    /** @var \Siel\Acumulus\Web\ConfigInterface */
    protected $config;

    /** @var \Siel\Acumulus\Helpers\TranslatorInterface */
    protected $translator;

    /** @var \Siel\Acumulus\Web\Communicator */
    protected $communicator;

    /**
     * Constructor.
     *
     * @param \Siel\Acumulus\Web\ConfigInterface $config
     * @param \Siel\Acumulus\Helpers\TranslatorInterface $translator
     */
    public function __construct(ConfigInterface $config, TranslatorInterface $translator)
    {
        $this->config = $config;
        $this->communicator = null;

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
     * Lazy loads the communicator.
     *
     * @return Communicator
     */
    protected function getCommunicator()
    {
        if ($this->communicator === null) {
            if ($this->config->getDebug() == 4 /*ConfigInterface::Debug_StayLocal*/) {
                $this->communicator = new CommunicatorLocal($this->config);
            } else {
                $this->communicator = new Communicator($this->config);
            }
        }
        return $this->communicator;
    }

    /**
     * If the result contains any errors or warnings, a list of verbose messages
     * is returned.
     *
     * @param array $result
     *   A keyed array that contains the results, but also any messages in the
     *   keys 'errors and 'warnings'.
     * @param bool $addTraceMessages
     *   $result may contain the actual request and response as sent in the key
     *   'trace'. If this parameter is true the trace should be added to the
     *   messages to be returned as well.
     *
     * @return string[]
     *   An array with textual messages that can be used to inform the user.
     */
    public function resultToMessages(array $result, $addTraceMessages = true)
    {
        $messages = array();
        foreach ($result['errors'] as $error) {
            $message = "{$error['code']}: ";
            $message .= $this->t($error['message']);
            if ($error['codetag']) {
                $message .= " ({$error['codetag']})";
            }
            $messages[] = $this->t('message_error') . ' ' . $message;
        }
        foreach ($result['warnings'] as $warning) {
            $message = "{$warning['code']}: ";
            $message .= $this->t($warning['message']);
            if ($warning['codetag']) {
                $message .= " ({$warning['codetag']})";
            }
            $messages[] = $this->t('message_warning') . ' ' . $message;
        }

        if ($addTraceMessages && (!empty($messages) || $this->config->getDebug() != ConfigInterface::Debug_None)) {
            if (isset($result['trace'])) {
                $messages[] = $this->t('message_info_for_user');
                if (isset($result['trace']['request'])) {
                    $messages[] = $this->t('message_sent') . ":\n" . $result['trace']['request'];
                }
                if (isset($result['trace']['response'])) {
                    $messages[] = $this->t('message_received') . ":\n" . $result['trace']['response'];
                }
            }
        }

        return $messages;
    }

    /**
     * Converts an array of messages to a string that can be used in a text mail.
     *
     * @param string[] $messages
     *
     * @return string
     */
    public function messagesToText(array $messages)
    {
        return !empty($messages) ? '* ' . implode("\n\n* ", $messages) . "\n\n" : '';
    }

    /**
     * Converts an array of messages to a string that can be used in an html mail.
     *
     * @param string[] $messages
     *
     * @return string
     */
    public function messagesToHtml(array $messages)
    {
        $messages_html = array();
        foreach ($messages as $message) {
            $messages_html[] = nl2br(htmlspecialchars($message, ENT_NOQUOTES));
        }
        return '<ul><li>' . implode("</li><li>", $messages_html) . '</li></ul>';
    }

    /**
     * @param int $status
     *
     * @return string
     */
    public function getStatusText($status)
    {
        switch ($status) {
            case ConfigInterface::Status_Success:
                return $this->t('message_response_0');
            case ConfigInterface::Status_Errors:
                return $this->t('message_response_1');
            case ConfigInterface::Status_Warnings:
                return $this->t('message_response_2');
            case ConfigInterface::Status_Exception:
                return $this->t('message_response_3');
            default:
                return $this->t('message_response_x') . $status;
        }
    }

    /**
     * Retrieves a list of accounts.
     *
     * @return array
     *   Besides the general response structure, the actual result of this call is
     *   returned under the key 'accounts' and consists of an array of 'accounts',
     *   each 'account' being a keyed array with keys:
     *   - accountid
     *   - accountnumber
     *   - accountdescription
     *
     * See https://apidoc.sielsystems.nl/content/picklist-accounts-bankrekeningen.
     */
    public function getPicklistAccounts()
    {
        return $this->getPicklist('account');
    }

    /**
     * Retrieves a list of contact types.
     *
     * @return array
     *   Besides the general response structure, the actual result of this call is
     *   returned under the key 'contacttypes' and consists of an array of
     *   'contacttypes', each 'contacttype' being a keyed array with keys:
     *   - contacttypeid
     *   - contacttypename
     *
     * See https://apidoc.sielsystems.nl/content/picklist-contacttypes-contactsoorten.
     */
    public function getPicklistContactTypes()
    {
        return $this->getPicklist('contacttype');
    }

    /**
     * Retrieves a list of cost centers.
     *
     * @return array
     *   Besides the general response structure, the actual result of this call is
     *   returned under the key 'costcenters' and consists of an array of
     *   'costcenters', each 'costcenter' being a keyed array with keys:
     *   - costcenterid
     *   - costcentername
     *
     * See https://apidoc.sielsystems.nl/content/picklist-costcenters-kostenplaatsen.
     */
    public function getPicklistCostCenters()
    {
        return $this->getPicklist('costcenter');
    }

    /**
     * Retrieves a list of cost headings.
     *
     * @return array
     *   Besides the general response structure, the actual result of this call is
     *   returned under the key 'costheadings' and consists of an array of
     *   'costheadings', each 'costheading' being a keyed array with keys:
     *   - costheadingid
     *   - costheadingname
     *
     * See https://apidoc.sielsystems.nl/content/picklist-costheadings-kostensoorten.
     */
    public function getPicklistCostHeadings()
    {
        return $this->getPicklist('costheading');
    }

    /**
     * Retrieves a list of cost types.
     *
     * @return array
     *   Besides the general response structure, the actual result of this call is
     *   returned under the key 'invoicetemplates' and consists of an array of
     *   'invoicetemplates', each 'invoicetemplate' being a keyed array with keys:
     *   - invoicetemplateid
     *   - invoicetemplatename
     *
     * See https://apidoc.sielsystems.nl/content/picklist-invoice-templates-factuursjablonen.
     */
    public function getPicklistInvoiceTemplates()
    {
        return $this->getPicklist('invoicetemplate');
    }

    /**
     * Retrieves a list of VAT types.
     *
     * @return array
     *   Besides the general response structure, the actual result of this call is
     *   returned under the key 'vattypes' and consists of an array of 'vattypes',
     *   each 'vattype' being a keyed array with keys:
     *   - 'vattypeid'
     *   - 'vattypename'
     *
     * See https://apidoc.sielsystems.nl/content/picklist-vattypes-btw-groepen.
     */
    public function getPicklistVatTypes()
    {
        return $this->getPicklist('vattype');
    }

    /**
     * A helper method to retrieve a given picklist.
     *
     * The Acumulus API for picklists is so well standardized, that it is possible
     * to use 1 general picklist retrieval function that can process all picklist
     * types.
     *
     * @param string $picklist
     *   The picklist to retrieve, specify in singular form: account, contacttype,
     *   costcenter, etc.
     *
     * @return array
     *   Besides the general response structure, the actual result of this call is
     *   returned under the key $picklist in plural format (with an 's' attached)
     *   and consists of an array of keyed arrays, each keyed array being 1 result
     *   of the requested picklist.
     */
    protected function getPicklist($picklist)
    {
        $plural = $picklist . 's';
        $response = $this->getCommunicator()->callApiFunction("picklists/picklist_$plural", array());
        // Simplify result: remove indirection.
        if (!empty($response[$plural][$picklist])) {
            $response[$plural] = $response[$plural][$picklist];
            // If there was only 1 result, it wasn't put in an array.
            if (!is_array(reset($response[$plural]))) {
                $response[$plural] = array($response[$plural]);
            }
        } else {
            $response[$plural] = array();
        }
        return $response;
    }

    /**
     * Retrieves a list of VAT rates for the given country at the given date.
     *
     * @param string $countryCode
     *   Country code of the country to retrieve the VAT info for.
     * @param string $date
     *   ISO date string (yyyy-mm-dd) for the date to retrieve the VAT info for.
     *
     * @return array
     *   Besides the general response structure, the actual result of this call is
     *   returned under the key 'vatinfo' and consists of an array of "vatinfo's",
     *   each 'vatinfo' being a keyed array with keys:
     *   - vattype
     *   - vatrate
     *
     * See https://apidoc.sielsystems.nl/content/lookup-vatinfo-btw-informatie.
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
        $response = $this->getCommunicator()->callApiFunction("lookups/lookup_vatinfo", $message);
        // Simplify result: remove indirection.
        if (!empty($response['vatinfo']['vat'])) {
            $response['vatinfo'] = $response['vatinfo']['vat'];
            // If there was only 1 result, it wasn't put in an array.
            if (!is_array(reset($response['vatinfo']))) {
                $response['vatinfo'] = array($response['vatinfo']);
            }
        } else {
            $response['vatinfo'] = array();
        }
        return $response;
    }

    /**
     * Sends an invoice to Acumulus.
     *
     * @param array $invoice
     *   The invoice to send.
     *
     * @return array
     *   Besides the general response structure, the actual result of this call is
     *   returned under the following key:
     *   - invoice: an array of information about the created invoice, being an
     *     array with keys:
     *     - invoicenumber
     *     - token
     *     - entryid
     *   If the key invoice is present, it indicates success.
     *
     * See https://apidoc.sielsystems.nl/content/invoice-add.
     * See https://apidoc.sielsystems.nl/content/warning-error-and-status-response-section-most-api-calls
     * for more information on the contents of the returned array.
     */
    public function invoiceAdd(array $invoice)
    {
        return $this->getCommunicator()->callApiFunction("invoices/invoice_add", $invoice);
    }

    /**
     * Merges any local messages into the result structure and adapts the status
     * to correctly reflect local warnings and errors as well.
     *
     * @param array $result
     * @param array $localMessages
     *
     * @return array
     */
    public function mergeLocalMessages(array $result, array $localMessages)
    {
        if (!empty($localMessages['errors'])) {
            $result['errors'] = array_merge($result['errors'], $localMessages['errors']);
        }
        if (!empty($localMessages['warnings'])) {
            $result['warnings'] = array_merge($result['warnings'], $localMessages['warnings']);
        }
        $this->getCommunicator()->correctStatusForLocalMessages($result);
        return $result;
    }
}
