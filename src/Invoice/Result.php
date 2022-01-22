<?php
namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\ApiClient\Result as WebResult;
use Siel\Acumulus\Helpers\Severity;

/**
 * Extends Result with properties and features specific to the InvoiceAdd web
 * service call.
 */
class Result extends WebResult
{
    // Whether to add the raw request and response to mails or log messages.
    const AddReqResp_Never = 1;
    const AddReqResp_Always = 2;
    const AddReqResp_WithOther = 3;

    const SendStatus_Unknown = 0;
    // Invoice send handling related constants.
    // Reason for not sending
    const NotSent_EventInvoiceCreated = 0x1;
    const NotSent_EventInvoiceCompleted = 0x2;
    const NotSent_AlreadySent = 0x3;
    const NotSent_WrongStatus = 0x4;
    const NotSent_EmptyInvoice = 0x5;
    const NotSent_TriggerInvoiceCreateNotEnabled = 0x6;
    const NotSent_TriggerInvoiceSentNotEnabled = 0x7;
    const NotSent_LocalErrors = 0x8;
    const NotSent_DryRun = 0x9;
    const NotSent_TriggerCreditNoteEventNotEnabled = 0xa;
    const NotSent_LockedForSending = 0xb;
    const NotSent_Mask = 0xf;
    // Reason for sending
    const Send_New = 0x10;
    const Send_Forced = 0x20;
    const Send_TestMode = 0x30;
    const Send_LockExpired = 0x40;
    const Send_Mask = 0xf0;

    /**
     * @var int
     *   A status indicating if and why an invoice was or was not sent. It will
     *   contain 1 of the Result::Sent_... or Result::Invoice_NotSent_...
     *   constants.
     */
    protected $sendStatus;

    /**
     * @var array
     *   A list of parameters to use when getting the send status as text.
     */
    protected $sendStatusArguments;

    /**
     * @var string
     *   A string indicating the function that triggered the sending, e.g.
     *   InvoiceManager::sourceStatusChange().
     */
    protected $trigger;

    /**
     * InvoiceSendResult constructor.
     *
     * @param string $trigger
     *   A string indicating the function that triggered the sending, e.g.
     *   InvoiceManager::sourceStatusChange().
     */
    public function __construct($trigger)
    {
        parent::__construct();
        $this->trigger = $trigger;
        $this->sendStatus = self::SendStatus_Unknown;
        $this->sendStatusArguments = [];
    }

    /**
     * @return int
     *   A status indicating if and why an invoice was sent or not sent. It will
     *   contain 1 of the Result::Sent_... or Result::Invoice_NotSent_...
     *   constants.
     */
    public function getSendStatus()
    {
        return $this->sendStatus;
    }

    /**
     * @param int $sendStatus
     *   A status indicating if and why an invoice was sent or not sent. It will
     *   contain 1 of the Result::Sent_... or Result::Invoice_NotSent_...
     *   constants.
     * @param array $arguments
     *   A list of parameters to use when getting the send status as text.
     *
     * @return $this
     */
    public function setSendStatus($sendStatus, $arguments = [])
    {
        $this->sendStatus = $sendStatus;
        $this->sendStatusArguments = $arguments;
        return $this;
    }

    /**
     * Returns whether the invoice has been sent.
     *
     * @return bool
     *   True if the invoice has been sent, false if sending was prevented or
     *   if the sendStatus has not yet been set.
     */
    public function hasBeenSent()
    {
        return ($this->sendStatus & self::Send_Mask) !== 0;
    }

    /**
     * Returns whether the invoice has been prevented from sending.
     *
     * @return bool
     *   True if the invoice has been prevented from sensing, false if it has
     *   been sent or if the sendStatus has not yet been set.
     */
    public function isSendingPrevented()
    {
        return ($this->sendStatus & self::NotSent_Mask) !== 0;
    }

    /**
     * @return string
     *   A string indicating the function that triggered the sending, e.g.
     *   InvoiceManager::sourceStatusChange().
     */
    public function getTrigger()
    {
        return $this->trigger;
    }

    /**
     * @param string $trigger
     *   A string indicating the function that triggered the sending, e.g.
     *   InvoiceManager::sourceStatusChange().
     *
     * @return $this
     *
     * @noinspection PhpUnused
     */
    public function setTrigger($trigger)
    {
        $this->trigger = $trigger;
        return $this;
    }


    /**
     * Returns a translated string indicating the action taken (sent or not sent).
     *
     * @return string
     */
    protected function getActionText()
    {
        if ($this->hasBeenSent()) {
            $action = 'action_sent';
        } elseif ($this->isSendingPrevented()) {
            $action = 'action_not_sent';
        } else {
            $action = 'action_unknown';
        }
        return $this->t($action);
    }

    /**
     * Returns a translated string indicating the reason for the action taken.
     *
     * @return string
     */
    protected function getSendStatusText()
    {
        switch ($this->sendStatus) {
            case self::NotSent_WrongStatus:
                $message = empty($this->sendStatusArguments)
                    ? 'reason_not_sent_triggerCreditNoteEvent_None'
                    : 'reason_not_sent_wrongStatus';
                break;
            case self::NotSent_AlreadySent:
                $message = 'reason_not_sent_alreadySent';
                break;
            case self::NotSent_LockedForSending:
                $message = 'reason_not_sent_alreadySending';
                break;
            case self::NotSent_EventInvoiceCreated:
                $message = 'reason_not_sent_prevented_invoiceCreated';
                break;
            case self::NotSent_EventInvoiceCompleted:
                $message = 'reason_not_sent_prevented_invoiceCompleted';
                break;
            case self::NotSent_EmptyInvoice:
                $message = 'reason_not_sent_empty_invoice';
                break;
            case self::NotSent_TriggerInvoiceCreateNotEnabled:
                $message = 'reason_not_sent_not_enabled_triggerInvoiceCreate';
                break;
            case self::NotSent_TriggerInvoiceSentNotEnabled:
                $message = 'reason_not_sent_not_enabled_triggerInvoiceSent';
                break;
            case self::NotSent_LocalErrors:
                $message = 'reason_not_sent_local_errors';
                break;
            case self::NotSent_DryRun:
                $message = 'reason_not_sent_dry_run';
                break;
            case self::Send_TestMode:
                $message = 'reason_sent_testMode';
                break;
            case self::Send_New:
                $message = empty($this->sendStatusArguments)
                    ? 'reason_sent_new'
                    : 'reason_sent_new_status_change';
                break;
            case self::Send_LockExpired:
                $message = 'reason_sent_lock_expired';
                break;
            case self::Send_Forced:
                $message = 'reason_sent_forced';
                break;
            default:
                $message = 'reason_unknown';
                $this->sendStatusArguments = [($this->sendStatus)];
                break;
        }
        $message = $this->t($message);
        if (!empty($this->sendStatusArguments)) {
            $message = vsprintf($message, $this->sendStatusArguments);
        }
        return $message;
    }

    /**
     * Returns a translated sentence that can be used for logging.
     *
     * The returned sentence indicates what happened and why. If the invoice was
     * sent or local errors prevented it being sent, then the returned string
     * also includes any messages.
     *
     * @param int $addReqResp
     *   Whether to add the raw request and response.
     *   One of the Result::AddReqResp_... constants
     *
     * @return string
     */
    public function getLogText($addReqResp)
    {
        $action = $this->getActionText();
        $reason = $this->getSendStatusText();
        $message = sprintf($this->t('message_invoice_reason'), $action, $reason);

        if ($this->hasBeenSent() || $this->getSendStatus() === self::NotSent_LocalErrors) {
            if ($this->hasBeenSent()) {
                $message .= ' ' . $this->getStatusText();
            }
            if ($this->hasRealMessages()) {
                $message .= "\n" . $this->formatMessages(Message::Format_PlainListWithSeverity, Severity::RealMessages);
            }
            if ($addReqResp === Result::AddReqResp_Always || ($addReqResp === Result::AddReqResp_WithOther && $this->hasRealMessages())) {
                $message = rtrim($message);
                // @todo: move to dedicated getters.
                $message .= "\n" . $this->formatMessages(Message::Format_PlainList, Severity::Log);
            }
        }
        return rtrim($message);
    }
}
