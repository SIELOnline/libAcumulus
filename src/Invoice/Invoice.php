<?php
namespace Siel\Acumulus\Invoice;

/**
 * Class Invoice wraps an Acumulus invoice (array) and any accompanying data.
 *
 * An Invoice object will contain the invoice in the format as expected by the
 * invoice-add Acumulus web service call, the send status, result status, and
 * any error and warning messages.
 */
class Invoice
{
    /**
     * The invoice in Acumulus format.
     *
     * @see https://www.siel.nl/acumulus/API/Invoicing/Add_Invoice/
     *
     * @var array
     */
    protected $invoice;

    /**
     * A status indicating why this invoice will or will not be sent.
     *
     * @var int
     */
    protected $sendStatus;

    /**
     * The status as returned by the web service.
     *
     * @see https://www.siel.nl/acumulus/API/Basic_Response/
     *
     * @var int
     */
    protected $resultStatus;

    /**
     * A - possibly empty - list of error messages.
     *
     * @var array[]
     */
    protected $errors;

    /**
     * A - possibly empty - list of warning messages.
     *
     * @var array[]
     */
    protected $warnings;

    /**
     * Invoice constructor.
     */
    public function __construct()
    {
        $this->invoice = array();
        $this->sendStatus = null;
        $this->resultStatus = null;
        $this->errors = array();
        $this->warnings = array();
    }

    /**
     * @param array $invoice
     */
    public function setInvoice($invoice)
    {
        $this->invoice = $invoice;
    }

    /**
     * Returns the invoice as a reference, so it can be modified.
     *
     * @return array
     */
    public function &getInvoice()
    {
        return $this->invoice;
    }

    /**
     * @param int $sendStatus
     */
    public function setSendStatus($sendStatus)
    {
        $this->sendStatus = $sendStatus;
    }

    /**
     * Returns a status indicating why this invoice will or will not be sent.
     *
     * @return int
     */
    public function getSendStatus()
    {
        return $this->sendStatus;
    }

    /**
     * Returns the status as returned by the web service.
     *
     * @param int $resultStatus
     */
    public function setResultStatus($resultStatus)
    {
        $this->resultStatus = $resultStatus;
    }

    /**
     * @return int
     */
    public function getResultStatus()
    {
        return $this->resultStatus;
    }

    /**
     * Adds multiple errors to the list of errors.
     *
     * @param array[] $errors
     */
    public function addErrors(array $errors)
    {
        $this->errors[] =  array_merge($this->errors, $errors);
    }

    /**
     * Adds an error to the list of errors.
     *
     * @param int $code
     *   Error/warning code number. Usually of type 4xx, 5xx or 6xx.
     * @param string $codeTag
     *   Special code tag. Use this as a reference when communicating with
     *   Acumulus technical support.
     * @param string $message
     *   A message describing the warning or error.
     */
    public function addError($code, $codeTag, $message)
    {
        $this->errors[] = array(
            'code' => $code,
            'codetag' =>$codeTag,
            'message' => $message
        );
    }

    /**
     * Returns a - possibly empty - list of error messages.
     *
     * @return array[]
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Adds multiple warnings to the list of warnings.
     *
     * @param array[] $warnings
     */
    public function addWarnings(array $warnings)
    {
        $this->warnings = array_merge($this->warnings, $warnings);
    }

    /**
     * Adds a warning to the list of warnings.
     *
     * @param int $code
     *   Error/warning code number. Usually of type 4xx, 5xx or 6xx.
     * @param string $codeTag
     *   Special code tag. Use this as a reference when communicating with
     *   Acumulus technical support.
     * @param string $message
     *   A message describing the warning or error.
     */
    public function addWarning($code, $codeTag, $message)
    {
        $this->warnings[] = array(
            'code' => $code,
            'codetag' =>$codeTag,
            'message' => $message
        );
    }

    /**
     * Returns a - possibly empty - list of warning messages.
     *
     * @return array[]
     */
    public function getWarnings()
    {
        return $this->warnings;
    }
}
